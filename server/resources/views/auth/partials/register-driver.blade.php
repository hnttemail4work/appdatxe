<div class="register-alert">
    Hồ sơ cần duyệt trước khi đăng nhập trong vòng từ 3 đến 5 ngày làm việc.
</div>
<div id="driver-wizard" data-driver-wizard>
    <div class="driver-wizard-progress mb-4">
        <div class="driver-wizard-steps">
            <button type="button" class="driver-wizard-step-btn active" data-goto-step="1">
                <span class="step-num">1</span> Giấy tờ
            </button>
            <button type="button" class="driver-wizard-step-btn" data-goto-step="2">
                <span class="step-num">2</span> Tài khoản
            </button>
            <button type="button" class="driver-wizard-step-btn" data-goto-step="3">
                <span class="step-num">3</span> Thông tin xe
            </button>
            <button type="button" class="driver-wizard-step-btn" data-goto-step="4">
                <span class="step-num">4</span> Ngân hàng
            </button>
            <button type="button" class="driver-wizard-step-btn" data-goto-step="5">
                <span class="step-num">5</span> Xác nhận
            </button>
        </div>
        <div class="progress mt-2" style="height:4px;">
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
        <div class="register-section">
            <div class="register-section-title"><span class="section-icon">✅</span> Kiểm tra lại hồ sơ</div>
            <dl class="row small mb-0 driver-review-summary" data-review-summary></dl>
        </div>

        <div class="form-check mt-3 register-terms-check">
            <input class="form-check-input register-terms-input @error('terms') is-invalid @enderror" type="checkbox"
                   name="terms" value="1" id="termsCheck" {{ old('terms') ? 'checked' : '' }} required>
            <label class="form-check-label small" for="termsCheck">
                Tôi đồng ý với điều khoản sử dụng và chính sách bảo mật của {{ config('app.name') }}.
            </label>
            @error('terms')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="driver-wizard-nav d-flex justify-content-between gap-2 mt-4">
        <button type="button" class="btn btn-outline-secondary" data-wizard-prev disabled>← Quay lại</button>
        <button type="button" class="btn btn-primary" data-wizard-next>Tiếp theo →</button>
        <button type="submit" class="btn btn-primary d-none" data-wizard-submit>Gửi hồ sơ đăng ký tài xế</button>
    </div>
</div>
