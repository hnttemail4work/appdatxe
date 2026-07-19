@php
    /** @var string $audience user|driver */
    $audience = $audience ?? 'user';
    $items = $audience === 'driver'
        ? [
            ['label' => 'Đăng nhập tài xế', 'code' => 'TX-DN', 'url' => route('driver.login')],
            ['label' => 'Đăng ký tài xế', 'code' => 'TX-DK', 'url' => route('driver.register')],
        ]
        : [
            ['label' => 'Đăng nhập khách', 'code' => 'KH-DN', 'url' => route('login')],
            ['label' => 'Đăng ký khách', 'code' => 'KH-DK', 'url' => route('customer.register')],
        ];
@endphp

<p class="text-muted small mb-3">
    QR chung của app (không phải mã giảm giá). Quét để mở đúng trang đăng nhập / đăng ký.
</p>

<div class="row g-3">
    @foreach($items as $item)
        <div class="col-md-6">
            <div class="console-panel h-100 mb-0">
                <div class="console-panel-body">
                    <h3 class="h6 fw-semibold mb-2">{{ $item['label'] }}</h3>
                    <button type="button" class="referral-qr-thumb" data-referral-qr-open
                            data-url="{{ $item['url'] }}" data-code="{{ $item['code'] }}"
                            title="Xem QR — {{ $item['label'] }}">
                        <span data-referral-qr data-url="{{ $item['url'] }}"></span>
                    </button>
                    <p class="small text-muted mt-2 mb-0 text-break">{{ $item['url'] }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>
