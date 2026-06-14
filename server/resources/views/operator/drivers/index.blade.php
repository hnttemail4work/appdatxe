@extends('layouts.app')

@section('content')
<div class="row g-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="mb-0 card-title-bar">Quản lý tài xế</h3>
            <p class="text-muted mb-0">Danh sách tài xế — bấm Sửa để chỉnh thông tin hoặc upload ảnh.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('operator.drivers.create') }}" class="btn btn-primary btn-sm">+ Thêm tài xế</a>
            <a href="{{ route(auth()->user()->role === 'admin' ? 'admin.dashboard' : 'operator.dashboard') }}"
               class="btn btn-outline-secondary btn-sm">← Về Dashboard</a>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                @if($drivers->isEmpty())
                    <div class="p-4 text-center text-muted">
                        <p class="mb-2">Chưa có tài xế nào.</p>
                        <a href="{{ route('operator.drivers.create') }}" class="btn btn-sm btn-primary">Thêm tài xế đầu tiên</a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:48px"></th>
                                    <th>Họ tên</th>
                                    <th>Mã TX</th>
                                    <th>Liên hệ</th>
                                    <th>Bằng lái</th>
                                    <th>Kinh nghiệm</th>
                                    @if(auth()->user()->role === 'admin')
                                        <th>Quản lý</th>
                                    @endif
                                    <th>Trạng thái</th>
                                    <th>Hồ sơ ảnh</th>
                                    <th style="width:100px">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($drivers as $d)
                                @php
                                    $vehicleCount = count($d->photo_vehicles ?? []);
                                    $docCount = collect(['photo_portrait','photo_id_card','photo_id_card_back'])
                                        ->filter(fn($c) => $d->{$c})->count();
                                @endphp
                                <tr>
                                    <td>
                                        @if($d->photo_portrait)
                                            <img src="{{ $d->photoUrl('photo_portrait') }}" alt=""
                                                 class="rounded-circle object-fit-cover border"
                                                 style="width:36px;height:36px;">
                                        @else
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                                 style="width:36px;height:36px;font-size:.85rem;font-weight:700;">
                                                {{ mb_substr($d->user->name, 0, 1) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $d->user->name }}</strong>
                                        @if($d->user->id_number)
                                            <br><small class="text-muted">CCCD: {{ $d->user->id_number }}</small>
                                        @endif
                                    </td>
                                    <td><code>{{ $d->driver_code ?? '—' }}</code></td>
                                    <td class="small">
                                        {{ $d->user->phone ?? '—' }}<br>
                                        <span class="text-muted">{{ $d->user->email }}</span>
                                    </td>
                                    <td class="small">
                                        Hạng <strong>{{ $d->license_class }}</strong><br>
                                        HH: {{ $d->license_expiry->format('d/m/Y') }}
                                        @if($d->license_expiry->isPast())
                                            <span class="badge bg-danger">Hết hạn</span>
                                        @elseif($d->license_expiry->diffInDays(now()) < 60)
                                            <span class="badge bg-warning text-dark">Sắp HH</span>
                                        @endif
                                    </td>
                                    <td>{{ $d->experience_years }} năm</td>
                                    @if(auth()->user()->role === 'admin')
                                        <td class="small">{{ $d->operator?->name ?? '—' }}</td>
                                    @endif
                                    <td>
                                        <span class="badge bg-{{ match($d->status) { 'active'=>'primary','suspended'=>'danger',default=>'secondary' } }}">
                                            {{ match($d->status) { 'active'=>'Hoạt động','suspended'=>'Tạm ngưng',default=>'Không HĐ' } }}
                                        </span>
                                        <br>
                                        <span class="badge bg-{{ match($d->availability_status ?? 'off_duty') {
                                            'available'=>'success','on_trip'=>'info',default=>'secondary'
                                        } }} mt-1">
                                            {{ match($d->availability_status ?? 'off_duty') {
                                                'available'=>'Sẵn sàng','on_trip'=>'Đang chạy',default=>'Nghỉ'
                                            } }}
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        {{ $docCount }}/3 giấy tờ<br>
                                        {{ $vehicleCount }} ảnh xe
                                    </td>
                                    <td>
                                        <a href="{{ route('operator.drivers.edit', $d) }}"
                                           class="btn btn-sm btn-outline-primary">Sửa</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
