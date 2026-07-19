@php
    $flashSuccess = session('success');
    $flashInfo = session('info');
    $flashError = session('error');
    // Auth screen: flash lỗi ở trên (field dưới không lặp). Trang chủ: không dump $errors.
    $flashErrors = (isset($errors) && $errors->any() && ! request()->routeIs('home'))
        ? $errors->all()
        : [];
    $preferDialogFlash = request()->routeIs('driver.dashboard');
    $isHome = request()->routeIs('home');
@endphp

@if($flashSuccess || $flashInfo || $flashError || $flashErrors !== [])
    <script>
        window.__appServerFlash = {
            success: @json($flashSuccess ?: null),
            info: @json($flashInfo ?: null),
            error: @json($flashError ?: null),
            errors: @json($flashErrors),
            preferDialog: @json($preferDialogFlash),
            homePendingFlash: false,
        };
    </script>
@endif

@unless($preferDialogFlash)
    {{-- Trang chủ: không flash “chờ duyệt” — chỉ hộp thư. OTP được hiện flash đăng ký. --}}
    @if($flashSuccess && ! $isHome)
        <div class="alert alert-success app-flash mb-3" role="status" data-auto-dismiss="10000">
            {{ $flashSuccess }}
            @include('partials.flash-close')
        </div>
    @endif

    @if($flashInfo)
        <div class="alert alert-warning app-flash mb-3" role="status" data-auto-dismiss="15000">
            {{ $flashInfo }}
            @include('partials.flash-close')
        </div>
    @endif

    @if($flashError)
        <div class="alert alert-danger app-flash mb-3" role="alert" data-auto-dismiss="10000">
            {{ $flashError }}
            @include('partials.flash-close')
        </div>
    @endif

    @if($flashErrors !== [])
        <div class="alert alert-danger app-flash mb-3" role="alert" data-auto-dismiss="10000">
            @foreach($flashErrors as $error)
                <div @if(! $loop->last) class="mb-1" @endif>{{ $error }}</div>
            @endforeach
            @include('partials.flash-close')
        </div>
    @endif
@endunless
