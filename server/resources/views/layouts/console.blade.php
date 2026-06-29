@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/console.css') }}?v={{ filemtime(public_path('css/console.css')) }}">
@endpush

@section('content')
<div class="console-page">
    @yield('console')
</div>
@endsection
