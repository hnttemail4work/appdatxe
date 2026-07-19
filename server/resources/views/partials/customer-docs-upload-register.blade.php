{{-- Upload CCCD — đăng ký / cập nhật khách (tái dùng tile UI đăng ký TX) --}}
@php
    $docFields = [
        ['name' => 'photo_id_card', 'label' => 'CCCD trước', 'required' => $idCardRequired ?? true],
        ['name' => 'photo_id_card_back', 'label' => 'CCCD sau', 'required' => $idCardRequired ?? true],
    ];
    $inputIdPrefix = $inputIdPrefix ?? 'cust';
@endphp
<div class="register-section register-section--docs" data-field-section="documents">
    <div class="register-doc-grid">
        @foreach($docFields as $doc)
        <div class="register-doc-item">
            <div class="register-file-field register-file-tile @error($doc['name']) is-invalid @enderror">
                <input type="file"
                       name="{{ $doc['name'] }}"
                       id="{{ $inputIdPrefix }}-{{ $doc['name'] }}"
                       accept="image/jpeg,image/png,image/webp"
                       class="register-file-input @error($doc['name']) is-invalid @enderror"
                       @if($doc['required']) required @endif>
                <button type="button" class="register-file-tile-btn" data-file-trigger aria-label="Chọn {{ $doc['label'] }}">
                    <img data-doc-preview class="register-file-tile-preview d-none" alt="">
                    <span class="register-file-tile-plus" aria-hidden="true">+</span>
                    <span class="register-file-tile-label">{{ $doc['label'] }}@if($doc['required']) <span class="text-danger">*</span>@endif</span>
                    <span class="register-file-name register-file-tile-status" data-file-name>Chưa chọn</span>
                </button>
            </div>
            <div class="invalid-feedback" data-client-feedback="{{ $doc['name'] }}">@error($doc['name']){{ $message }}@enderror</div>
        </div>
        @endforeach
    </div>
</div>
