<?php

namespace App\Services\Finnotech;

/**
 * v40 — نتیجهٔ استعلام فینوتک.
 *
 * حالت‌ها:
 *  • succeeded && matched   → تطبیق موفق (اجازهٔ ثبت)
 *  • succeeded && !matched  → استعلام انجام شد ولی تطبیق نشد (رد صریح)
 *  • !succeeded             → خطای سرویس/شبکه (تصمیم‌گیری سیستمی با فراخواننده)
 */
class FinnotechResult
{
    public function __construct(
        public readonly bool $succeeded,
        public readonly bool $matched,
        public readonly string $message,
        public readonly string $reason = '', // config|token|network|api|invalid_*
    ) {}

    public static function ok(bool $matched, string $message): self
    {
        return new self(true, $matched, $message);
    }

    public static function error(string $reason, string $message): self
    {
        return new self(false, false, $message, $reason);
    }

    /** استعلام موفق و تطبیق شد؟ */
    public function isVerified(): bool
    {
        return $this->succeeded && $this->matched;
    }

    /** خطای فنی (شبکه/سرویس)؟ — فراخواننده می‌تواند fail-open کند */
    public function isTechnicalError(): bool
    {
        return ! $this->succeeded && in_array($this->reason, ['token', 'network', 'api'], true);
    }
}
