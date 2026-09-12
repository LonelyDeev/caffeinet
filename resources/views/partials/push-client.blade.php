{{-- نوتیف دستگاه (Web Push) — v25/v26 --}}
{{-- مشترک ۴ پنل + اپ مشتری. متغیر $pushRegisterUrl (اختیاری):
      روت ثبت توکن برای پنل‌ها؛ اگر خالی باشد، اپ مشتری از CN.api استفاده می‌کند.
      پیکربندی بر اساس سرویس فعال: پیش‌فرض (VAPID) / پوشر Beams / فایربیس. --}}
@php
    $pushCfg = app(\App\Services\Push\PushManager::class)->clientConfig(auth()->user());
    $pushCfg['registerUrl'] = $pushRegisterUrl ?? null;
@endphp
<script src="{{ asset('assets/js/push/push-client.js') }}?v=4" data-push-config='@json($pushCfg)'></script>
