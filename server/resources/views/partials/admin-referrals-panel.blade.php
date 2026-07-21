@php
/** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $referralCodes */
/** @var \Illuminate\Support\Collection<int, \App\Models\DriverProfile> $assignableDrivers */
$assignableDrivers = $assignableDrivers ?? collect();
$createMode = old('mode', 'commission');
@endphp

<form method="POST" action="{{ route('admin.referrers.store') }}" class="console-form mb-4" id="referrer-create-form">
    @csrf
    <div class="mb-3">
        <span class="form-label d-block mb-2">Loại mã QR</span>
        <div class="d-flex flex-wrap gap-3">
            <label class="form-check">
                <input class="form-check-input" type="radio" name="mode" value="commission" @checked($createMode === 'commission')>
                <span class="form-check-label">Hoa hồng (%)</span>
            </label>
            <label class="form-check">
                <input class="form-check-input" type="radio" name="mode" value="driver" @checked($createMode === 'driver')>
                <span class="form-check-label">Gán tài xế (Khách của tôi)</span>
            </label>
        </div>
        @error('mode')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="row g-3 align-items-end" data-qr-mode-panel="commission">
        <div class="col-md-4">
            <label class="form-label" for="referrer-name">Tên người giới thiệu <span class="text-danger">*</span></label>
            <input type="text" name="name" id="referrer-name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name') }}" @if($createMode === 'commission') required @endif>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="referrer-phone">Số điện thoại <span class="text-danger">*</span></label>
            <input type="tel" name="phone" id="referrer-phone" class="form-control @error('phone') is-invalid @enderror"
                   value="{{ old('phone') }}" @if($createMode === 'commission') required @endif>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
            <label class="form-label" for="referrer-commission">Hoa hồng %</label>
            <input type="number" name="commission_percent" id="referrer-commission"
                   class="form-control @error('commission_percent') is-invalid @enderror"
                   min="0" max="100" step="0.1"
                   value="{{ old('commission_percent', number_format(($pricingSettings['referral_commission_first'] ?? 0), 1, '.', '')) }}">
            @error('commission_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="row g-3 align-items-end mt-1" data-qr-mode-panel="driver">
        <div class="col-md-6">
            <label class="form-label" for="referrer-driver">Tài xế <span class="text-danger">*</span></label>
            <select name="driver_profile_id" id="referrer-driver"
                    class="form-select @error('driver_profile_id') is-invalid @enderror"
                    @if($createMode === 'driver') required @endif>
                <option value="">Chọn tài xế…</option>
                @foreach($assignableDrivers as $driver)
                    <option value="{{ $driver->id }}" @selected((int) old('driver_profile_id') === (int) $driver->id)>
                        {{ $driver->driver_code ?: ('TX#'.$driver->id) }}
                        @if($driver->user?->preferredDisplayName())
                            — {{ $driver->user->preferredDisplayName() }}
                        @endif
                    </option>
                @endforeach
            </select>
            @error('driver_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="small text-muted mb-0 mt-1">Khách quét QR và hoàn thành chuyến sẽ vào «Khách của tôi» của tài xế này.</p>
        </div>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary fw-semibold px-4">Tạo mã QR</button>
    </div>
</form>

@if($referralCodes->isEmpty())
    <div class="console-empty py-4"><p class="mb-0">Chưa có mã QR.</p></div>
@else
    @foreach($referralCodes as $ref)
        @if($ref->status !== \App\Models\ReferralCode::STATUS_SUSPENDED)
            <form method="POST" action="{{ route('admin.referrers.hide', $ref) }}" id="hide-referrer-{{ $ref->id }}"
                  data-confirm="Tạm ngưng mã {{ $ref->code }}?"
                  data-confirm-title="Tạm ngưng mã QR"
                  data-confirm-variant="danger"
                  data-confirm-ok="Tạm ngưng">
                @csrf
            </form>
        @else
            <form method="POST" action="{{ route('admin.referrers.show', $ref) }}" id="show-referrer-{{ $ref->id }}">
                @csrf
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
                    <th>Giới thiệu / TX</th>
                    <th>Hoa hồng</th>
                    <th>Ngày tạo</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @foreach($referralCodes as $ref)
                @php
                    $isDriverQr = $ref->isAssignedCommissionCode() || (float) $ref->commissionPercent() <= 0;
                    $assignedDriver = $ref->assignedDriverProfile;
                @endphp
                <tr class="@if($ref->isSuspended()) destination-row-hidden @endif">
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
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="cell-muted">
                        @if($ref->isAssignedCommissionCode() || (float) $ref->commissionPercent() <= 0)
                            Khách của tôi
                        @else
                            Hoa hồng
                        @endif
                    </td>
                    <td class="cell-muted">
                        @if($assignedDriver)
                            <strong>{{ $assignedDriver->driver_code ?: ('TX#'.$assignedDriver->id) }}</strong>
                            @if($assignedDriver->user?->preferredDisplayName())
                                <span class="d-block small text-muted">{{ $assignedDriver->user->preferredDisplayName() }}</span>
                            @endif
                        @elseif(filled($ref->phone))
                            <span @if(filled($ref->name)) title="{{ $ref->name }}" @endif>{{ $ref->phone }}</span>
                            @if(filled($ref->name))
                                <span class="d-block small text-muted">{{ $ref->name }}</span>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="cell-muted">
                        @if((float) $ref->commissionPercent() > 0)
                            {{ $ref->listPercentLabel() }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="cell-muted small">{{ $ref->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-end">
                        @if((float) $ref->commissionPercent() > 0 && ! $ref->isAssignedCommissionCode())
                            <form method="POST" action="{{ route('admin.referrers.update', $ref) }}" class="d-inline-flex flex-wrap gap-1 align-items-center justify-content-end mb-1">
                                @csrf
                                @method('PATCH')
                                <input type="number" name="commission_percent" class="form-control form-control-sm" style="width:4.5rem"
                                       min="0.1" max="100" step="0.1" value="{{ number_format($ref->commissionPercent(), 1, '.', '') }}"
                                       title="% hoa hồng" aria-label="Hoa hồng %">
                                <input type="hidden" name="customer_discount_percent" value="0">
                                <button type="submit" class="btn btn-outline-primary btn-sm">Lưu %</button>
                            </form>
                        @endif
                        @if($ref->canAssignToDriver() && (float) $ref->commissionPercent() <= 0)
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
                                        data-confirm-title="Gán mã Khách của tôi"
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
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @include('partials.pagination', ['paginator' => $referralCodes])
@endif

<script>
(function () {
    var form = document.getElementById('referrer-create-form');
    if (!form) return;
    function syncMode() {
        var mode = (form.querySelector('input[name="mode"]:checked') || {}).value || 'commission';
        form.querySelectorAll('[data-qr-mode-panel]').forEach(function (panel) {
            var on = panel.getAttribute('data-qr-mode-panel') === mode;
            panel.classList.toggle('d-none', !on);
            panel.querySelectorAll('input, select').forEach(function (el) {
                if (el.name === 'mode') return;
                el.disabled = !on;
                if (el.hasAttribute('data-keep-required')) return;
                if (on) {
                    if (el.dataset.wasRequired === '1') el.required = true;
                } else {
                    if (el.required) el.dataset.wasRequired = '1';
                    el.required = false;
                }
            });
        });
    }
    form.querySelectorAll('input[name="mode"]').forEach(function (radio) {
        radio.addEventListener('change', syncMode);
    });
    form.querySelectorAll('[data-qr-mode-panel] input[required], [data-qr-mode-panel] select[required]').forEach(function (el) {
        el.dataset.wasRequired = '1';
    });
    syncMode();
})();
</script>
