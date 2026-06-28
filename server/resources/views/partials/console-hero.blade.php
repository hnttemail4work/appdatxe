{{--
    @param string $title
    @param string|null $subtitle
    @param string|null $breadcrumbHref
    @param string|null $breadcrumbLabel
--}}
<div class="console-hero">
    <div class="console-hero-inner">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                @if(!empty($breadcrumbHref) && !empty($breadcrumbLabel))
                    <div class="console-breadcrumb">
                        <a href="{{ $breadcrumbHref }}">{{ $breadcrumbLabel }}</a>
                        <span class="mx-1">/</span>
                        <span>{{ $title }}</span>
                    </div>
                @endif
                <h1>{{ $title }}</h1>
                @if(!empty($subtitle))
                    <p>{{ $subtitle }}</p>
                @endif
            </div>
            @if(!empty($actions))
                <div class="console-hero-actions">
                    {!! $actions !!}
                </div>
            @endif
        </div>
        @if(!empty($slot))
            <div class="mt-3">{!! $slot !!}</div>
        @endif
    </div>
</div>
