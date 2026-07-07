@php
/** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $referralCodes */
/** @var array<int, array{trips: int, revenue: int, commission: int}> $referralCommissionStats */
$referralCommissionStats = $referralCommissionStats ?? [];
@endphp

<form method="POST" action="{{ route('admin.referrers.store') }}" class="console-form mb-4" id="referrer-create-form">
    @csrf
    <div class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="referrer-name">Tên người giới thiệu <span class="text-danger">*</span></label>
            <input type="text" name="name" id="referrer-name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-5">
            <label class="form-label" for="referrer-phone">Số điện thoại <span class="text-danger">*</span></label>
            <input type="tel" name="phone" id="referrer-phone" class="form-control @error('phone') is-invalid @enderror"
                   value="{{ old('phone') }}" required>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100 fw-semibold">Tạo mã</button>
        </div>
    </div>
</form>

<p class="text-muted small mb-3">
    <strong>Mã người giới thiệu</strong> (admin tạo): người giới thiệu hưởng <strong>{{ rtrim(rtrim(number_format(\App\Support\PlatformFees::referralCommissionFirstPercent(), 1, '.', ''), '0'), '.') }}%</strong> hoa hồng khi khách đặt qua mã — không giảm giá vé.<br>
    <strong>Mã từ đặt vé</strong>: khách nhận QR sau khi hoàn tất chuyến — bạn bè quét mã được giảm <strong>{{ rtrim(rtrim(number_format(\App\Support\PlatformFees::bookingQrDiscountPercent(), 1, '.', ''), '0'), '.') }}%</strong>.
</p>

@if($referralCodes->isEmpty())
    <div class="console-empty py-4"><p class="mb-0">Chưa có mã giới thiệu.</p></div>
