@extends('layouts.console')

@section('console')
@include('partials.console-hero', ['title' => 'Quản trị hệ thống'])

<div class="console-panel">
    <div class="console-panel-body">
        @include('partials.admin-nav-tabs', ['active' => 'auth-codes'])

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 my-3">
            <div>
                <h2 class="h5 mb-1">Mã OTP / đặt lại mật khẩu</h2>
                <p class="text-muted small mb-0">Mã đang hiệu lực để nhắn tay cho khách / tài xế.</p>
            </div>
        </div>

        @if(session('auth_code_issued'))
            @php $issued = session('auth_code_issued'); @endphp
            <div class="alert alert-warning">
                <div class="fw-semibold mb-1">Mã vừa cấp — gửi ngay cho người dùng</div>
                <div>SĐT: <strong>{{ $issued['phone'] }}</strong></div>
                <div>Mã: <strong class="fs-4">{{ $issued['code'] }}</strong></div>
                <div class="small">Hết hạn: {{ $issued['expires'] ?? '—' }}</div>
            </div>
        @endif

        <div class="console-table-wrap">
            <table class="console-table">
                <thead>
                    <tr>
                        <th>SĐT</th>
                        <th>Loại</th>
                        <th>Trạng thái</th>
                        <th>Mã (admin)</th>
                        <th>Hết hạn</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($codes as $code)
                        @php
                            $purposeLabel = match ($code->purpose) {
                                'register_otp' => 'OTP đăng ký',
                                'password_reset_request' => 'Yêu cầu quên MK',
                                'password_reset' => 'Mã đặt lại MK',
                                default => $code->purpose,
                            };
                            $display = data_get($code->meta, 'admin_display_code');
                        @endphp
                        <tr>
                            <td>
                                <div class="cell-primary">{{ $code->phone }}</div>
                                <div class="text-muted small">{{ $code->user?->role ?? '—' }} · #{{ $code->user_id ?? '—' }}</div>
                            </td>
                            <td>{{ $purposeLabel }}</td>
                            <td><span class="status-pill status-pill--neutral">{{ $code->status }}</span></td>
                            <td>
                                @if($display)
                                    <code class="fs-6">{{ $display }}</code>
                                @elseif($code->status === 'pending_admin')
                                    <span class="text-muted small">Chờ cấp mã</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="cell-muted">{{ optional($code->expires_at)->format('H:i d/m') }}</td>
                            <td class="text-end">
                                @if($code->purpose === 'password_reset_request' && $code->status === 'pending_admin')
                                    <form method="POST" action="{{ route('admin.authCodes.issueReset', $code) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-warning">Cấp mã 6 số</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Không có mã đang hiệu lực.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
