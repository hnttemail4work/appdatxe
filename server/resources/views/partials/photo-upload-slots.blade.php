{{--
  Slot ảnh đơn: hiện ảnh cũ → bấm chọn mới → submit form để lưu.
  Không dùng cho chọn nhiều (photo_vehicles[]…).

  @var list<array{field: string, label: string, url?: ?string, ratio?: string, required?: bool, badge?: ?string, locked?: bool}> $slots
  @var string|null $columnsClass  Bootstrap row cols
  @var bool $viewOnly
  @var bool $wrapManager  false khi đã nằm trong .driver-photo-manager
--}}
@php
    $slots = $slots ?? [];
    $viewOnly = (bool) ($viewOnly ?? false);
    $wrapManager = (bool) ($wrapManager ?? true);
    $columnsClass = $columnsClass ?? 'row g-2 row-cols-2 row-cols-md-3';
@endphp

@if($slots !== [])
@if($wrapManager)<div class="photo-upload-manager">@endif
    <div class="{{ $columnsClass }}">
        @foreach($slots as $slot)
            @php
                $field = $slot['field'] ?? '';
                $label = $slot['label'] ?? $field;
                $url = $slot['url'] ?? null;
                $ratio = $slot['ratio'] ?? 'landscape';
                $required = (bool) ($slot['required'] ?? false);
                $badge = $slot['badge'] ?? null;
                $locked = (bool) ($slot['locked'] ?? false);
                $hasUrl = is_string($url) && $url !== '';
            @endphp
            @if($field === '')
                @continue
            @endif
            <div class="col">
                <div class="photo-slot {{ $hasUrl ? 'has-photo' : 'is-empty' }} {{ $locked || $viewOnly ? 'is-locked' : '' }}"
                     data-photo-slot="{{ $field }}">
                    <div class="photo-slot-header">
                        <span class="photo-slot-title">
                            {{ $label }}@if($required && ! $viewOnly && ! $locked) <span class="text-danger">*</span>@endif
                        </span>
                        @if($badge)
                            <span class="photo-slot-badge">{{ $badge }}</span>
                        @endif
                    </div>
                    <div class="photo-slot-preview photo-ratio-{{ $ratio }}">
                        @if($hasUrl)
                            <a href="{{ $url }}" data-photo-zoom
                               class="photo-thumb photo-current-link" data-current-wrap
                               title="Bấm để phóng to">
                                <img src="{{ $url }}" alt="{{ $label }}" class="photo-current-img" data-current-img>
                                <span class="photo-zoom-hint">Phóng to</span>
                            </a>
                        @else
                            <div class="photo-placeholder" data-current-wrap><span>—</span></div>
                        @endif
                        @unless($viewOnly || $locked)
                            <div class="photo-thumb photo-new-wrap d-none" data-new-wrap>
                                <img src="" alt="Ảnh mới" class="photo-new-img" data-new-img>
                                <span class="photo-new-label">Mới</span>
                            </div>
                        @endunless
                    </div>
                    @unless($viewOnly || $locked)
                        <label class="photo-file-label">
                            <span data-file-label>{{ $hasUrl ? 'Thay ảnh' : 'Chọn ảnh' }}</span>
                            <input type="file"
                                   name="{{ $field }}"
                                   accept="image/jpeg,image/png,image/webp"
                                   class="photo-file-input @error($field) is-invalid @enderror"
                                   data-photo-input="{{ $field }}"
                                   @if($required && ! $hasUrl) required @endif>
                        </label>
                        @error($field)<div class="invalid-feedback d-block small">{{ $message }}</div>@enderror
                    @endunless
                </div>
            </div>
        @endforeach
    </div>
@if($wrapManager)</div>@endif
@endif
