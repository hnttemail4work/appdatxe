@php
    $inboxTab = in_array($inboxTab ?? 'notice', ['info', 'notice'], true)
        ? ($inboxTab ?? 'notice')
        : 'notice';
    $infoMessages = $inboxInfoMessages ?? collect();
    $noticeMessages = $inboxNoticeMessages ?? collect();
    $unreadInfo = (int) ($inboxUnread['info'] ?? 0);
    $unreadNotice = (int) ($inboxUnread['notice'] ?? 0);
@endphp
<section class="customer-inbox-panel" aria-label="Hộp thư" data-customer-inbox-panel data-inbox-mark-url="{{ route('customer.inbox.read') }}">
    <div class="customer-inbox-tabs mb-3" role="tablist">
        <button type="button"
                class="customer-inbox-tabs__btn {{ $inboxTab === 'notice' ? 'is-active' : '' }}"
                data-inbox-tab="notice"
                role="tab"
                aria-selected="{{ $inboxTab === 'notice' ? 'true' : 'false' }}">
            Thông báo
            @if($unreadNotice > 0)
                <span class="customer-inbox-tabs__badge">{{ $unreadNotice }}</span>
            @endif
        </button>
        <button type="button"
                class="customer-inbox-tabs__btn {{ $inboxTab === 'info' ? 'is-active' : '' }}"
                data-inbox-tab="info"
                role="tab"
                aria-selected="{{ $inboxTab === 'info' ? 'true' : 'false' }}">
            Thông tin
            @if($unreadInfo > 0)
                <span class="customer-inbox-tabs__badge">{{ $unreadInfo }}</span>
            @endif
        </button>
    </div>

    <div class="customer-inbox-pane {{ $inboxTab === 'notice' ? 'is-active' : '' }}" data-inbox-pane="notice" @if($inboxTab !== 'notice') hidden @endif>
        @if($noticeMessages->isEmpty())
            <div class="customer-inbox-empty">
                <p class="mb-0">Chưa có thông báo.</p>
            </div>
        @else
            <ul class="customer-inbox-list">
                @foreach($noticeMessages as $msg)
                    <li class="customer-inbox-list__item {{ $msg->isRead() ? '' : 'is-unread' }}">
                        <strong>{{ $msg->title }}</strong>
                        <p class="mb-1">{{ $msg->body }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="customer-inbox-pane {{ $inboxTab === 'info' ? 'is-active' : '' }}" data-inbox-pane="info" @if($inboxTab !== 'info') hidden @endif>
        @if($infoMessages->isEmpty())
            <div class="customer-inbox-empty">
                <p class="mb-0">Chưa có thông tin.</p>
            </div>
        @else
            <ul class="customer-inbox-list">
                @foreach($infoMessages as $msg)
                    <li class="customer-inbox-list__item {{ $msg->isRead() ? '' : 'is-unread' }}">
                        <strong>{{ $msg->title }}</strong>
                        <p class="mb-1">{{ $msg->body }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
