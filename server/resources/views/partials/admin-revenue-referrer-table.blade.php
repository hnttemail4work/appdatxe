@php
/** @var \Illuminate\Support\Collection<int, array{referral: \App\Models\ReferralCode, trips: int, revenue: int, commission: int}> $referrerRows */
$referrerRows = $referrerRows ?? collect();
@endphp

<div class="console-panel-head px-0 pt-2">
    <div class="console-panel-head-accent">
        <h2>Theo giới thiệu</h2>
    </div>
</div>

@if($referrerRows->isNotEmpty())
    <div class="console-table-wrap mb-4">
        <table class="console-table admin-revenue-referrer-table">
            <thead>
                <tr>
                    <th>Mã GT</th>
                    <th>Người giới thiệu</th>
                    <th>SĐT</th>
                    <th>Chuyến HT</th>
                    <th>Doanh thu GT</th>
                    <th>% HH</th>
                    <th>Hoa hồng</th>
                </tr>
            </thead>
            <tbody>
                @foreach($referrerRows as $row)
                @php $ref = $row['referral']; @endphp
                <tr>
                    <td class="cell-primary"><span class="driver-meta-code">{{ $ref->code }}</span></td>
                    <td>{{ $ref->name }}</td>
                    <td class="cell-muted">{{ $ref->phone }}</td>
                    <td>{{ number_format($row['trips']) }}</td>
                    <td class="fw-semibold">{{ number_format($row['revenue'], 0, ',', '.') }} đ</td>
                    <td class="cell-muted">{{ rtrim(rtrim(number_format($ref->commissionPercent(), 1, '.', ''), '0'), '.') }}%</td>
                    <td class="fw-semibold text-success">{{ number_format($row['commission'], 0, ',', '.') }} đ</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
