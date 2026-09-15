@extends('back.coffeenet.layouts.panel')

@section('title', 'کارت‌های بانکی')
@section('page-title', 'کارت‌های بانکی')
@section('breadcrumb', 'پنل کافی‌نت ← کارت‌های بانکی')

@section('content')
    <div class="card p-5 animate-fade-up">
        @include('back.partials.bank-cards', ['baseUrl' => '/coffeenet/'.$coffeenet->id.'/bank-cards', 'cards' => $cards])
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('back/assets/js/pages/bank-cards.js') }}?v=1" defer></script>
@endpush
