@php
    $operatorNotifications = \App\Support\OperatorNotifications::list(auth()->id());
    $operatorNotifyCount = count($operatorNotifications);
@endphp
<div class="operator-notify-wrap">
    <button type="button" class="operator-notify-btn" id="operator-notify-btn"
            aria-label="Thông báo" aria-expanded="false" aria-controls="operator-notify-panel">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        @if($operatorNotifyCount > 0)
            <span class="operator-notify-badge">{{ $operatorNotifyCount }}</span>
        @endif
    </button>

    <div class="operator-notify-panel" id="operator-notify-panel" hidden>
        <div class="operator-notify-panel-head">
            <strong>Thông báo</strong>
            <button type="button" class="operator-notify-close" id="operator-notify-close" aria-label="Đóng">×</button>
        </div>
        @if($operatorNotifications === [])
            <p class="operator-notify-empty mb-0">Không có thông báo mới.</p>
        @else
            <ul class="operator-notify-list mb-0">
                @foreach($operatorNotifications as $item)
                    <li>
                        <a href="{{ $item['href'] }}">{{ $item['message'] }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
