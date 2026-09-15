{{-- v39 — نمایش فقط‌خواندنی کارت‌های بانکی در پنل مدیریت کل
     $cards: مجموعهٔ BankCard | $ownerLabel: عنوان مالک (مثلاً «اپراتور») --}}
<section class="dt-section">
    <div class="dt-section-head">
        <h2 class="dt-section-title">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
            کارت‌های بانکی {{ $ownerLabel ?? '' }}
        </h2>
    </div>

    @forelse ($cards as $card)
        <div class="flex items-center justify-between gap-3 text-xs border-b border-dashed border-stone-100 py-2">
            <span class="text-stone-400 font-semibold shrink-0">
                {{ $card->is_default ? '⭐ پیش‌فرض' : 'کارت' }}
            </span>
            <span class="flex flex-wrap items-center justify-end gap-x-4 gap-y-1">
                @if ($card->card_number)
                    <span class="text-stone-400">کارت:</span>
                    <span class="font-mono font-bold text-stone-700" dir="ltr">{{ fa_digits($card->card_number) }}</span>
                @endif
                @if ($card->sheba_number)
                    <span class="text-stone-400">شبا:</span>
                    <span class="font-mono font-bold text-stone-700 text-[11px]" dir="ltr">{{ fa_digits($card->sheba_number) }}</span>
                @endif
                @if ($card->account_number)
                    <span class="text-stone-400">حساب:</span>
                    <span class="font-mono font-bold text-stone-700" dir="ltr">{{ fa_digits($card->account_number) }}</span>
                @endif
                @if ($card->holder_name)
                    <span class="text-stone-500">({{ $card->holder_name }})</span>
                @endif
            </span>
        </div>
    @empty
        <p class="text-xs text-stone-400 py-2">هنوز کارتی ثبت نشده است.</p>
    @endforelse
</section>
