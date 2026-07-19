<input type="hidden" name="password" data-register-password value="{{ old('password') }}">
<input type="hidden" name="password_confirmation" data-register-password-confirm value="{{ old('password_confirmation') }}">

<div id="driver-wizard" data-driver-wizard>
    <div class="auth-step-panel driver-wizard-panel" data-wizard-step="1">
        @include('partials.driver-docs-upload-register')
        <div class="auth-group-actions">
            @include('partials.auth-next-btn', ['nextAttr' => 'data-wizard-next'])
        </div>
    </div>

    <div class="auth-step-panel driver-wizard-panel" data-wizard-step="2" hidden>
        @include('partials.driver-core-fields', [
            'context'  => 'register',
            'user'     => null,
            'profile'  => null,
            'sections' => ['account'],
        ])
        <div class="auth-group-actions">
            @include('partials.auth-next-btn', ['nextAttr' => 'data-wizard-next'])
        </div>
    </div>

    <div class="auth-step-panel driver-wizard-panel" data-wizard-step="3" hidden>
        @include('partials.driver-core-fields', [
            'context'  => 'register',
            'user'     => null,
            'profile'  => null,
            'sections' => ['vehicle'],
            'compact'  => true,
        ])
        <div class="auth-group-actions">
            @include('partials.auth-next-btn', ['nextAttr' => 'data-wizard-next'])
        </div>
    </div>

    <div class="auth-step-panel driver-wizard-panel" data-wizard-step="4" hidden>
        @include('partials.driver-core-fields', [
            'context'  => 'register',
            'user'     => null,
            'profile'  => null,
            'sections' => ['bank'],
        ])
        <div class="auth-group-actions">
            @include('partials.auth-next-btn', ['nextAttr' => 'data-wizard-next'])
        </div>
    </div>

    <div class="auth-step-panel driver-wizard-panel" data-wizard-step="5" hidden>
        <div class="auth-terms-row">
            <input class="@error('terms') is-invalid @enderror" type="checkbox"
                   name="terms" value="1" id="termsCheck" {{ old('terms') ? 'checked' : '' }} required>
            <span>Đồng ý điều khoản {{ config('app.name') }}.</span>
        </div>
        <div class="invalid-feedback" data-client-feedback="terms">@error('terms'){{ $message }}@enderror</div>
        <div class="auth-group-actions">
            @include('partials.auth-next-btn', ['nextAttr' => 'data-wizard-next'])
        </div>
    </div>

    <div class="auth-step-panel driver-wizard-panel" data-wizard-step="6" hidden>
        @include('partials.auth-pin-row', [
            'pinName' => 'pin_draft',
            'pinId' => 'driver-pin',
            'pinLabel' => 'PIN',
            'hideNext' => true,
        ])
    </div>

    <div class="auth-step-panel driver-wizard-panel" data-wizard-step="7" hidden>
        @include('partials.auth-pin-row', [
            'pinName' => 'pin_confirm_draft',
            'pinId' => 'driver-pin-confirm',
            'pinLabel' => 'Nhập lại PIN',
            'hideNext' => true,
        ])
    </div>
</div>
