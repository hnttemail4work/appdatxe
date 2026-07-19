{{--
  Ô đồng ý điều khoản — đăng ký khách / tài xế.
  @var string $checkboxId
  @var string|null $brandName
--}}
@php
    use App\Support\AppBrandingSettings;

    $checkboxId = $checkboxId ?? 'termsCheck';
    $brandName = $brandName ?? AppBrandingSettings::appName();
    $checked = (bool) old('terms');
@endphp
<div class="auth-terms {{ $errors->has('terms') ? 'auth-terms--invalid' : '' }}" data-auth-terms>
    <label class="auth-terms__control" for="{{ $checkboxId }}">
        <input class="auth-terms__check @error('terms') is-invalid @enderror"
               type="checkbox"
               name="terms"
               value="1"
               id="{{ $checkboxId }}"
               {{ $checked ? 'checked' : '' }}
               required>
        <span class="auth-terms__box" aria-hidden="true"></span>
        <span class="auth-terms__copy">
            <span class="auth-terms__title">
                Tôi đã đọc và đồng ý với
                <span class="auth-terms__linkish">Điều khoản sử dụng</span>
                của {{ $brandName }}
            </span>
            <span class="auth-terms__hint">
                Ảnh CCCD chỉ dùng để xác minh danh tính khi đặt xe và hỗ trợ an toàn chuyến đi.
            </span>
        </span>
    </label>

    <details class="auth-terms__details">
        <summary>Xem tóm tắt điều khoản</summary>
        <ul class="auth-terms__list">
            <li>Thông tin cá nhân và giấy tờ được bảo mật, không chia sẻ cho bên thứ ba ngoài mục đích vận hành.</li>
            <li>Bạn chịu trách nhiệm cung cấp thông tin đúng; hồ sơ sẽ được kiểm duyệt trước khi dùng dịch vụ.</li>
            <li>Khi đặt chuyến, bạn tuân thủ lịch đón và quy định an toàn của nền tảng.</li>
        </ul>
    </details>

    <div class="invalid-feedback auth-terms__error" data-client-feedback="terms">
        @error('terms'){{ $message }}@enderror
    </div>
</div>
