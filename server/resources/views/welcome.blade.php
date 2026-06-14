@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm p-5">
                <h1 class="display-6">Chào mừng đến với {{ config('app.name') }}</h1>
                <p class="lead text-muted">Hệ thống đặt vé xe đường dài thuần Laravel. Đăng nhập hoặc đăng ký để đặt vé, quản lý chuyến và vận hành.</p>
                <div class="d-flex gap-3 mt-4">
                    <a href="{{ route('login') }}" class="btn btn-primary">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="btn btn-outline-primary">Đăng ký</a>
                </div>
                <div class="mt-5 p-4 bg-light rounded-4">
                    <h5 class="mb-3">Tài khoản thử nghiệm</h5>
                    <ul class="mb-0">
                        <li>Admin: admin@appdatxe.test / password</li>
                        <li>Operator: operator@appdatxe.test / password</li>
                        <li>Customer: customer@appdatxe.test / password</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
