@php
    use App\Support\DriverSoundPresets;

    $user = $user ?? auth()->user();
    $locale = $user->locale ?? 'vi';
    $soundEnabled = $user->sound_enabled ?? true;
    $soundPreset = DriverSoundPresets::normalize($user->sound_preset ?? null);
    $soundOptions = DriverSoundPresets::options();
@endphp
<section class="driver-settings-panel customer-settings-panel is-active" aria-label="Cài đặt">
    <form method="POST" action="{{ route('customer.settings.update') }}" class="driver-settings-form" id="customer-settings-form">
        @csrf
        @method('PATCH')

        <div class="driver-settings-card mb-3">
            <h3 class="driver-settings-card__title">Âm thanh</h3>

            <label class="driver-settings-toggle mb-3">
                <input type="checkbox" name="sound_enabled" value="1" @checked($soundEnabled)>
                <span>Bật âm thanh</span>
            </label>

            <div class="driver-settings-sound-list" role="radiogroup" aria-label="Chọn âm thanh">
                @foreach($soundOptions as $key => $meta)
                    <label class="driver-settings-sound-item">
                        <input type="radio" name="sound_preset" value="{{ $key }}" @checked($soundPreset === $key)>
                        <span class="driver-settings-sound-item__copy">
                            <strong>{{ $locale === 'en' ? $meta['label_en'] : $meta['label'] }}</strong>
                        </span>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                data-sound-preview="{{ $key }}">Nghe thử</button>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="driver-settings-card mb-3">
            <h3 class="driver-settings-card__title">Ngôn ngữ</h3>
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

        <button type="submit" class="btn btn-warning w-100 fw-semibold">Lưu cài đặt</button>
    </form>
</section>
