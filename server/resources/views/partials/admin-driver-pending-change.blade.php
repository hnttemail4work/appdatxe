@php
    /** @var \App\Models\DriverProfileChangeRequest|null $pendingChange */
    $pendingChange = $pendingChange ?? $driver->pendingChangeRequest;
@endphp
@if($pendingChange)
<div class="driver-notice driver-notice-warning mb-3" role="status">
    <strong>Yêu cầu cập nhật giấy tờ đang chờ duyệt</strong>

    @if(! empty($pendingChange->payload))
        @php
            $payloadLabels = [
                'vehicle_license_plate' => 'Biển số',
                'vehicle_type' => 'Loại xe',
                'bank_name' => 'Ngân hàng',
                'bank_account' => 'Số tài khoản',
            ];
            $vehicleLabels = \App\Support\DriverVehicleOptions::labels();
        @endphp
        <ul class="small mb-2">
            @foreach($pendingChange->payload as $key => $value)
                @php
                    $label = $payloadLabels[$key] ?? $key;
                    $display = is_scalar($value) ? (string) $value : json_encode($value);
                    if ($key === 'vehicle_type') {
                        $display = $vehicleLabels[$value] ?? $display;
                    }
                @endphp
                <li><strong>{{ $label }}:</strong> {{ $display }}</li>
            @endforeach
        </ul>
    @endif

    @php
        $previewFields = [
            'photo_portrait' => 'Chân dung',
            'photo_id_card' => 'CCCD trước',
            'photo_id_card_back' => 'CCCD sau',
            'photo_license_front' => 'GPLX trước',
            'photo_license_back' => 'GPLX sau',
        ];
    @endphp
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach($previewFields as $field => $label)
            @php $url = $pendingChange->photoUrl($field); @endphp
            @if($url)
                <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">{{ $label }}</a>
            @endif
        @endforeach
        @foreach($pendingChange->vehiclePhotoUrls() as $idx => $url)
            <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Ảnh xe #{{ $idx + 1 }}</a>
        @endforeach
    </div>

    <div class="d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.drivers.changes.approve', [$driver, $pendingChange]) }}">
            @csrf
            <button type="submit" class="btn btn-success btn-sm"
                    data-confirm="Duyệt cập nhật giấy tờ vào hồ sơ?"
                    data-confirm-ok="Duyệt">Duyệt</button>
        </form>
        <form method="POST" action="{{ route('admin.drivers.changes.reject', [$driver, $pendingChange]) }}">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm"
                    data-confirm="Xóa yêu cầu cập nhật giấy tờ này?"
                    data-confirm-variant="danger"
                    data-confirm-ok="Xóa">Xóa</button>
        </form>
    </div>
</div>
@endif
