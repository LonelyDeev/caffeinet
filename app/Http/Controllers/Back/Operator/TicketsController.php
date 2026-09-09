<?php

namespace App\Http\Controllers\Back\Operator;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Support\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * تیکت‌های پشتیبانی — پنل اپراتور (فاز ۱۰).
 *
 * دید: تیکت‌های سفارش‌های کافی‌نت جاری (با مجوز tickets.view).
 * اپراتور می‌تواند پاسخ عمومی بدهد؛ یادداشت داخلی فقط با tickets.manage.
 */
class TicketsController extends Controller
{
    public function __construct(
        protected TicketService $tickets,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('tickets.view'), 403);

        $coffeenet = $request->attributes->get('current_coffeenet');

        return view('back.operator.tickets.index', [
            'coffeenet' => $coffeenet,
            'stats' => $this->counts($coffeenet),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('tickets.view'), 403);

        $coffeenet = $request->attributes->get('current_coffeenet');

        $query = $this->scopedQuery($coffeenet);

        if ($status = (string) $request->query('status')) {
            $query->where('tickets.status', $status);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('tickets.ticket_number', 'like', "%{$search}%")
                    ->orWhere('tickets.subject', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->orderByDesc('tickets.updated_at')->orderByDesc('tickets.id')->paginate(25);

        $rows = $paginator->through(
            fn (Ticket $t) => $this->tickets->serializeTicket($t, canSeeInternal: false)
        );

        return response()->json($rows);
    }

    public function show(Request $request, Ticket $ticket): View
    {
        abort_unless($request->user()->can('tickets.view'), 403);

        $coffeenet = $request->attributes->get('current_coffeenet');
        $this->authorizeScope($coffeenet, $ticket);

        $ticket->load(['order:id,order_number', 'user:id,name,family', 'assignedTo:id,name,family']);

        // اپراتور یادداشت داخلی نمی‌بیند (فقط tickets.manage)
        $messages = $ticket->messages()->where('is_internal', false)->with('sender:id,name,family')->get();

        $payload = [
            'ticket' => $this->tickets->serializeTicket($ticket, canSeeInternal: false),
            'messages' => $messages->map(
                fn ($m) => $this->tickets->serializeMessage($m, canSeeInternal: false)
            )->values(),
            'last_id' => (int) ($messages->last()?->id ?? 0),
            'can_manage' => false,
        ];

        return view('back.operator.tickets.show', [
            'coffeenet' => $coffeenet,
            'ticket' => $ticket,
            'payload' => $payload,
        ]);
    }

    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()->can('tickets.view'), 403);

        $coffeenet = $request->attributes->get('current_coffeenet');
        $this->authorizeScope($coffeenet, $ticket);

        $request->validate([
            'message' => ['nullable', 'string', 'max:3000'],
            'file' => ['nullable', 'file', 'max:15360'],
        ]);

        try {
            $message = $this->tickets->reply(
                $ticket,
                $request->user(),
                (string) $request->input('message', ''),
                $request->file('file'),
                internal: false,
                fromCustomer: false,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'ارسال پاسخ ناموفق بود.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'پاسخ ارسال شد.',
            'data' => $this->tickets->serializeMessage($message, canSeeInternal: false),
            'status' => $ticket->refresh()->status->value,
            'status_label' => $ticket->status->label(),
        ], 201);
    }

    /** پیام‌های تازه برای پولینگ افزایشی گفتگو (AJAX) — بدون یادداشت داخلی */
    public function messages(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()->can('tickets.view'), 403);

        $coffeenet = $request->attributes->get('current_coffeenet');
        $this->authorizeScope($coffeenet, $ticket);

        $afterId = (int) $request->query('after_id', 0);

        $query = $ticket->messages()->where('is_internal', false)->with('sender:id,name,family');

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
                fn ($m) => $this->tickets->serializeMessage($m, false)
            )->values(),
            'last_id' => (int) ($messages->last()?->id ?? $afterId),
            'status' => $ticket->status->value,
        ]);
    }

    /* ------------------------------------------------------------------ */

    protected function authorizeScope($coffeenet, Ticket $ticket): void
    {
        if (! $ticket->order || (int) $ticket->order->coffeenet_id !== (int) $coffeenet->id) {
            abort(404, 'تیکت یافت نشد.');
        }
    }

    protected function scopedQuery($coffeenet): \Illuminate\Database\Eloquent\Builder
    {
        return Ticket::query()
            ->with(['order:id,order_number,coffeenet_id', 'user:id,name,family', 'assignedTo:id,name,family'])
            ->whereHas('order', fn ($o) => $o->where('coffeenet_id', $coffeenet->id));
    }

    protected function counts($coffeenet): array
    {
        $base = $this->scopedQuery($coffeenet);

        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->where('status', TicketStatus::Open->value)->count(),
            'answered' => (clone $base)->where('status', TicketStatus::Answered->value)->count(),
            'customer_reply' => (clone $base)->where('status', TicketStatus::CustomerReply->value)->count(),
            'closed' => (clone $base)->where('status', TicketStatus::Closed->value)->count(),
            'high' => (clone $base)->where('priority', 'high')->where('status', '!=', 'closed')->count(),
        ];
    }
}
