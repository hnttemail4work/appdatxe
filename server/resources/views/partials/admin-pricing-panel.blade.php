@php
    $pricingSettings = $pricingSettings ?? \App\Support\PricingConfig::forAdmin();
    $vehicleTypes = $vehicleTypes ?? collect();
    $surchargeRules = $surchargeRules ?? collect();
    $pricingTolls = $pricingTolls ?? collect();
    $dowLabels = [0 => 'CN', 1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7'];
@endphp

<form method="POST" action="{{ route('admin.pricingSettings.update') }}" class="console-form mb-5">
    @csrf
    <h3 class="h6 fw-semibold mb-2">Đơn giá km & làm tròn</h3>
    <p class="text-muted small mb-3">Giá gốc theo km, rồi nhân hệ số loại xe, cộng phụ phí/thu phí, cuối cùng áp % giảm giá QR (cấu hình ở tab QR).</p>
    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Giá / km (≤ 100 km)</label>
            <div class="input-group">
                <input type="number" name="km_rate_under_100" class="form-control" min="0" step="500"
                       value="{{ old('km_rate_under_100', $pricingSettings['km_rate_under_100']) }}" required>
                <span class="input-group-text">đ</span>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Giá / km (&gt; 100 km)</label>
            <div class="input-group">
                <input type="number" name="km_rate_over_100" class="form-control" min="0" step="500"
                       value="{{ old('km_rate_over_100', $pricingSettings['km_rate_over_100']) }}" required>
                <span class="input-group-text">đ</span>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Flat nội tỉnh tối đa (km)</label>
            <input type="number" name="intra_flat_max_km" class="form-control" min="0" max="50"
                   value="{{ old('intra_flat_max_km', $pricingSettings['intra_flat_max_km']) }}" required>
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Giá flat nội tỉnh</label>
            <div class="input-group">
                <input type="number" name="intra_flat_price" class="form-control" min="0" step="1000"
                       value="{{ old('intra_flat_price', $pricingSettings['intra_flat_price']) }}" required>
                <span class="input-group-text">đ</span>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Đơn vị làm tròn</label>
            <div class="input-group">
                <input type="number" name="rounding_unit" class="form-control" min="1000" step="1000"
                       value="{{ old('rounding_unit', $pricingSettings['rounding_unit']) }}" required>
                <span class="input-group-text">đ</span>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label">Hoa hồng app (%)</label>
            <div class="input-group">
                <input type="number" name="app_commission" class="form-control" min="0" max="100" step="0.1"
                       value="{{ old('app_commission', $pricingSettings['app_commission']) }}" required>
                <span class="input-group-text">%</span>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="rain_surcharge_enabled" value="1" id="rain-surcharge-enabled"
                       @checked(old('rain_surcharge_enabled', $pricingSettings['rain_surcharge_enabled']))>
                <label class="form-check-label" for="rain-surcharge-enabled">Bật phụ phí mưa</label>
            </div>
        </div>
        <div class="col-12">
            <p class="text-muted small mb-0">Rule giảm giá QR / hoa hồng GT → tab <a href="{{ route('admin.referrals', ['tab' => 'rules']) }}">QR → Rule giảm giá</a>.</p>
        </div>
    </div>
    <button class="btn btn-primary px-4 fw-semibold mt-3">Lưu cấu hình gốc</button>
</form>

<hr class="my-4">

<h3 class="h6 fw-semibold mb-2">Loại xe & hệ số giá</h3>
<p class="text-muted small mb-3">Baseline khuyến nghị: <strong>sedan_4 = 100%</strong>. Thêm loại mới bằng key viết thường (vd. <code>suv_5</code>). Ảnh loại xe hiện trên màn chọn xe khi khách đặt.</p>
<div class="d-flex flex-column gap-2 mb-3">
    @foreach($vehicleTypes as $vt)
        <form method="POST"
              action="{{ route('admin.vehicleTypes.update', $vt) }}"
              enctype="multipart/form-data"
              class="border rounded p-2">
            @csrf
            @method('PATCH')
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <span class="form-label d-block small text-muted">Ảnh</span>
                    <div class="d-flex align-items-center gap-2">
                        @if($vt->imageUrl())
                            <img src="{{ $vt->imageUrl() }}"
                                 alt="{{ $vt->label }}"
                                 width="48"
                                 height="48"
                                 class="rounded border"
                                 style="object-fit:cover;background:#111;">
                        @else
                            <span class="d-inline-flex align-items-center justify-content-center rounded border text-muted"
                                  style="width:48px;height:48px;font-size:.7rem;">SVG</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-2"><span class="form-label d-block small text-muted">Key</span><code>{{ $vt->key }}</code></div>
                <div class="col-md-2"><label class="form-label small">Tên</label><input type="text" name="label" class="form-control form-control-sm" value="{{ $vt->label }}" required></div>
                <div class="col-md-1"><label class="form-label small">Chỗ</label><input type="number" name="seats" class="form-control form-control-sm" min="1" max="60" value="{{ $vt->seats }}"></div>
                <div class="col-md-1"><label class="form-label small">Family</label><input type="text" name="family" class="form-control form-control-sm" value="{{ $vt->family }}"></div>
                <div class="col-md-1"><label class="form-label small">%</label><input type="number" name="price_percent" class="form-control form-control-sm" min="50" max="500" step="0.1" value="{{ $vt->price_percent }}" required></div>
                <div class="col-md-1"><label class="form-label small">TT</label><input type="number" name="sort_order" class="form-control form-control-sm" value="{{ $vt->sort_order }}"></div>
                <div class="col-md-1"><label class="form-label small">Bật</label><div><input type="checkbox" name="is_active" value="1" @checked($vt->is_active)></div></div>
                <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100">Lưu</button></div>
            </div>
            <div class="row g-2 align-items-center mt-1">
                <div class="col-md-6">
                    <label class="form-label small mb-0">Đổi ảnh (JPG/PNG/WebP, tối đa 5MB)</label>
                    <input type="file" name="image" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/gif">
                </div>
                @if($vt->image_path)
                    <div class="col-md-3">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="vt-remove-{{ $vt->id }}">
                            <label class="form-check-label small" for="vt-remove-{{ $vt->id }}">Xóa ảnh (dùng icon)</label>
                        </div>
                    </div>
                @endif
            </div>
        </form>
    @endforeach
</div>
<form method="POST"
      action="{{ route('admin.vehicleTypes.store') }}"
      enctype="multipart/form-data"
      class="row g-2 align-items-end mb-4 border rounded p-2">
    @csrf
    <div class="col-md-2"><label class="form-label">Key</label><input name="key" class="form-control form-control-sm" pattern="[a-z0-9_]+" required placeholder="suv_5"></div>
    <div class="col-md-2"><label class="form-label">Tên</label><input name="label" class="form-control form-control-sm" required></div>
    <div class="col-md-1"><label class="form-label">Chỗ</label><input type="number" name="seats" class="form-control form-control-sm" min="1" max="60"></div>
    <div class="col-md-1"><label class="form-label">Family</label><input name="family" class="form-control form-control-sm" value="other"></div>
    <div class="col-md-1"><label class="form-label">%</label><input type="number" name="price_percent" class="form-control form-control-sm" value="100" step="0.1" required></div>
    <div class="col-md-1"><label class="form-label">TT</label><input type="number" name="sort_order" class="form-control form-control-sm" value="{{ $vehicleTypes->count() }}"></div>
    <div class="col-md-2"><label class="form-label">Ảnh</label><input type="file" name="image" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/gif"></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Thêm loại xe</button></div>
    <input type="hidden" name="is_active" value="1">
</form>

<hr class="my-4">

<h3 class="h6 fw-semibold mb-2">Phụ phí (lễ / cao điểm / mưa)</h3>
<p class="text-muted small mb-3">Thứ tự cộng: holiday → peak → rain. Mưa chỉ áp khi bật công tắc phía trên.</p>
<div class="console-table-wrap mb-3">
    <table class="console-table">
        <thead>
            <tr>
                <th>Loại</th>
                <th>Tên</th>
                <th>Mode</th>
                <th>Giá trị</th>
                <th>Chi tiết</th>
                <th>Bật</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($surchargeRules as $rule)
                @php $p = $rule->payload ?? []; @endphp
                <tr>
                    <td>{{ $rule->type }}</td>
                    <td>{{ $rule->name }}</td>
                    <td>{{ $rule->mode }}</td>
                    <td>{{ $rule->mode === 'percent' ? rtrim(rtrim(number_format($rule->value, 2, '.', ''), '0'), '.').'%' : number_format($rule->value, 0, ',', '.').' đ' }}</td>
                    <td class="small text-muted">
                        @if($rule->type === 'holiday')
                            {{ $p['starts_on'] ?? '—' }} → {{ $p['ends_on'] ?? '—' }}
                        @elseif($rule->type === 'peak')
                            @foreach(($p['days_of_week'] ?? []) as $d){{ $dowLabels[$d] ?? $d }} @endforeach
                            {{ $p['start_time'] ?? '' }}–{{ $p['end_time'] ?? '' }}
                        @else
                            {{ ($p['start_time'] ?? null) ? ($p['start_time'].'–'.($p['end_time'] ?? '')) : 'Khi bật mưa' }}
                        @endif
                    </td>
                    <td>{{ $rule->is_active ? 'Có' : 'Không' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.pricingSurcharges.destroy', $rule) }}" onsubmit="return confirm('Xóa quy tắc này?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<form method="POST" action="{{ route('admin.pricingSurcharges.store') }}" class="row g-2 align-items-end mb-4">
    @csrf
    <div class="col-md-2">
        <label class="form-label">Loại</label>
        <select name="type" class="form-select form-select-sm" required>
            <option value="holiday">Lễ/Tết</option>
            <option value="peak">Cao điểm</option>
            <option value="rain">Mưa</option>
        </select>
    </div>
    <div class="col-md-2"><label class="form-label">Tên</label><input name="name" class="form-control form-control-sm" required></div>
    <div class="col-md-2">
        <label class="form-label">Mode</label>
        <select name="mode" class="form-select form-select-sm" required>
            <option value="percent">%</option>
            <option value="fixed">Số tiền</option>
        </select>
    </div>
    <div class="col-md-1"><label class="form-label">Giá trị</label><input type="number" name="value" class="form-control form-control-sm" min="0" step="0.1" required></div>
    <div class="col-md-2"><label class="form-label">Từ ngày</label><input type="date" name="starts_on" class="form-control form-control-sm"></div>
    <div class="col-md-2"><label class="form-label">Đến ngày</label><input type="date" name="ends_on" class="form-control form-control-sm"></div>
    <div class="col-md-1"><label class="form-label">Giờ từ</label><input type="time" name="start_time" class="form-control form-control-sm"></div>
    <div class="col-md-1"><label class="form-label">Giờ đến</label><input type="time" name="end_time" class="form-control form-control-sm"></div>
    <div class="col-12">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-muted">Ngày trong tuần (cao điểm):</span>
            @foreach($dowLabels as $num => $label)
                <label class="form-check form-check-inline small mb-0">
                    <input class="form-check-input" type="checkbox" name="days_of_week[]" value="{{ $num }}"> {{ $label }}
                </label>
            @endforeach
            <input type="hidden" name="is_active" value="1">
            <button class="btn btn-sm btn-primary ms-auto">Thêm phụ phí</button>
        </div>
    </div>
</form>

<hr class="my-4">

<h3 class="h6 fw-semibold mb-2">Thu phí theo tuyến</h3>
<p class="text-muted small mb-3">Số tiền cố định 1 chiều; chiều ngược dùng chung nếu chưa có dòng riêng.</p>
<div class="d-flex flex-column gap-2 mb-3">
    @foreach($pricingTolls as $toll)
        <div class="row g-2 align-items-end border rounded p-2">
            <form method="POST" action="{{ route('admin.pricingTolls.update', $toll) }}" class="row g-2 align-items-end col-12">
                @csrf
                @method('PATCH')
                <div class="col-md-3"><label class="form-label small">Từ</label><input name="from_province" class="form-control form-control-sm" value="{{ $toll->from_province }}" required></div>
                <div class="col-md-3"><label class="form-label small">Đến</label><input name="to_province" class="form-control form-control-sm" value="{{ $toll->to_province }}" required></div>
                <div class="col-md-2"><label class="form-label small">Số tiền</label><input type="number" name="amount_vnd" class="form-control form-control-sm" min="0" value="{{ $toll->amount_vnd }}" required></div>
                <div class="col-md-1"><label class="form-label small">Bật</label><div><input type="checkbox" name="is_active" value="1" @checked($toll->is_active)></div></div>
                <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Lưu</button></div>
            </form>
            <div class="col-12">
                <form method="POST" action="{{ route('admin.pricingTolls.destroy', $toll) }}" onsubmit="return confirm('Xóa tuyến thu phí?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-link btn-sm text-danger p-0">Xóa</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
<form method="POST" action="{{ route('admin.pricingTolls.store') }}" class="row g-2 align-items-end">
    @csrf
    <div class="col-md-3"><label class="form-label">Từ tỉnh</label><input name="from_province" class="form-control form-control-sm" required></div>
    <div class="col-md-3"><label class="form-label">Đến tỉnh</label><input name="to_province" class="form-control form-control-sm" required></div>
    <div class="col-md-2"><label class="form-label">Số tiền</label><input type="number" name="amount_vnd" class="form-control form-control-sm" min="0" required></div>
    <div class="col-md-2"><input type="hidden" name="is_active" value="1"><button class="btn btn-sm btn-primary w-100 mt-4">Thêm thu phí</button></div>
</form>
