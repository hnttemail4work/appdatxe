@php
use App\Support\LocationCatalog;
$selected = $selected ?? '';
$all = LocationCatalog::all();
@endphp
@if($selected !== '' && ! in_array($selected, $all, true))
    <option value="{{ $selected }}" selected>{{ $selected }}</option>
@endif
@foreach(LocationCatalog::grouped() as $groupLabel => $items)
    <optgroup label="{{ $groupLabel }}">
        @foreach($items as $p)
            <option value="{{ $p }}" @selected($selected === $p)>{{ $p }}</option>
        @endforeach
    </optgroup>
@endforeach
