<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Support\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * تیکت‌های پشتیبانی مشتری (فاز ۱۰).
 *
 * GET  /api/v1/tickets?status= — لیست تیکت‌های خودم
 * POST /api/v1/tickets — ثبت تیکت (موضوع + توضیح + اولویت + سفارش اختیاری)
 * GET  /api/v1/tickets/{ticket} — گفتگو (پیام‌ها + وضعیت)
 * POST /api/v1/tickets/{ticket}/messages — پاسخ (متن + پیوست اختیاری)
 * POST /api/v1/tickets/{ticket}/close — بستن توسط مشتری
 */
class TicketsController extends Controller
{
    public function __construct(protected TicketService $tickets) {}

    public function index(Request $request): JsonResponse
    {
        $query = Ticket::query()
            ->with(['order:id,order_number', 'user:id,name,family'])
            ->where('user_id', $request->user()->id);

        if ($status = (string) $request->query('status')) {
            $enum = TicketStatus::tryFrom($status);

            if ($enum) {
                $query->where('status', $enum->value);
            }
        }

        $paginator = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(15);

        $rows = $paginator->through(
            fn (Ticket $t) => $this->tickets->serializeTicket($t, canSeeInternal: false)
        );

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        // فقط مشتریِ تکمیل‌شدهٔ پروفایل می‌تواند تیکت ثبت کند (v24)
        $this->requireCompletedProfile($request);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:3000'],
            'priority' => ['nullable', 'in:low,normal,high'],
            'order_id' => ['nullable', 'integer'],
        ], [
            'subject.required' => 'موضوع تیکت الزامی است.',
            'message.required' => 'توضیح مشکل الزامی است.',
        ]);

        try {
            $ticket = $this->tickets->create($request->user(), $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'ثبت تیکت ناموفق بود.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'تیکت شما با شماره «'.$ticket->ticket_number.'» ثبت شد؛ کارشناسان به‌زودی پاسخ می‌دهند.',
            'data' => $this->tickets->serializeTicket($ticket->refresh(), canSeeInternal: false),
        ], 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeOwner($request, $ticket);

        $ticket->load(['order:id,order_number', 'user:id,name,family']);

        $messages = $ticket->messages()
            ->where('is_internal', false)
            ->with('sender:id,name,family')
            ->get();

        return response()->json([
            'data' => $this->tickets->serializeTicket($ticket, canSeeInternal: false),
            'messages' => $messages->map(
                fn ($m) => $this->tickets->serializeMessage($m, false, $request->user()->id)
            )->values(),
            'last_id' => (int) ($messages->last()?->id ?? 0),
            'can_reply' => in_array($ticket->status, TicketService::CUSTOMER_REPLY_STATUSES, true)
                || $ticket->status === TicketStatus::Closed, // پیام مشتری روی بسته = بازگشایی
        ]);
    }

    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeOwner($request, $ticket);

        // فقط مشتریِ تکمیل‌شدهٔ پروفایل می‌تواند پاسخ دهد (v24)
        $this->requireCompletedProfile($request);

        $request->validate([
            'message' => ['nullable', 'string', 'max:3000'],
            'file' => ['nullable', 'file', 'max:15360'],
        ]);

        try {
            $message = $this->tickets->reply(
                $ticket->refresh(),
                $request->user(),
                (string) $request->input('message', ''),
                $request->file('file'),
                internal: false,
                fromCustomer: true,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'ارسال پاسخ ناموفق بود.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'پاسخ شما ثبت شد.',
            'data' => $this->tickets->serializeMessage($message, false, $request->user()->id),
            'status' => $ticket->refresh()->status->value,
        ], 201);
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeOwner($request, $ticket);

        $this->tickets->close($ticket, $request->user());

        return response()->json([
            'message' => 'تیکت «'.$ticket->ticket_number.'» بسته شد.',
            'status' => $ticket->refresh()->status->value,
        ]);
    }

    /** ثبت تیکت/پاسخ فقط با پروفایل کامل (v24) */
    protected function requireCompletedProfile(Request $request): void
    {
        if (! $request->user()->profile_completed) {
            throw ValidationException::withMessages([
                "profile" => ["ابتدا اطلاعات پروفایل خود را کامل کنید."],
            ]);
        }
    }

    /** تیکتِ خودت یا ۴۰۴ (عدم افشای وجود) */
    protected function authorizeOwner(Request $request, Ticket $ticket): void
    {
        if ((int) $ticket->user_id !== (int) $request->user()->id) {
            abort(404, 'تیکت یافت نشد.');
        }
    }
}
