@php
    use App\Support\DriverSoundPresets;

    $locale = $profile->locale ?? 'vi';
    $soundEnabled = $profile->sound_enabled ?? true;
    $soundPreset = DriverSoundPresets::normalize($profile->sound_preset ?? null);
    $soundOptions = DriverSoundPresets::options();
@endphp
<section class="driver-settings-panel" aria-label="Cài đặt">
    <h2 class="driver-panel-title mb-3" data-i18n="settings_title">Cài đặt</h2>

    <form method="POST" action="{{ route('driver.settings.update') }}" class="driver-settings-form" id="driver-settings-form">
        @csrf
        @method('PATCH')

        <div class="driver-settings-card mb-3">
            <h3 class="driver-settings-card__title" data-i18n="settings_language">Ngôn ngữ</h3>
            <div class="driver-settings-radios">
                <label class="driver-settings-radio">
                    <input type="radio" name="locale" value="vi" @checked($locale === 'vi')>
                    <span>Tiếng Việt</span>
                </label>
                <label class="driver-settings-radio">
                    <input type="radio" name="locale" value="en" @checked($locale === 'en')>
                    <span>English</span>
                </label>
            </div>
        </div>

        <div class="driver-settings-card mb-3">
            <h3 class="driver-settings-card__title">Thông báo đẩy (Chrome nền)</h3>
            <p class="driver-account-hint mb-2">
                Chuông trong tab chỉ kêu khi đang mở trang. Bật thông báo đẩy để nghe khi đang dùng app khác hoặc Chrome đang nền.
            </p>
            <p class="driver-account-hint mb-2" data-pwa-push-status>Đang kiểm tra…</p>
            <button type="button" class="btn btn-outline-warning w-100 fw-semibold" data-pwa-enable-push>
                Bật thông báo đẩy
            </button>
        </div>

        <div class="driver-settings-card mb-3">
            <h3 class="driver-settings-card__title" data-i18n="settings_sound">Âm thanh thông báo</h3>
            <p class="driver-account-hint mb-2" data-i18n="settings_sound_hint">
                Âm thanh khi có cuốc mới hoặc tin hộp thư (Tin tức / Thông báo). Đồng bộ với mặc định admin; bạn có thể đổi tone riêng.
            </p>

            <label class="driver-settings-toggle mb-3">
                <input type="checkbox" name="sound_enabled" value="1" @checked($soundEnabled)>
                <span data-i18n="settings_sound_enabled">Bật âm thanh thông báo</span>
            </label>

            <div class="driver-settings-sound-list" role="radiogroup" aria-label="Chọn âm thanh">
                @foreach($soundOptions as $key => $meta)
                    <label class="driver-settings-sound-item">
                        <input type="radio" name="sound_preset" value="{{ $key }}" @checked($soundPreset === $key)>
                        <span class="driver-settings-sound-item__copy">
                            <strong data-i18n="sound_{{ $key }}">{{ $locale === 'en' ? $meta['label_en'] : $meta['label'] }}</strong>
                        </span>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                data-sound-preview="{{ $key }}"
                                data-i18n="preview">Nghe thử</button>
                    </label>
                @endforeach
            </div>
        </div>

        <button type="submit" class="btn btn-warning w-100 fw-semibold" data-i18n="settings_save">Lưu cài đặt</button>
    </form>
</section>