@else
    @foreach($referralCodes as $ref)
        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
            @if($ref->status !== \App\Models\ReferralCode::STATUS_SUSPENDED)
                <form method="POST" action="{{ route('admin.referrers.hide', $ref) }}" id="hide-referrer-{{ $ref->id }}"
                      data-confirm="Tạm ngưng mã {{ $ref->code }} ({{ $ref->name }})?"
                      data-confirm-title="Tạm ngưng mã giới thiệu"
                      data-confirm-variant="danger"
                      data-confirm-ok="Tạm ngưng">
                    @csrf
                </form>
            @else
                <form method="POST" action="{{ route('admin.referrers.show', $ref) }}" id="show-referrer-{{ $ref->id }}">
                    @csrf
                </form>
            @endif
        @endif
        @if($ref->type === \App\Models\ReferralCode::TYPE_BOOKING_TEMP)
            <form method="POST" action="{{ route('admin.referralCodes.destroy', $ref) }}" id="delete-referral-{{ $ref->id }}"
                  data-confirm="Xóa mã {{ $ref->code }} ({{ $ref->name }})?"
                  data-confirm-title="Xóa mã giới thiệu"
                  data-confirm-variant="danger"
                  data-confirm-ok="Xóa">
                @csrf
                @method('DELETE')
            </form>
        @endif
    @endforeach
    <div class="console-table-wrap">
        <table class="console-table">
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>QR</th>
                    <th>Loại</th>
                    <th>Tên</th>
                    <th>SĐT</th>
                    <th>Trạng thái</th>
                    <th>% HH</th>
                    <th>Doanh thu GT</th>
                    <th>Hoa hồng GT</th>
                    <th>Giảm giá KH</th>
                    <th>Ngày hết hạn</th>
                    <th>Ngày tạo</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @foreach($referralCodes as $ref)
                <tr class="@if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER && $ref->isSuspended()) destination-row-hidden @endif">
                    <td class="cell-primary">
                        <span class="driver-meta-code">{{ $ref->code }}</span>
                    </td>
                    <td>
                        @if($ref->isUsable())
                            <button type="button" class="referral-qr-thumb" data-referral-qr-open
                                    data-url="{{ $ref->landingUrl() }}" data-code="{{ $ref->code }}"
                                    title="Xem QR — {{ $ref->code }}">
                                <span data-referral-qr data-url="{{ $ref->landingUrl() }}"></span>
                            </button>
                        @elseif($ref->type === \App\Models\ReferralCode::TYPE_BOOKING_TEMP && $ref->status === \App\Models\ReferralCode::STATUS_PENDING)
                            <span class="text-muted small" title="QR kích hoạt sau khi hoàn tất chuyến">Chờ HT</span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="cell-muted">{{ $ref->typeLabel() }}</td>
                    <td>{{ $ref->name }}</td>
                    <td class="cell-muted">{{ $ref->phone }}</td>
                    <td>
                        <span class="status-pill status-pill--{{ $ref->statusColor() }}">{{ $ref->statusLabel() }}</span>
                    </td>
                    <td class="cell-muted">
                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                            {{ number_format($ref->commissionPercent(), 1) }}%
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="cell-muted small">
                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                            @php
                                $refStats = $referralCommissionStats[$ref->id] ?? ['trips' => 0, 'revenue' => 0, 'commission' => 0];
                            @endphp
                            @if($refStats['revenue'] > 0)
                                <span class="fw-semibold text-body">{{ number_format($refStats['revenue'], 0, ',', '.') }} đ</span>
                                <span class="d-block text-muted">{{ $refStats['trips'] }} chuyến HT</span>
                            @else
                                <span class="text-muted">0 đ</span>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="cell-muted small">
                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                            @php
                                $refStats = $referralCommissionStats[$ref->id] ?? ['trips' => 0, 'revenue' => 0, 'commission' => 0];
                            @endphp
                            @if($refStats['commission'] > 0)
                                <span class="fw-semibold text-success">{{ number_format($refStats['commission'], 0, ',', '.') }} đ</span>
                            @else
                                <span class="text-muted">0 đ</span>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="cell-muted">
                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                            <span class="text-muted">—</span>
                        @else
                            {{ number_format($ref->customerDiscountPercent(), 1) }}%
                            <span class="d-block small">Mã QR vé</span>
                        @endif
                    </td>
                    <td class="cell-muted small">
                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                            <span class="text-muted">—</span>
                        @else
                            <span class="status-pill status-pill--{{ $ref->expiryColor() }}">{{ $ref->expiryLabel() }}</span>
                        @endif
                    </td>
                    <td class="cell-muted small">{{ $ref->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-end">
                        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
                            <form method="POST" action="{{ route('admin.referrers.update', $ref) }}" class="d-inline-flex flex-wrap gap-1 align-items-center justify-content-end mb-1">
                                @csrf
                                @method('PATCH')
                                <input type="number" name="customer_discount_percent" class="form-control form-control-sm" style="width:4.5rem"
                                       min="0" max="100" step="0.1" value="{{ number_format($ref->customerDiscountPercent(), 1, '.', '') }}" title="% giảm giá khách" aria-label="Giảm giá %">
                                <input type="number" name="commission_percent" class="form-control form-control-sm" style="width:4.5rem"
                                       min="0" max="100" step="0.1" value="{{ number_format($ref->commissionPercent(), 1, '.', '') }}" title="% hoa hồng" aria-label="Hoa hồng %">
                                <button type="submit" class="btn btn-outline-primary btn-sm">Lưu</button>
                            </form>
                            @if($ref->isSuspended())
                                <button type="submit" class="btn btn-outline-primary btn-sm" form="show-referrer-{{ $ref->id }}">Sử dụng</button>
                            @else
                                <button type="submit" class="btn btn-outline-danger btn-sm" form="hide-referrer-{{ $ref->id }}">Tạm ngưng</button>
                            @endif
                        @else
                            <button type="submit" class="btn btn-outline-danger btn-sm" form="delete-referral-{{ $ref->id }}">Xóa</button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @include('partials.pagination', ['paginator' => $referralCodes])
@endif
@include('partials.referral-qr-modal')
