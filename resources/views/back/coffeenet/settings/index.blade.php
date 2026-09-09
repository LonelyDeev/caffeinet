@extends('back.coffeenet.layouts.panel')

@section('title', 'تنظیمات')
@section('page-title', 'تنظیمات')
@section('breadcrumb', 'پنل کافی‌نت ← تنظیمات')

@section('content')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        {{-- اطلاعات پرونده --}}
        <section class="card ui-lift p-5 animate-fade-up xl:col-span-1 h-fit overflow-hidden relative">
            <span class="ui-orb" data-pos="tr" aria-hidden="true"></span>
            <h2 class="no-card-title text-sm font-extrabold text-stone-700 relative">پرونده کافی‌نت</h2>
            <p class="text-[11px] text-stone-400 mt-1 mb-4 relative">اطلاعات ثابت — تغییرات فقط از طریق مدیریت کل</p>

            <dl class="space-y-3 text-xs">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-stone-400">وضعیت</dt>
                    <dd>
                        <span class="badge {{ $coffeenet->status === \App\Enums\CoffeenetStatus::Approved ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">{{ $coffeenet->status->label() }}</span>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-stone-400">وابستگی</dt>
                    <dd class="font-semibold text-stone-600">{{ $coffeenet->organization?->name ?? 'کافی‌نت مستقل' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-stone-400">تاریخ تأیید</dt>
                    <dd class="font-semibold text-stone-600">{{ $coffeenet->approved_at ? jdate($coffeenet->approved_at)->format('Y/m/d') : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-stone-400">ایمیل مدیر</dt>
                    <dd class="font-semibold text-stone-600 truncate" dir="ltr">{{ auth()->user()->email }}</dd>
                </div>
            </dl>
        </section>

        {{-- فرم ویرایش اطلاعات --}}
        <section class="card ui-lift p-5 xl:col-span-2 animate-fade-up delay-1 overflow-hidden relative">
            <span class="ui-orb" data-tone="amber" data-pos="bl" aria-hidden="true"></span>
            <h2 class="no-card-title text-sm font-extrabold text-stone-700 relative">اطلاعات تماس و آدرس</h2>
            <p class="text-[11px] text-stone-400 mt-1 mb-5 relative">این اطلاعات برای مشتریان و پشتیبانی نمایش داده می‌شود.</p>

            <form id="info-form" class="space-y-4" novalidate>
                <p id="info-error" class="hidden text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-3.5 py-2.5 leading-6"></p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="lbl" for="i-name">نام کافی‌نت <span class="text-rose-500">*</span></label>
                        <input id="i-name" type="text" class="field" value="{{ $coffeenet->name }}" autocomplete="off">
                        <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="name"></p>
                    </div>
                    <div>
                        <label class="lbl" for="i-phone">تلفن</label>
                        <input id="i-phone" type="text" dir="ltr" class="field" value="{{ $coffeenet->phone ?? '' }}" placeholder="02112345678" autocomplete="off">
                    </div>
                    <div>
                        <label class="lbl" for="i-province">استان</label>
                        <select id="i-province" class="field">
                            <option value="">— انتخاب استان —</option>
                            @foreach ($provinces as $province)
                                <option value="{{ $province->id }}" {{ $province->id === $coffeenet->province_id ? 'selected' : '' }}>{{ $province->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="lbl" for="i-city">شهرستان</label>
                        <select id="i-city" class="field" {{ $coffeenet->province_id ? '' : 'disabled' }}>
                            <option value="">— انتخاب شهرستان —</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="lbl" for="i-address">نشانی</label>
                        <textarea id="i-address" rows="3" class="field !py-2.5" maxlength="1000" placeholder="مثلاً: تهران، خیابان ولیعصر، پلاک ۱۲">{{ $coffeenet->address ?? '' }}</textarea>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" id="btn-info" class="btn-primary btn-shine !py-2.5 !text-xs !px-6">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg>
                        ذخیره اطلاعات
                    </button>
                </div>
            </form>
        </section>

        {{-- تغییر رمز --}}
        <section class="card ui-lift p-5 xl:col-span-3 animate-fade-up delay-2 overflow-hidden relative">
            <span class="ui-orb" data-tone="teal" data-pos="tr" aria-hidden="true"></span>
            <h2 class="no-card-title text-sm font-extrabold text-stone-700 relative">تغییر رمز عبور</h2>
            <p class="text-[11px] text-stone-400 mt-1 mb-5 relative">رمز عبور ورود شما به پنل کافی‌نت.</p>

            <form id="pass-form" class="space-y-4 max-w-xl relative" novalidate>
                <p id="pass-error" class="hidden text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-3.5 py-2.5 leading-6"></p>

                <div>
                    <label class="lbl" for="p-current">رمز عبور فعلی <span class="text-rose-500">*</span></label>
                    <input id="p-current" type="password" dir="ltr" class="field" autocomplete="current-password" required>
                    <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="current_password"></p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="lbl" for="p-new">رمز عبور جدید <span class="text-rose-500">*</span></label>
                        <input id="p-new" type="password" dir="ltr" class="field" autocomplete="new-password" minlength="8" required>
                        <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="password"></p>
                    </div>
                    <div>
                        <label class="lbl" for="p-confirm">تکرار رمز جدید <span class="text-rose-500">*</span></label>
                        <input id="p-confirm" type="password" dir="ltr" class="field" autocomplete="new-password" minlength="8" required>
                        <p class="err text-[11px] text-rose-500 mt-1 hidden" data-for="password_confirmation"></p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" id="btn-pass" class="btn-primary btn-shine !py-2.5 !text-xs !px-6">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        تغییر رمز عبور
                    </button>
                </div>
            </form>
        </section>
    </div>


{{-- داده‌های سرور برای اسکریپت صفحه (بدون JS درون‌خطی) --}}
<div id="page-data" hidden data-payload="{{ json_encode(['base' => '/coffeenet/' . $coffeenet->id . '/settings', 'city_id' => (int) $coffeenet->city_id]) }}"></div>

@endsection

@push('scripts')
<script src="{{ asset('back/assets/js/pages/coffeenet/settings/index.js') }}"></script>
@endpush
