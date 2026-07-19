@php
    /** @var \App\Models\CustomerProfileChangeRequest|null $pendingChange */
    $pendingChange = $pendingChange ?? null;
@endphp
@if($pendingChange)
<div class="alert alert-warning mb-3" role="status">
    <strong>Yêu cầu cập nhật hồ sơ đang chờ duyệt</strong>
    <div class="small mb-2">#{{ $pendingChange->id }} · gửi {{ optional($pendingChange->created_at)->format('d/m/Y H:i') }}</div>

    @if(! empty($pendingChange->payload))
        @php
            $payloadLabels = [
                'name' => 'Họ tên',
                'email' => 'Email',
                'gender' => 'Giới tính',
                'date_of_birth' => 'Ngày sinh',
                'address' => 'Địa chỉ',
                'id_number' => 'Số CCCD',
            ];
        @endphp
        <ul class="small mb-2">
            @foreach($pendingChange->payload as $key => $value)
                @php
                    $label = $payloadLabels[$key] ?? $key;
                    $display = is_scalar($value) ? (string) $value : json_encode($value);
                    if ($key === 'gender') {
                        $display = $value === 'female' ? 'Nữ' : ($value === 'male' ? 'Nam' : $display);
                    }
                    if ($key === 'date_of_birth' && $display !== '') {
                        try {
                            $display = \Illuminate\Support\Carbon::parse($display)->format('d/m/Y');
                        } catch (\Throwable) {
                        }
                    }
                @endphp
                <li><strong>{{ $label }}:</strong> {{ $display }}</li>
            @endforeach
        </ul>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach(['photo_id_card' => 'CCCD trước', 'photo_id_card_back' => 'CCCD sau'] as $field => $label)
            @php $url = $pendingChange->photoUrl($field); @endphp
            @if($url)
                <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">{{ $label }} (mới)</a>
            @endif
        @endforeach
    </div>

    <div class="d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.users.changes.approve', $pendingChange) }}">
            @csrf
            <button type="submit" class="btn btn-success btn-sm"
                    data-confirm="Duyệt cập nhật vào hồ sơ khách?"
                    data-confirm-ok="Duyệt">Duyệt</button>
        </form>
        <form method="POST" action="{{ route('admin.users.changes.reject', $pendingChange) }}">
            @csrf
            <input type="hidden" name="admin_note" value="">
            <button type="submit" class="btn btn-outline-danger btn-sm"
                    data-confirm="Xóa yêu cầu cập nhật hồ sơ này?"
                    data-confirm-variant="danger"
                    data-confirm-ok="Xóa">Xóa</button>
        </form>
    </div>
</div>
@endif
