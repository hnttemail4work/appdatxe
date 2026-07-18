@php
    $flashSuccess = session('success');
    $flashError = session('error');
    $flashErrors = (isset($errors) && $errors->any() && ! request()->routeIs('home', 'register'))
        ? $errors->all()
        : [];
    $preferDialogFlash = request()->routeIs('driver.dashboard');
@endphp

@if($flashSuccess || $flashError || $flashErrors !== [])
    <script>
        window.__appServerFlash = {
            success: @json($flashSuccess ?: null),
            error: @json($flashError ?: null),
            errors: @json($flashErrors),
            preferDialog: @json($preferDialogFlash),
        };
    </script>
@endif

@unless($preferDialogFlash)
    @if($flashSuccess && ! request()->routeIs('home'))
        <div class="alert alert-success app-flash mb-3" role="alert" data-auto-dismiss="10000">
            {{ $flashSuccess }}
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
