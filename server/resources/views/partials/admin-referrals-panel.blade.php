@php
/** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $referralCodes */
/** @var \Illuminate\Support\Collection<int, \App\Models\DriverProfile> $assignableDrivers */
$assignableDrivers = $assignableDrivers ?? collect();
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

@if($referralCodes->isEmpty())
    <div class="console-empty py-4"><p class="mb-0">Chưa có mã giới thiệu.</p></div>
@else
    @foreach($referralCodes as $ref)
        @if($ref->type === \App\Models\ReferralCode::TYPE_REFERRER)
            @if($ref->status !== \App\Models\ReferralCode::STATUS_SUSPENDED)
                <form method="POST" action="{{ route('admin.referrers.hide', $ref) }}" id="hide-referrer-{{ $ref->id }}"
                      data-confirm="Tạm ngưng mã {{ $ref->code }}?"
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
                  data-confirm="Xóa mã {{ $ref->code }}?"
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
                    <th>Giới thiệu</th>
                    <th>Gán TX</th>
                    <th>Giảm giá</th>
                    <th>Ngày tạo</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @foreach($referralCodes as $ref)
                @php
                    $isAdminReferrer = $ref->type === \App\Models\ReferralCode::TYPE_REFERRER;
                @endphp
                <tr class="@if($isAdminReferrer && $ref->isSuspended()) destination-row-hidden @endif">
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
                    <td class="cell-muted">
                        @if($isAdminReferrer)
                            Người GT
                        @elseif($ref->type === \App\Models\ReferralCode::TYPE_BOOKING_TEMP)
                            Khách
                        @else
                            {{ $ref->typeLabel() }}
                        @endif
                    </td>
                    <td class="cell-muted">
                        @if(filled($ref->phone))
                            <span @if(filled($ref->name)) title="{{ $ref->name }}" @endif>{{ $ref->phone }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="cell-muted small">
                        @if($ref->isAssignedCommissionCode())
                            Đã gán
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="cell-muted">{{ $ref->listPercentLabel() }}</td>
                    <td class="cell-muted small">{{ $ref->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-end">
                        @if($isAdminReferrer)
                            <form method="POST" action="{{ route('admin.referrers.update', $ref) }}" class="d-inline-flex flex-wrap gap-1 align-items-center justify-content-end mb-1">
                                @csrf
                                @method('PATCH')
                                <input type="number" name="commission_percent" class="form-control form-control-sm" style="width:4.5rem"
                                       min="0" max="100" step="0.1" value="{{ number_format($ref->commissionPercent(), 1, '.', '') }}"
                                       title="% giảm giá / hoa hồng" aria-label="Giảm giá %">
                                <input type="hidden" name="customer_discount_percent" value="{{ number_format($ref->customerDiscountPercent(), 1, '.', '') }}">
                                <button type="submit" class="btn btn-outline-primary btn-sm">Lưu %</button>
                            </form>
                            @if($ref->canAssignToDriver())
                                <form method="POST" action="{{ route('admin.referrers.assign', $ref) }}" class="d-inline-flex flex-wrap gap-1 align-items-center justify-content-end mb-1">
                                    @csrf
                                    <select name="driver_profile_id" class="form-select form-select-sm" style="min-width:9rem;max-width:12rem" required>
                                        <option value="">Gán tài xế…</option>
                                        @foreach($assignableDrivers as $driver)
                                            <option value="{{ $driver->id }}" @selected((int) $ref->assigned_driver_profile_id === (int) $driver->id)>
                                                {{ $driver->driver_code ?: ('TX#'.$driver->id) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-outline-success btn-sm"
                                            data-confirm="Gán mã {{ $ref->code }} cho tài xế đã chọn?"
                                            data-confirm-title="Gán mã hoa hồng"
                                            data-confirm-ok="Gán">Gán</button>
                                </form>
                            @endif
                            @if($ref->isAssignedCommissionCode())
                                <form method="POST" action="{{ route('admin.referrers.revoke', $ref) }}" class="d-inline mb-1">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning btn-sm"
                                            data-confirm="Thu hồi mã {{ $ref->code }} khỏi tài xế?"
                                            data-confirm-title="Thu hồi mã"
                                            data-confirm-variant="warning"
                                            data-confirm-ok="Thu hồi">Thu hồi</button>
                                </form>
                            @endif
                            @if($ref->isSuspended())
                                <button type="submit" class="btn btn-outline-primary btn-sm" form="show-referrer-{{ $ref->id }}">Dùng</button>
                            @else
                                <button type="submit" class="btn btn-outline-danger btn-sm" form="hide-referrer-{{ $ref->id }}">Ngưng</button>
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
