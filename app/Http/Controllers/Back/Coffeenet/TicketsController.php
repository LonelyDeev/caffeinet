<?php

namespace App\Http\Controllers\Back\Coffeenet;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Coffeenet;
use App\Models\Ticket;
use App\Services\Support\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * تیکت‌های پشتیبانی — پنل کافی‌نت (فاز ۱۰).
 *
 * فقط تیکت‌هایی که سفارش مرتبط‌شان به همین کافی‌نت تعلق دارد (سایر تیکت‌ها
 * عمومی‌اند و فقط مدیریت کل پاسخ می‌دهد). کافی‌نت‌منیجر مجاز به پاسخ + بستن است.
 */
class TicketsController extends Controller
{
    public function __construct(
        protected TicketService $tickets,
    ) {}

    public function index(Request $request, Coffeenet $coffeenet): View
    {
        return view('back.coffeenet.tickets.index', [
            'coffeenet' => $coffeenet,
            'stats' => $this->counts($coffeenet),
        ]);
    }

    public function data(Request $request, Coffeenet $coffeenet): JsonResponse
    {
        $query = $this->scopedQuery($coffeenet);

        if ($status = (string) $request->query('status')) {
            $query->where('tickets.status', $status);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('tickets.ticket_number', 'like', "%{$search}%")
                    ->orWhere('tickets.subject', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('family', 'like', "%{$search}%"))
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->orderByDesc('tickets.updated_at')->orderByDesc('tickets.id')->paginate(25);

        $rows = $paginator->through(
            fn (Ticket $t) => $this->tickets->serializeTicket($t, canSeeInternal: $this->canInternal($request))
        );

        return response()->json($rows);
    }

    public function show(Request $request, Coffeenet $coffeenet, Ticket $ticket): View
    {
        $this->authorizeScope($coffeenet, $ticket);

        $ticket->load(['order:id,order_number', 'user:id,name,family,mobile', 'assignedTo:id,name,family']);

        $messages = $ticket->messages()->with('sender:id,name,family')->get();

        $payload = [
            'ticket' => $this->tickets->serializeTicket($ticket, canSeeInternal: $this->canInternal($request)),
            'messages' => $messages->map(
                fn ($m) => $this->tickets->serializeMessage($m, canSeeInternal: $this->canInternal($request), viewerId: (int) $request->user()->id)
            )->values(),
            'last_id' => (int) ($messages->last()?->id ?? 0),
            'can_manage' => $this->canInternal($request),
        ];

        return view('back.coffeenet.tickets.show', [
            'coffeenet' => $coffeenet,
            'ticket' => $ticket,
            'payload' => $payload,
        ]);
    }

    public function reply(Request $request, Coffeenet $coffeenet, Ticket $ticket): JsonResponse
    {
        $this->authorizeScope($coffeenet, $ticket);

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
                (bool) $request->boolean('internal') && $this->canInternal($request),
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
            'data' => $this->tickets->serializeMessage($message, canSeeInternal: $this->canInternal($request), viewerId: (int) $request->user()->id),
            'status' => $ticket->refresh()->status->value,
            'status_label' => $ticket->status->label(),
        ], 201);
    }

    /** پیام‌های تازه برای پولینگ افزایشی گفتگو (AJAX) */
    public function messages(Request $request, Coffeenet $coffeenet, Ticket $ticket): JsonResponse
    {
        $this->authorizeScope($coffeenet, $ticket);

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

        $canInternal = $this->canInternal($request);

        return response()->json([
            'messages' => $messages->map(
                fn ($m) => $this->tickets->serializeMessage($m, $canInternal, (int) $request->user()->id)
            )->values(),
            'last_id' => (int) ($messages->last()?->id ?? $afterId),
            'status' => $ticket->status->value,
        ]);
    }

    public function status(Request $request, Coffeenet $coffeenet, Ticket $ticket): JsonResponse
    {
        $this->authorizeScope($coffeenet, $ticket);

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

    /* ------------------------------------------------------------------ */

    /** تیکت باید سفارشِ همین کافی‌نت را داشته باشد */
    protected function authorizeScope(Coffeenet $coffeenet, Ticket $ticket): void
    {
        if (! $ticket->order || (int) $ticket->order->coffeenet_id !== (int) $coffeenet->id) {
            abort(404, 'تیکت یافت نشد.');
        }
    }

    protected function scopedQuery(Coffeenet $coffeenet): \Illuminate\Database\Eloquent\Builder
    {
        return Ticket::query()
            ->with(['order:id,order_number,coffeenet_id', 'user:id,name,family,mobile', 'assignedTo:id,name,family'])
            ->whereHas('order', fn ($o) => $o->where('coffeenet_id', $coffeenet->id));
    }

    protected function canInternal(Request $request): bool
    {
        return $request->user()->can('tickets.manage');
    }

    protected function counts(Coffeenet $coffeenet): array
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
