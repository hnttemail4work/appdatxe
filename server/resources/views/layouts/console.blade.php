@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/console.css') }}?v={{ filemtime(public_path('css/console.css')) }}">
@endpush

@section('content')
<div class="console-page"
     @auth
     @if(auth()->user()->role === 'admin')
     data-admin-alerts-poll="{{ route('admin.alerts.poll') }}"
     data-admin-alerts-interval="12000"
     @endif
     @endauth>
    @yield('console')
</div>
@endsection

@push('scripts')
@auth
@if(auth()->user()->role === 'admin')
<script src="{{ asset('js/admin-alerts-poll.js') }}?v={{ filemtime(public_path('js/admin-alerts-poll.js')) }}"></script>
@endif
@endauth
@endpush
