@if(session('success') && ! request()->routeIs('home'))
    <div class="alert alert-success app-flash mb-3" role="alert" data-auto-dismiss="10000">
        {{ session('success') }}
        @include('partials.flash-close')
    </div>
@endif

@if(isset($errors) && $errors->any() && ! request()->routeIs('home'))
    <div class="alert alert-danger app-flash mb-3" role="alert" data-auto-dismiss="10000">
        @foreach($errors->all() as $error)
            <div @if(! $loop->last) class="mb-1" @endif>{{ $error }}</div>
        @endforeach
        @include('partials.flash-close')
    </div>
@endif
