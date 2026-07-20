{{--
  Một panel duyệt hồ sơ chờ duyệt (khách / tài xế).
  @var string $approveUrl
  @var string $rejectUrl
  @var string $prefix
  @var \App\Models\User $user
  @var string|null $phone
  @var string|null $frontUrl
  @var string|null $backUrl
  @var list<array{label: string, url?: ?string, side?: string, field?: string}> $extraPhotos
--}}
@php
    $phone = $phone ?? $user->phone;
    $frontUrl = $frontUrl ?? null;
    $backUrl = $backUrl ?? null;
    $extraPhotos = $extraPhotos ?? [];
    $rejectPanelId = $prefix.'-reject';
    $showReject = (bool) (old('rejection_reason') || $errors->has('rejection_reason'));

    $photoSlots = [
        ['side' => 'front', 'field' => 'photo_id_card', 'label' => 'CCCD mặt trước', 'url' => $frontUrl],
        ['side' => 'back', 'field' => 'photo_id_card_back', 'label' => 'CCCD mặt sau', 'url' => $backUrl],
    ];
    foreach ($extraPhotos as $i => $extra) {
        $side = $extra['side'] ?? ('extra-'.$i);
        $field = $extra['field'] ?? null;
        $photoSlots[] = [
            'side'  => $side,
            'field' => $field,
            'label' => $extra['label'] ?? $side,
            'url'   => $extra['url'] ?? null,
        ];
    }
@endphp

<div class="admin-pending-review">
    <div class="admin-pending-review__meta">
        <span class="status-pill status-pill--warning">Chờ duyệt</span>
        @if($phone)
            <span class="admin-pending-review__phone">{{ $phone }}</span>
        @endif
    </div>

    <div class="admin-pending-review__cccd" data-cccd-previews>
        @foreach($photoSlots as $slot)
            <figure class="admin-pending-review__shot"
                    data-cccd-preview="{{ $slot['side'] }}"
                    @if($slot['field']) data-photo-field="{{ $slot['field'] }}" @endif
                    data-src="{{ $slot['url'] }}">
                <figcaption>{{ $slot['label'] }}</figcaption>
                @if($slot['url'])
                    <div class="admin-pending-review__stage">
                        <a href="{{ $slot['url'] }}" data-photo-zoom class="admin-pending-review__img-link" title="Phóng to">
                            <img src="{{ $slot['url'] }}" alt="{{ $slot['label'] }}" data-cccd-img>
                        </a>
                        <div class="admin-pending-review__crop-layer d-none" data-cccd-crop-layer>
                            <div class="admin-pending-review__crop-box" data-cccd-crop-box style="left:55%;top:55%;width:40%;height:40%"></div>
                        </div>
                    </div>
                    <div class="admin-pending-review__tools">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cccd-rotate="-90" title="Xoay trái 90°">↶ Trái</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cccd-rotate="90" title="Xoay phải 90°">Phải ↷</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cccd-crop-toggle title="Kéo khung rồi bấm Xong cắt">Cắt</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-cccd-crop-clear title="Bỏ cắt / ảnh gốc">Bỏ cắt</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cccd-rotate-reset title="Ảnh gốc">Gốc</button>
                    </div>
                @else
                    <div class="admin-pending-review__empty">Chưa có ảnh</div>
                @endif
            </figure>
        @endforeach
    </div>

    <form method="POST"
          action="{{ $approveUrl }}"
          enctype="multipart/form-data"
          class="admin-approve-form admin-pending-review__form"
          data-admin-pending-form>
        @csrf
        @foreach($photoSlots as $slot)
            @if(! empty($slot['field']))
                <input type="file"
                       name="{{ $slot['field'] }}"
                       accept="image/jpeg,image/png,image/webp"
                       class="d-none"
                       data-idcard-file="{{ $slot['side'] }}"
                       tabindex="-1"
                       aria-hidden="true">
            @endif
        @endforeach

        @include('partials.admin-identity-scan', [
            'prefix' => $prefix,
            'user' => $user,
            'frontUrl' => $frontUrl,
            'showPreviewLink' => false,
            'readOnly' => true,
        ])

        <div id="{{ $rejectPanelId }}"
             class="admin-pending-review__reject {{ $showReject ? '' : 'd-none' }}"
             data-driver-reject-form>
            <label class="form-label small mb-1">
                Lý do từ chối <span class="text-danger">*</span>
            </label>
            @php
                $rejectPresets = [
                    'CCCD bị mờ / không đọc được',
                    'Bằng lái xe không nhìn rõ',
                    'Ảnh giấy tờ không khớp thông tin',
                    'Thiếu ảnh giấy tờ bắt buộc',
                    'Khác',
                ];
                $oldReason = old('rejection_reason', '');
                $matchedPreset = collect($rejectPresets)->first(fn ($p) => $p !== 'Khác' && $oldReason === $p);
                $isOther = $oldReason !== '' && $matchedPreset === null;
            @endphp
            <div class="d-flex flex-column gap-1 mb-2">
                @foreach($rejectPresets as $preset)
                    <label class="form-check small mb-0">
                        <input class="form-check-input" type="radio" name="rejection_reason_preset"
                               value="{{ $preset }}"
                               data-reject-preset
                               @checked(($matchedPreset === $preset) || ($preset === 'Khác' && $isOther))>
                        <span class="form-check-label">{{ $preset }}</span>
                    </label>
                @endforeach
            </div>
            <textarea name="rejection_reason"
                      id="{{ $prefix }}-reject-reason"
                      class="form-control form-control-sm @error('rejection_reason') is-invalid @enderror"
                      rows="3"
                      minlength="5"
                      maxlength="1000"
                      data-reject-reason-text
                      placeholder="Chọn lý do phía trên hoặc nhập chi tiết…">{{ $oldReason }}</textarea>
            @error('rejection_reason')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="admin-pending-review__actions">
            <button type="submit" class="btn btn-primary fw-semibold" data-approve-submit>
                Duyệt
            </button>
            <button type="button"
                    class="btn btn-outline-danger {{ $showReject ? 'd-none' : '' }}"
                    data-driver-reject-toggle
                    data-target="{{ $rejectPanelId }}"
                    aria-expanded="{{ $showReject ? 'true' : 'false' }}">
                Từ chối
            </button>
            <button type="submit"
                    class="btn btn-danger {{ $showReject ? '' : 'd-none' }}"
                    formaction="{{ $rejectUrl }}"
                    formnovalidate
                    data-reject-submit>
                Xác nhận từ chối
            </button>
            <button type="button"
                    class="btn btn-outline-secondary {{ $showReject ? '' : 'd-none' }}"
                    data-driver-reject-cancel
                    data-target="{{ $rejectPanelId }}">
                Hủy
            </button>
        </div>
    </form>
</div>
