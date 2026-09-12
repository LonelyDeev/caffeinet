<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Support\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * تیکت‌های پشتیبانی — پنل مدیریت کل (فاز ۱۰).
 *
 * GET   /admin/tickets — لیست + فیلترها
 * GET   /admin/tickets/data — جدول AJAX
 * GET   /admin/tickets/{ticket} — صفحهٔ گفتگو
 * POST  /admin/tickets/{ticket}/reply — پاسخ/یادداشت داخلی (+پیوست)
 * PATCH /admin/tickets/{ticket}/status — close/reopen
 * PATCH /admin/tickets/{ticket}/priority — اولویت
 * PATCH /admin/tickets/{ticket}/assign — ارجاع به کارشناس
 */
class TicketsController extends Controller
{
    public function __construct(
        protected TicketService $tickets,
    ) {}

    public function index(): View
    {
        $stats = $this->counts();

        return view('back.admin.tickets.index', ['stats' => $stats]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Ticket::query()
            ->with(['order:id,order_number,coffeenet_id', 'user:id,name,family,mobile', 'assignedTo:id,name,family']);

        // فیلتر وضعیت
        if ($status = (string) $request->query('status')) {
            $query->where('status', $status);
        }

        // فیلتر اولویت
        if ($priority = (string) $request->query('priority')) {
            $query->where('priority', $priority);
        }

        // جستجو: شماره تیکت / موضوع / نام مشتری / شماره سفارش
        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('family', 'like', "%{$search}%"))
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(25);

        $rows = $paginator->through(
            fn (Ticket $t) => $this->tickets->serializeTicket($t, canSeeInternal: true)
        );

        return response()->json($rows);
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $ticket->load(['order:id,order_number,coffeenet_id,service_id', 'user:id,name,family,mobile', 'assignedTo:id,name,family']);

        $messages = $ticket->messages()->with('sender:id,name,family')->get();

        $payload = [
            'ticket' => $this->tickets->serializeTicket($ticket, canSeeInternal: true),
            'messages' => $messages->map(
                fn ($m) => $this->tickets->serializeMessage($m, canSeeInternal: true, viewerId: (int) $request->user()->id)
            )->values(),
            'last_id' => (int) ($messages->last()?->id ?? 0),
            'can_manage' => true,
            'staff' => \App\Models\User::query()->role('super_admin')->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'family']),
        ];

        return view('back.admin.tickets.show', [
            'ticket' => $ticket,
            'payload' => $payload,
        ]);
    }

    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate([
            'message' => ['nullable', 'string', 'max:3000'],
            'file' => ['nullable', 'file', 'max:15360'],
            'internal' => ['nullable', 'boolean'],
        ]);

        try {
            $message = $this->tickets->reply(
                $ticket,
                $request->user(),
                (string) $request->input('message', ''),
                $request->file('file'),
                (bool) $request->boolean('internal'),
                fromCustomer: false,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'ارسال پاسخ ناموفق بود.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => $request->boolean('internal') ? 'یادداشت داخلی ثبت شد.' : 'پاسخ ارسال شد.',
            'data' => $this->tickets->serializeMessage($message, canSeeInternal: true, viewerId: (int) $request->user()->id),
            'status' => $ticket->refresh()->status->value,
            'status_label' => $ticket->status->label(),
        ], 201);
    }

    /** پیام‌های تازه برای پولینگ افزایشی گفتگو (AJAX) */
    public function messages(Request $request, Ticket $ticket): JsonResponse
    {
        $afterId = (int) $request->query('after_id', 0);

        $query = $ticket->messages()->with('sender:id,name,family');

        if ($afterId > 0) {
            $query->where('id', '>', $afterId)->orderBy('id');
        } else {
            $query->latest('id')->limit(60);
        }

        $messages = $query->get();

        if ($afterId === 0) {
            $messages = $messages->reverse()->values();
        }

        return response()->json([
            'messages' => $messages->map(
                fn ($m) => $this->tickets->serializeMessage($m, canSeeInternal: true, viewerId: (int) $request->user()->id)
            )->values(),
            'last_id' => (int) ($messages->last()?->id ?? $afterId),
            'status' => $ticket->status->value,
        ]);
    }

    public function status(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:close,reopen'],
        ]);

        if ($data['action'] === 'close') {
            $this->tickets->close($ticket, $request->user());
        } else {
            $this->tickets->reopen($ticket, $request->user());
        }

        return response()->json([
            'message' => $data['action'] === 'close'
                ? 'تیکت «'.$ticket->ticket_number.'» بسته شد.'
                : 'تیکت «'.$ticket->ticket_number.'» بازگشایی شد.',
            'status' => $ticket->refresh()->status->value,
            'status_label' => $ticket->status->label(),
        ]);
    }

    public function priority(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'priority' => ['required', 'in:low,normal,high'],
        ]);

        $this->tickets->setPriority($ticket, $data['priority']);

        return response()->json([
            'message' => 'اولویت تیکت به «'.TicketService::priorityLabel($data['priority']).'» تغییر کرد.',
            'priority' => $ticket->refresh()->priority,
            'priority_label' => TicketService::priorityLabel($ticket->priority),
        ]);
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        try {
            $this->tickets->assign($ticket, (int) $data['user_id'], $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'ارجاع ناموفق بود.',
            ], 422);
        }

        return response()->json([
            'message' => 'تیکت ارجاع شد.',
            'assigned_name' => $ticket->refresh()->assignedTo?->full_name,
        ]);
    }

    /** چیپ‌های آماری بالای لیست */
    protected function counts(): array
    {
        return [
            'total' => Ticket::count(),
            'open' => Ticket::where('status', 'open')->count(),
            'answered' => Ticket::where('status', 'answered')->count(),
            'customer_reply' => Ticket::where('status', 'customer_reply')->count(),
            'closed' => Ticket::where('status', 'closed')->count(),
            'high' => Ticket::where('priority', 'high')->where('status', '!=', 'closed')->count(),
        ];
    }
}
