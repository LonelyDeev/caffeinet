{{-- 
    v36 — نشانگر حضور/آفلاین کاربر (منبع حقیقت: User::isOnline با آستانهٔ تنظیمات).
    استفاده: @include('back.shared.presence', ['puser' => $customer])
--}}
@php
    $online = $puser->isOnline();
    $seen = $puser->last_seen_at;
@endphp
<span class="presence {{ $online ? 'presence--on' : 'presence--off' }}"
      title="{{ $online ? 'کاربر آنلاین است' : ($seen ? 'آخرین بازدید: '.fa_date($seen, 'Y/m/d H:i:s') : 'هنوز هیچ فعالیتی ثبت نشده') }}">
    <span class="presence-dot" aria-hidden="true"></span>
    <span>{{ $online ? 'آنلاین' : 'آفلاین' }}</span>
    @unless ($online)
        @if ($seen)
            <span class="presence-seen">· {{ $seen->diffForHumans(null, ['locale' => 'fa']) }}</span>
        @else
            <span class="presence-seen">· بدون فعالیت</span>
        @endif
    @endunless
</span>
