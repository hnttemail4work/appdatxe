@extends('layouts.app')

@section('content')
<div class="customer-page register-shell register-shell-wide">
    <div class="card shadow-sm border-0 overflow-hidden">
        <div class="register-header pb-0">
            <h2 class="mb-0">Đăng ký tài xế</h2>
        </div>
        <div class="card-body p-4 pt-3">
            <form method="POST" action="{{ route('register') }}"
                  id="driver-register-form" enctype="multipart/form-data" novalidate>
                @csrf
                <input type="hidden" name="register_mode" value="driver">
                @include('auth.partials.register-driver')
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
@endpush

@push('scripts')
<script src="{{ asset('js/driver-register-wizard.js') }}"></script>
@endpush
