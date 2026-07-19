@extends('layouts.app')

@section('content')
<div class="auth-screen" data-auth-screen>
    @include('partials.auth-screen-header', [
        'authTitle' => 'Nhập mã',
        'authBackUrl' => route('password.reset.request'),
    ])

    <div class="auth-screen-body">
        <form method="POST" action="{{ route('password.reset.code') }}" data-pin-autosubmit="1">
            @csrf
            @include('partials.auth-pin-row', [
                'pinName' => 'code',
                'pinId' => 'reset-code',
                'pinLabel' => 'Mã xác minh',
                'pinErrorBag' => 'code',
                'hideNext' => true,
                'pinAutoSubmit' => true,
            ])
        </form>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/pin-input.js') }}?v={{ filemtime(public_path('js/pin-input.js')) }}"></script>
<script src="{{ asset('js/auth-field-validation.js') }}?v={{ filemtime(public_path('js/auth-field-validation.js')) }}"></script>
<script>
(function () {
  var form = document.querySelector('.auth-screen form');
  if (form && window.AuthFieldValidation) AuthFieldValidation.bindCodeSubmit(form);
})();
</script>
@endpush
