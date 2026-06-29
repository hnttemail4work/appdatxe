@if(session('success') && ! request()->routeIs('home'))
    <div class="alert alert-success app-flash mb-3" role="alert" data-auto-dismiss="10000">
        {{ session('success') }}
        @include('partials.flash-close')
    </div>
@endif

@if(isset($errors) && $errors->any() && ! request()->routeIs('home'))
    <div class="alert alert-danger app-flash mb-3" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        @include('partials.flash-close')
    </div>
@endif
