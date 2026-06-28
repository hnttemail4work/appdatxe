{{-- Tiến độ hoàn thiện hồ sơ tài xế --}}
@php
    $progress = $profile->sectionProgress();
    $percent = $profile->completenessPercent();
    $missing = $profile->missingFieldLabels();
    $missingOptional = $profile->missingOptionalFieldLabels();

    $sections = [
        'documents' => ['icon' => '📷', 'label' => 'Giấy tờ & ảnh'],
        'contact'   => ['icon' => '👤', 'label' => 'Liên hệ'],
        'vehicle'   => ['icon' => '🚗', 'label' => 'Thông tin xe'],
        'bank'      => ['icon' => '🏦', 'label' => 'Ngân hàng', 'optional' => true],
    ];
@endphp

<div class="card shadow-sm p-4 driver-completeness-card mt-4 border-0">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="text-muted mb-0">Hoàn thiện hồ sơ</h6>
        <strong class="text-primary">{{ $percent }}%</strong>
    </div>
    <div class="progress mb-3" style="height:8px;">
        <div class="progress-bar bg-primary" role="progressbar" style="width:{{ $percent }}%"></div>
    </div>
    <ul class="list-unstyled small mb-0 driver-checklist">
        @foreach($sections as $key => $meta)
            @php
                $sec = $progress[$key] ?? ['state' => 'empty', 'filled' => 0, 'total' => 0];
                $state = $sec['state'];
                $iconClass = match($state) {
                    'complete' => 'done',
                    'partial'  => 'partial',
                    default    => 'pending',
                };
                $iconChar = match($state) {
                    'complete' => '✓',
                    'partial'  => '◐',
                    default    => '○',
                };
                $textClass = match($state) {
                    'complete' => 'text-success',
                    'partial'  => 'text-warning',
                    default    => 'text-muted',
                };
            @endphp
            <li class="d-flex align-items-start gap-2 mb-2">
                <span class="check-icon {{ $iconClass }}">{{ $iconChar }}</span>
                <span class="flex-grow-1">
                    <span class="{{ $textClass }}">{{ $meta['icon'] }} {{ $meta['label'] }}</span>
                    @if(!empty($meta['optional']))
                        <span class="badge bg-light text-muted border ms-1" style="font-size:.6rem;">Tùy chọn</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
    @if($missing !== [])
        <div class="alert alert-light border small py-2 mt-3 mb-0">
            <strong class="d-block mb-1">Cần bổ sung:</strong>
            {{ implode(' · ', $missing) }}
        </div>
    @elseif($missingOptional !== [])
        <div class="alert alert-light border small py-2 mt-3 mb-0">
            {{ implode(' · ', $missingOptional) }}
        </div>
    @else
        <div class="alert alert-success small py-2 mt-3 mb-0">Hồ sơ đã đầy đủ.</div>
    @endif
</div>
