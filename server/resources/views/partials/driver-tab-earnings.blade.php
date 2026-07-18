@php
/** @var array{day?: float|int, week?: float|int} $revenueStats */
$revenueStats = $revenueStats ?? ['day' => 0, 'week' => 0];
@endphp

<section class="driver-earnings-panel" aria-label="Thu nhập">
    <h2 class="driver-panel-title mb-3">Thu nhập</h2>

    <div class="driver-earnings-grid mb-3">
        <div class="driver-earnings-grid__item driver-earnings-grid__item--primary">
            <span class="driver-earnings-grid__label">Hôm nay</span>
            <strong class="driver-earnings-grid__value">{{ number_format($revenueStats['day'] ?? 0, 0, ',', '.') }} đ</strong>
        </div>
        <div class="driver-earnings-grid__item">
            <span class="driver-earnings-grid__label">Tuần này</span>
            <strong class="driver-earnings-grid__value">{{ number_format($revenueStats['week'] ?? 0, 0, ',', '.') }} đ</strong>
        </div>
    </div>

    <p class="driver-account-hint mb-0">
        Thu nhập tính theo các chuyến đã hoàn thành. Số dư ví và nạp tiền nằm ở menu <strong>Ví tài xế</strong>.
    </p>
</section>
