@php
    $pinName = $pinName ?? 'pin';
    $pinId = $pinId ?? ('pin-'.preg_replace('/[^a-z0-9_-]+/i', '-', $pinName));
    $pinLabel = $pinLabel ?? 'PIN';
    $pinErrorBag = $pinErrorBag ?? $pinName;
    $nextType = $nextType ?? 'button';
    $nextAttr = $nextAttr ?? 'data-auth-next';
    $nextAria = $nextAria ?? 'Tiếp tục';
    $pinAutoSubmit = !empty($pinAutoSubmit);
    $hideNext = !empty($hideNext);
@endphp
<div class="auth-field-block" data-auth-pin-block>
    @if($pinLabel)
        <label class="auth-field-label" for="{{ $pinId }}-0">{{ $pinLabel }}</label>
    @endif
    <div class="auth-pin-row{{ $hideNext ? ' auth-pin-row--solo' : '' }}">
        <div class="pin-boxes pin-boxes--inline" data-pin-boxes data-pin-name="{{ $pinName }}" id="{{ $pinId }}-wrap"
             @if($pinAutoSubmit) data-pin-autosubmit="1" @endif>
            <div class="pin-boxes-row" role="group" aria-label="{{ $pinLabel }}">
                @for($i = 0; $i < 6; $i++)
                    <input
                        type="password"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="1"
                        class="pin-box form-control @error($pinErrorBag) is-invalid @enderror"
                        id="{{ $pinId }}-{{ $i }}"
                        data-pin-index="{{ $i }}"
                        autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                        aria-label="{{ $pinLabel }} chữ số {{ $i + 1 }}"
                    >
                @endfor
            </div>
            <input type="hidden" name="{{ $pinName }}" data-pin-value value="{{ old($pinName) }}">
        </div>
        @unless($hideNext)
            @include('partials.auth-next-btn', [
                'nextType' => $nextType,
                'nextAttr' => $nextAttr ?? '',
                'nextAria' => $nextAria,
            ])
        @endunless
    </div>
    <div class="auth-field-error" @error($pinErrorBag)@else hidden @enderror>@error($pinErrorBag){{ $message }}@enderror</div>
</div>
