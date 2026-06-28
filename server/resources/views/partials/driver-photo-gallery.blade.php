{{-- Gallery ảnh hồ sơ — xem / duyệt --}}
@php
    $vehicleUrls = $driver->vehiclePhotoUrls();
    $groups = [
        'Chân dung' => [
            ['col' => 'photo_portrait', 'label' => 'Chân dung', 'portrait' => true],
        ],
        'CCCD / CMND' => [
            ['col' => 'photo_id_card', 'label' => 'Mặt trước'],
            ['col' => 'photo_id_card_back', 'label' => 'Mặt sau'],
        ],
        'Bằng lái' => [
            ['col' => 'photo_license_front', 'label' => 'Mặt trước'],
            ['col' => 'photo_license_back', 'label' => 'Mặt sau'],
        ],
    ];
@endphp

<div class="driver-photo-gallery-grid">
    @foreach($groups as $groupLabel => $items)
        <div class="driver-photo-gallery-group">
            <div class="group-label">{{ $groupLabel }}</div>
            <div class="d-flex gap-2 flex-wrap">
                @foreach($items as $item)
                    <div class="driver-photo-gallery-item {{ ! empty($item['portrait']) ? 'portrait' : '' }}">
                        @if($driver->{$item['col']})
                            <a href="{{ $driver->photoUrl($item['col']) }}" target="_blank" rel="noopener">
                                <img src="{{ $driver->photoUrl($item['col']) }}" alt="{{ $item['label'] }}">
                            </a>
                        @else
                            <div class="photo-placeholder-box">{{ $item['label'] }}</div>
                        @endif
                        @if(count($items) > 1)
                            <div class="item-label">{{ $item['label'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="driver-photo-gallery-group">
        <div class="group-label">Ảnh xe ({{ count($vehicleUrls) }})</div>
        @if(count($vehicleUrls) > 0)
            <div class="d-flex gap-2 flex-wrap">
                @foreach($vehicleUrls as $i => $url)
                    <div class="driver-photo-gallery-item">
                        <a href="{{ $url }}" target="_blank" rel="noopener">
                            <img src="{{ $url }}" alt="Xe {{ $i + 1 }}">
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <span class="text-muted small">Chưa có ảnh xe.</span>
        @endif
    </div>
</div>
