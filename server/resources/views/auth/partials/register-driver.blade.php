@php
    $wizardSteps = [
        1 => 'Giấy tờ',
        2 => 'Tài khoản',
        3 => 'Xe',
        4 => 'Ngân hàng',
        5 => 'Xác nhận',
    ];
@endphp
<div id="driver-wizard" data-driver-wizard>
    <div class="driver-wizard-progress">
        <div class="driver-wizard-steps" role="tablist" aria-label="Các bước đăng ký">
            @foreach($wizardSteps as $num => $label)
                <button type="button"
                        class="driver-wizard-step-btn{{ $num === 1 ? ' active' : '' }}"
                        data-goto-step="{{ $num }}"
                        data-step-label="{{ $label }}"
                        aria-label="Bước {{ $num }}: {{ $label }}">
                    <span class="step-num">{{ $num }}</span>
                </button>
            @endforeach
        </div>
        <div class="driver-wizard-meta">
            <span class="driver-wizard-step-label" data-wizard-step-label>{{ $wizardSteps[1] }}</span>
            <span class="driver-wizard-step-count" data-wizard-step-count>1/{{ count($wizardSteps) }}</span>
        </div>
        <div class="progress driver-wizard-bar">
            <div class="progress-bar" data-wizard-progress style="width:20%"></div>
        </div>
    </div>

    <div class="driver-wizard-panel" data-wizard-step="1">
        @include('partials.driver-docs-upload-register')
    </div>

    <div class="driver-wizard-panel d-none" data-wizard-step="2">
        @include('partials.driver-core-fields', [
            'context'  => 'register',
            'user'     => null,
            'profile'  => null,
            'sections' => ['account'],
        ])
    </div>

    <div class="driver-wizard-panel d-none" data-wizard-step="3">
        @include('partials.driver-core-fields', [
            'context'  => 'register',
            'user'     => null,
            'profile'  => null,
            'sections' => ['vehicle'],
            'compact'  => true,
        ])
    </div>

    <div class="driver-wizard-panel d-none" data-wizard-step="4">
        @include('partials.driver-core-fields', [
            'context'  => 'register',
            'user'     => null,
            'profile'  => null,
            'sections' => ['bank'],
        ])
    </div>

    <div class="driver-wizard-panel d-none" data-wizard-step="5">
        <div class="register-section register-section--review">
            <div class="driver-review-summary" data-review-summary></div>
        </div>

        <div class="register-terms-check">
            <label class="register-terms-row" for="termsCheck">
                <input class="register-terms-input @error('terms') is-invalid @enderror" type="checkbox"
                       name="terms" value="1" id="termsCheck" {{ old('terms') ? 'checked' : '' }} required>
                <span class="register-terms-text">Đồng ý điều khoản {{ config('app.name') }}.</span>
            </label>
            <div class="invalid-feedback" data-client-feedback="terms">@error('terms'){{ $message }}@enderror</div>
        </div>
    </div>

    <div class="driver-wizard-nav">
        <button type="button" class="btn btn-outline-secondary driver-wizard-nav-btn" data-wizard-prev disabled>Quay lại</button>
        <button type="button" class="btn btn-primary driver-wizard-nav-btn" data-wizard-next>Tiếp theo</button>
        <button type="submit" class="btn btn-primary driver-wizard-nav-btn d-none" data-wizard-submit>Đăng ký</button>
    </div>
</div>
