@extends('layouts.app')

@section('content')
<div class="auth-screen" data-auth-screen data-reset-pin>
    @include('partials.auth-screen-header', [
        'authTitle' => 'PIN mới',
        'authBackUrl' => route('password.reset.code'),
    ])

    <div class="auth-screen-body">
        <form method="POST" action="{{ route('password.reset.pin') }}" id="reset-pin-form">
            @csrf

            <div class="auth-step-panel" data-reset-step="pin">
                @include('partials.auth-pin-row', [
                    'pinName' => 'password',
                    'pinId' => 'reset-pin',
                    'pinLabel' => 'PIN mới',
                    'pinErrorBag' => 'password',
                    'nextType' => 'button',
                    'nextAttr' => 'data-reset-to-confirm',
                    'nextAria' => 'Tiếp tục',
                ])
            </div>

            <div class="auth-step-panel" data-reset-step="confirm" hidden>
                @include('partials.auth-pin-row', [
                    'pinName' => 'password_confirmation',
                    'pinId' => 'reset-pin-confirm',
                    'pinLabel' => 'Nhập lại PIN',
                    'pinErrorBag' => 'password_confirmation',
                    'nextType' => 'submit',
                    'nextAttr' => '',
                    'nextAria' => 'Lưu PIN',
                ])
            </div>
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
  var root = document.querySelector('[data-reset-pin]');
  if (!root || !window.PinInput) return;
  var V = window.AuthFieldValidation;
  var form = root.querySelector('#reset-pin-form');
  var pinStep = root.querySelector('[data-reset-step="pin"]');
  var confirmStep = root.querySelector('[data-reset-step="confirm"]');
  var titleEl = root.querySelector('[data-auth-title]');
  var backLink = root.querySelector('[data-auth-back]');
  var codeUrl = backLink ? backLink.getAttribute('href') : '';
  var draft = '';

  function showPin() {
    pinStep.hidden = false;
    confirmStep.hidden = true;
    if (titleEl) titleEl.textContent = 'PIN mới';
    if (backLink) {
      backLink.setAttribute('href', codeUrl);
      backLink.onclick = null;
    }
    var wrap = pinStep.querySelector('[data-pin-boxes]');
    if (wrap) {
      PinInput.clear(wrap);
      if (V) V.clearPinError(wrap);
    }
  }

  function showConfirm() {
    pinStep.hidden = true;
    confirmStep.hidden = false;
    if (titleEl) titleEl.textContent = 'Nhập lại PIN';
    if (backLink) {
      backLink.setAttribute('href', '#');
      backLink.onclick = function (e) {
        e.preventDefault();
        draft = '';
        showPin();
      };
    }
    var wrap = confirmStep.querySelector('[data-pin-boxes]');
    if (wrap) {
      PinInput.clear(wrap);
      if (V) V.clearPinError(wrap);
    }
  }

  var toConfirm = root.querySelector('[data-reset-to-confirm]');
  if (toConfirm) {
    toConfirm.addEventListener('click', function () {
      var wrap = pinStep.querySelector('[data-pin-boxes]');
      if (V) {
        if (!V.validatePinWrap(wrap)) return;
        draft = V.pinValueFrom(wrap);
        showConfirm();
        return;
      }
      var val = PinInput.value(wrap);
      if (!/^\d{6}$/.test(val)) {
        PinInput.clear(wrap);
        return;
      }
      draft = val;
      showConfirm();
    });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      var wrap = confirmStep.querySelector('[data-pin-boxes]');
      var val = V ? V.pinValueFrom(wrap) : PinInput.value(wrap);
      if (!/^\d{6}$/.test(val) || val !== draft) {
        e.preventDefault();
        if (V && wrap) V.showPinError(wrap, (V.MSG && V.MSG.pinMismatch) || 'Nhập lại PIN không khớp.');
        draft = '';
        showPin();
      }
    });
  }
})();
</script>
@endpush
