<?php

namespace App\Http\Controllers\Back\Shared;

use App\Http\Controllers\Controller;
use App\Models\BankCard;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * v39 — کارت‌های بانکی مشترک سه پنل (اپراتور / مدیر کافی‌نت / مدیر سازمان).
 *
 * کارت‌ها متعلق به «کاربرِ» لاگین‌شده‌اند (هر کاربری فقط کارت‌های خودش را
 * می‌بیند و ویرایش می‌کند)؛ endpointها از هر سه پنل به همین کنترلر می‌رسند.
 */
class BankCardsController extends Controller
{
    /** صفحهٔ کارت‌های بانکی (view هر پنل از defaults خودِ روت خوانده می‌شود —
     *  چون پنل کافی‌نت پارامتر {coffeenet} هم دارد و تزریق موقعیتی جابه‌جا می‌شد) */
    public function index(Request $request): View
    {
        $view = (string) $request->route()->parameter('view', 'back.operator.bank-cards.index');

        return view($view, [
            'cards' => $this->cardsPayload($request),
        ]);
    }

    /** لیست کارت‌های کاربر جاری (AJAX) */
    public function data(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->cardsPayload($request),
        ]);
    }

    /** افزودن کارت جدید */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $user = $request->user();
        $count = $user->bankCards()->count();

        if ($count >= 10) {
            return response()->json([
                'message' => 'حداکثر ۱۰ کارت می‌توانید ثبت کنید؛ یکی را حذف کنید.',
            ], 422);
        }

        /* v40 — استعلام فینوتک: تطبیق شماره کارت و کد ملی */
        $verify = $this->verifyWithFinnotech($request, $data);
        if ($verify !== true) {
            return $verify; // پاسخ خطای ۴۲۲ آماده است
        }

        $card = $user->bankCards()->create([
            'card_number' => $data['card_number'] ?? null,
            'sheba_number' => $data['sheba_number'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'holder_name' => $data['holder_name'] ?? null,
            'is_default' => ($data['is_default'] ?? false) || $count === 0, // اولین کارت خودکار پیش‌فرض
            'verified_at' => $this->verifiedAt,
            'verified_method' => $this->verifiedAt ? 'nid' : null,
        ]);

        if ($card->is_default) {
            $this->makeDefault($card);
        }

        AuditLogger::log('bank_card.created', $card, null, $card->only(['card_number', 'sheba_number', 'account_number']),
            'ثبت کارت بانکی ('.($card->maskedCard() ?: ($card->sheba_number ?: 'شماره حساب')).')'.($card->verified_at ? ' — تأیید فینوتک ✓' : ''));

        return response()->json([
            'message' => 'کارت بانکی با موفقیت ثبت شد.'.($this->verifiedAt ? ' مالکیت کارت از طریق فینوتک تأیید شد.' : ''),
            'data' => $this->cardsPayload($request),
        ]);
    }

    /** ویرایش کارت */
    public function update(Request $request, BankCard $card): JsonResponse
    {
        $this->authorizeCard($request, $card);

        $data = $this->validated($request);

        $old = $card->only(['card_number', 'sheba_number', 'account_number', 'holder_name', 'is_default', 'verified_at']);

        /* v40 — اگر شماره کارت عوض شده، استعلام فینوتک دوباره اجرا می‌شود */
        $cardChanged = (string) ($data['card_number'] ?? '') !== (string) $card->card_number;
        if ($cardChanged) {
            $verify = $this->verifyWithFinnotech($request, $data);
            if ($verify !== true) {
                return $verify;
            }
        } else {
            $this->verifiedAt = $card->verified_at; // شماره کارت تغییر نکرده — وضعیت تأیید حفظ می‌شود
        }

        $card->update([
            'card_number' => $data['card_number'] ?? null,
            'sheba_number' => $data['sheba_number'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'holder_name' => $data['holder_name'] ?? null,
            'verified_at' => $this->verifiedAt,
            'verified_method' => $this->verifiedAt ? 'nid' : null,
        ]);

        if (! empty($data['is_default']) && ! $card->is_default) {
            $this->makeDefault($card);
        }

        AuditLogger::log('bank_card.updated', $card, $old, $card->only(['card_number', 'sheba_number', 'account_number', 'holder_name', 'is_default']),
            'ویرایش کارت بانکی ('.($card->maskedCard() ?: ($card->sheba_number ?: 'شماره حساب')).')');

        return response()->json([
            'message' => 'تغییرات کارت ذخیره شد.',
            'data' => $this->cardsPayload($request),
        ]);
    }

    /** کارت پیش‌فرض تسویه */
    public function setDefault(Request $request, BankCard $card): JsonResponse
    {
        $this->authorizeCard($request, $card);

        $this->makeDefault($card);

        AuditLogger::log('bank_card.default', $card, null, ['is_default' => true],
            'کارت پیش‌فرض تسویه: '.($card->maskedCard() ?: ($card->sheba_number ?: 'شماره حساب')));

        return response()->json([
            'message' => 'این کارت به‌عنوان پیش‌فرض تسویه ثبت شد.',
            'data' => $this->cardsPayload($request),
        ]);
    }

    /** حذف کارت */
    public function destroy(Request $request, BankCard $card): JsonResponse
    {
        $this->authorizeCard($request, $card);

        $wasDefault = $card->is_default;
        $label = $card->maskedCard() ?: ($card->sheba_number ?: 'شماره حساب');
        $card->delete();

        // اگر کارت پیش‌فرض حذف شد، قدیمی‌ترین کارتِ باقی‌مانده پیش‌فرض می‌شود
        if ($wasDefault) {
            $next = $request->user()->bankCards()->oldest()->first();
            if ($next) {
                $this->makeDefault($next);
            }
        }

        AuditLogger::log('bank_card.deleted', null, ['label' => $label], null, 'حذف کارت بانکی ('.$label.')');

        return response()->json([
            'message' => 'کارت حذف شد.',
            'data' => $this->cardsPayload($request),
        ]);
    }

    /* ----------------------------------------------------------------- */

    /** اعتبارسنجی مشترک: هر سه فیلد اختیاری‌اند اما حداقل یکی لازم است */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'card_number' => ['nullable', 'string', 'max:19'],
            'sheba_number' => ['nullable', 'string', 'max:26'],
            'account_number' => ['nullable', 'string', 'max:40'],
            'holder_name' => ['nullable', 'string', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
            'owner_nid' => ['nullable', 'string', 'max:10'], // v40 — کد ملی صاحب کارت (استعلام فینوتک)
        ], [
            'card_number.max' => 'شماره کارت معتبر نیست (۱۶ رقم).',
            'sheba_number.max' => 'شماره شبا معتبر نیست.',
            'account_number.max' => 'شماره حساب معتبر نیست.',
            'holder_name.max' => 'نام صاحب حساب طولانی است.',
            'owner_nid.max' => 'کد ملی صاحب کارت معتبر نیست (۱۰ رقم).',
        ]);

        // نرمال‌سازی: ارقام فارسی → انگلیسی، حذف فاصله/خط تیره
        foreach (['card_number', 'sheba_number', 'account_number'] as $key) {
            if (! empty($data[$key])) {
                $data[$key] = preg_replace('/[\s\-]/u', '', en_digits(trim((string) $data[$key])));
            }
        }

        // شماره کارت: ۱۶ رقم (با یا بدون IR پیشوند شبا)
        if (! empty($data['card_number']) && ! preg_match('/^\d{16}$/', (string) $data['card_number'])) {
            abort(response()->json([
                'message' => 'شماره کارت باید دقیقاً ۱۶ رقم باشد.',
                'errors' => ['card_number' => ['شماره کارت باید دقیقاً ۱۶ رقم باشد.']],
            ], 422));
        }

        // شبا: IR + ۲۴ رقم (با یا بدون IR)
        if (! empty($data['sheba_number'])) {
            $sheba = strtoupper((string) $data['sheba_number']);
            if (str_starts_with($sheba, 'IR')) {
                $sheba = 'IR'.substr($sheba, 2);
            }
            if (! preg_match('/^IR\d{24}$/', $sheba)) {
                abort(response()->json([
                    'message' => 'شماره شبا معتبر نیست (IR + ۲۴ رقم).',
                    'errors' => ['sheba_number' => ['شماره شبا معتبر نیست — باید IR به‌همراه ۲۴ رقم باشد.']],
                ], 422));
            }
            $data['sheba_number'] = $sheba;
        }

        // شماره حساب: ۴ تا ۳۰ نویسهٔ الفبایی-عددی
        if (! empty($data['account_number'])
            && ! preg_match('/^[A-Za-z0-9]{4,30}$/', (string) $data['account_number'])) {
            abort(response()->json([
                'message' => 'شماره حساب باید ۴ تا ۳۰ رقم/حرف انگلیسی باشد.',
                'errors' => ['account_number' => ['شماره حساب باید ۴ تا ۳۰ رقم/حرف انگلیسی باشد.']],
            ], 422));
        }

        // حداقل یکی از سه شماره
        if (empty($data['card_number']) && empty($data['sheba_number']) && empty($data['account_number'])) {
            abort(response()->json([
                'message' => 'حداقل یکی از شماره کارت، شبا یا شماره حساب را وارد کنید.',
                'errors' => ['card_number' => ['حداقل یکی از شماره‌ها را وارد کنید.']],
            ], 422));
        }

        return $data;
    }

    /* -----------------------------------------------------------------
     * v40 — استعلام فینوتک (تطبیق شماره کارت و کد ملی)
     *
     * اگر سرویس فینوتک روشن و تیک «بررسی کارت‌ها» فعال باشد:
     *  • برای ثبت شماره کارت، کد ملی صاحب کارت هم الزامی است؛
     *  • عدم تطبیق → ثبت کارت رد می‌شود؛
     *  • خطای فنی سرویس → کارت بدون نشان «تأییدشده» ذخیره می‌شود
     *    (تا قطعی سرویس کار پنل‌ها را نبندد) و در لاگ ثبت است.
     *
     * @return true|JsonResponse true = ادامه بده | JsonResponse = خطا
     */
    protected ?\Carbon\CarbonInterface $verifiedAt = null;

    protected function verifyWithFinnotech(Request $request, array $data): bool|JsonResponse
    {
        $this->verifiedAt = null;

        $finnotech = app(\App\Services\Finnotech\FinnotechService::class);

        if (! $finnotech->cardVerificationOn()) {
            return true; // سرویس خاموش است — بدون استعلام
        }

        $card = (string) ($data['card_number'] ?? '');
        $nid = en_digits(trim((string) ($data['owner_nid'] ?? '')));

        if ($card === '') {
            return true; // کارتی وارد نشده — کارت/حساب/شبا فقط یکی لازم است
        }

        if (! preg_match('/^\d{16}$/', $card)) {
            abort(response()->json([
                'message' => 'شماره کارت باید دقیقاً ۱۶ رقم باشد.',
                'errors' => ['card_number' => ['شماره کارت باید دقیقاً ۱۶ رقم باشد.']],
            ], 422));
        }

        if (! preg_match('/^\d{10}$/', $nid)) {
            return response()->json([
                'message' => 'برای احراز مالکیت کارت، کد ملی ۱۰ رقمی صاحب کارت الزامی است.',
                'errors' => ['owner_nid' => ['کد ملی صاحب کارت را وارد کنید (۱۰ رقم).']],
            ], 422);
        }

        $result = $finnotech->cardOwnerVerify($card, $nid, $request->user()->id);

        if ($result->succeeded && ! $result->matched) {
            return response()->json([
                'message' => 'شماره کارت به نام صاحب این کد ملی نیست؛ لطفاً شماره کارت یا کد ملی را بررسی کنید.',
                'errors' => ['card_number' => ['این کارت به نام صاحب کد ملی واردشده ثبت نشده است.']],
            ], 422);
        }

        if ($result->isVerified()) {
            $this->verifiedAt = now();
        }
        // خطای فنی → ادامه با کارتِ تأییدنشده (fail-open) + ثبت در لاگ فینوتک

        return true;
    }

    /** کارتِ خودت یا ۴۰۴ */
    protected function authorizeCard(Request $request, BankCard $card): void
    {
        if ((int) $card->user_id !== (int) $request->user()->id) {
            abort(404, 'کارت یافت نشد.');
        }
    }

    /** پیش‌فرض کردن یک کارت (بقیه غیرفعال می‌شوند) */
    protected function makeDefault(BankCard $card): void
    {
        BankCard::query()->where('user_id', $card->user_id)->where('id', '!=', $card->id)->update(['is_default' => false]);
        $card->forceFill(['is_default' => true])->save();
    }

    /** دادهٔ نمایشی کارت‌های کاربر جاری */
    protected function cardsPayload(Request $request): array
    {
        return $request->user()->bankCards()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BankCard $c) => [
                'id' => $c->id,
                'card_number' => $c->card_number,
                'card_masked' => $c->maskedCard(),
                'sheba_number' => $c->sheba_number,
                'account_number' => $c->account_number,
                'holder_name' => $c->holder_name,
                'is_default' => $c->is_default,
                'verified' => $c->isVerified(), // v40 — تأیید فینوتک
                'verified_at_fa' => $c->verified_at ? fa_date($c->verified_at, 'Y/m/d H:i') : null, // v40
            ])
            ->all();
    }
}
