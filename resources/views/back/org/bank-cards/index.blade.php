@extends('back.layouts.org', ['organization' => $organization])

@section('title', 'کارت‌های بانکی')
@section('page-title', 'کارت‌های بانکی')
@section('breadcrumb', 'پنل سازمان ← کارت‌های بانکی')

@section('content')
    <div class="card p-5 animate-fade-up">
        @include('back.partials.bank-cards', ['baseUrl' => '/organization/bank-cards', 'cards' => $cards])
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('back/assets/js/pages/bank-cards.js') }}?v=2" defer></script>
@endpush
