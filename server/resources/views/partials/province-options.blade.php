@foreach(\App\Support\SouthernProvinces::grouped() as $groupLabel => $items)
    <optgroup label="{{ $groupLabel }}">
        @foreach($items as $p)
            <option value="{{ $p }}" @selected(($selected ?? '') === $p)>{{ $p }}</option>
        @endforeach
    </optgroup>
@endforeach
