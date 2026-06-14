@extends('layouts.app')

@section('content')
<div class="row g-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="mb-0 card-title-bar">Thêm tài xế mới</h3>
            <p class="text-muted mb-0">Tạo tài khoản đăng nhập. Tài xế có thể tự bổ sung bằng lái và ảnh hồ sơ sau.</p>
        </div>
        <a href="{{ route('operator.drivers') }}" class="btn btn-outline-secondary btn-sm">← Danh sách tài xế</a>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm p-4">
            <form method="POST" action="{{ route('operator.drivers.store') }}">
                @csrf
                @include('partials.driver-form-fields', [
                    'mode'     => 'create',
                    'driver'   => null,
                    'operators'=> $operators,
                ])
                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-primary">Tạo tài xế</button>
                    <a href="{{ route('operator.drivers') }}" class="btn btn-outline-secondary">Huỷ</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
