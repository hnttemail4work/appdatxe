@php
    /** @var string $prefix @var string $key @var bool $active */
    $active = $active ?? false;
@endphp
<div class="tab-pane fade {{ $active ? 'show active' : '' }}"
     id="{{ $prefix }}-pane-{{ $key }}"
     role="tabpanel"
     aria-labelledby="{{ $prefix }}-tab-{{ $key }}"
     tabindex="0">
