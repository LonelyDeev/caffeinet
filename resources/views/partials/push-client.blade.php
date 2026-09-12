{{-- نوتیف دستگاه (Web Push / FCM) — v25 --}}
{{-- مشترک ۴ پنل + اپ مشتری. متغیر $pushRegisterUrl (اختیاری):
      روت ثبت توکن برای پنل‌ها؛ اگر خالی باشد، اپ مشتری از CN.api استفاده می‌کند. --}}
@php
    $pushCfg = app(\App\Services\Push\FcmPushService::class)->clientConfig(auth()->user());
    $pushCfg['registerUrl'] = $pushRegisterUrl ?? null;
@endphp
<script src="{{ asset('assets/js/push/push-client.js') }}?v=1" data-push-config='@json($pushCfg)'></script>
