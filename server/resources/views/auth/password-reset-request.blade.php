@extends('layouts.app')

@section('content')
<div class="auth-screen" data-auth-screen>
    @include('partials.auth-screen-header', [
        'authTitle' => 'Đặt lại mật khẩu',
        'authBackUrl' => route('login'),
    ])

    <div class="auth-screen-body">
        <form method="POST" action="{{ route('password.reset.request') }}">
            @csrf
            @include('partials.auth-field-row', [
                'fieldLabel' => 'Số điện thoại',
                'fieldName' => 'phone',
                'fieldId' => 'reset-phone',
                'fieldType' => 'tel',
                'fieldValue' => old('phone'),
                'fieldAutocomplete' => 'off',
                'fieldInputmode' => 'tel',
                'nextType' => 'submit',
                'nextAttr' => '',
                'nextAria' => 'Gửi yêu cầu',
            ])
        </form>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/auth-field-validation.js') }}?v={{ filemtime(public_path('js/auth-field-validation.js')) }}"></script>
<script>
(function () {
  var form = document.querySelector('.auth-screen form');
  if (form && window.AuthFieldValidation) AuthFieldValidation.bindPhoneSubmit(form);
})();
</script>
@endpush
