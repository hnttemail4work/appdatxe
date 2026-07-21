@php
    $inboxTab = in_array($inboxTab ?? 'info', ['info', 'notice'], true)
        ? ($inboxTab ?? 'info')
        : 'info';
    $infoMessages = $inboxInfoMessages ?? collect();
    $noticeMessages = $inboxNoticeMessages ?? collect();
    $chatThreads = $inboxChatThreads ?? collect();
    $unreadInfo = (int) ($inboxUnread['info'] ?? 0);
    $unreadNotice = (int) ($inboxUnread['notice'] ?? 0);
    $unreadChat = (int) ($inboxUnread['chat'] ?? 0);
@endphp
<section class="customer-inbox-panel" aria-label="Hộp thư" data-customer-inbox-panel data-inbox-mark-url="{{ route('customer.inbox.read') }}">
    <div class="customer-inbox-head mb-3 d-none" data-inbox-head-chat hidden>
        <button type="button" class="customer-inbox-back" data-inbox-chat-back aria-label="Quay lại">@include('partials.app-back-icon')</button>
        <strong class="customer-inbox-head__chat-title" data-inbox-chat-title>Tin nhắn</strong>
    </div>

    <div class="customer-inbox-system" data-inbox-system>
        <div class="customer-inbox-tabs mb-3" role="tablist">
            <button type="button"
                    class="customer-inbox-tabs__btn {{ $inboxTab === 'info' ? 'is-active' : '' }}"
                    data-inbox-tab="info"
                    role="tab"
                    aria-selected="{{ $inboxTab === 'info' ? 'true' : 'false' }}">
                Tin tức
                @if($unreadInfo > 0)
                    <span class="customer-inbox-tabs__badge">{{ $unreadInfo }}</span>
                @endif
            </button>
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
        </div>

        <div class="customer-inbox-pane {{ $inboxTab === 'info' ? 'is-active' : '' }}" data-inbox-pane="info" @if($inboxTab !== 'info') hidden @endif>
            @if($infoMessages->isEmpty())
                <div class="customer-inbox-empty">
                    <p class="mb-0">Chưa có tin tức.</p>
                </div>
            @else
                <ul class="customer-inbox-list">
                    @foreach($infoMessages as $msg)
                        <li class="customer-inbox-list__item {{ $msg->isRead() ? '' : 'is-unread' }}"
                            data-inbox-message-id="{{ $msg->id }}"
                            role="button"
                            tabindex="0">
                            <strong class="customer-inbox-list__title">{{ $msg->title }}</strong>
                            <p class="customer-inbox-list__body mb-1">{{ $msg->body }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="customer-inbox-pane {{ $inboxTab === 'notice' ? 'is-active' : '' }}" data-inbox-pane="notice" @if($inboxTab !== 'notice') hidden @endif>
            @if($noticeMessages->isEmpty())
                <div class="customer-inbox-empty">
                    <p class="mb-0">Chưa có thông báo.</p>
                </div>
            @else
                <ul class="customer-inbox-list">
                    @foreach($noticeMessages as $msg)
                        <li class="customer-inbox-list__item {{ $msg->isRead() ? '' : 'is-unread' }}"
                            data-inbox-message-id="{{ $msg->id }}"
                            role="button"
                            tabindex="0">
                            <div class="customer-inbox-list__top">
                                <strong class="customer-inbox-list__title">{{ $msg->title }}</strong>
                                @if($msg->created_at)
                                    <time datetime="{{ $msg->created_at->toIso8601String() }}">{{ $msg->created_at->format('d/m/Y H:i') }}</time>
                                @endif
                            </div>
                            <p class="customer-inbox-list__body mb-1">{{ $msg->body }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="customer-inbox-chats d-none" data-inbox-chats hidden>
        @if($chatThreads->isEmpty())
            <div class="customer-inbox-empty">
                <p class="mb-0">Chưa có tin nhắn với tài xế.</p>
            </div>
        @else
            <ul class="customer-inbox-chat-list">
                @foreach($chatThreads as $thread)
                    @php
                        /** @var \App\Models\Booking $chatBooking */
                        $chatBooking = $thread['booking'];
                        $chatUnread = (int) ($thread['unread'] ?? 0);
                        $peerName = trim((string) (
                            $chatBooking->schedule?->driver_name
                            ?: $chatBooking->assignedDriver?->name
                            ?: 'Tài xế'
                        ));
                        if ($peerName === '' || $peerName === 'Chờ phân bổ') {
                            $peerName = 'Tài xế';
                        }
                    @endphp
                    <li>
                        <button type="button"
                                class="customer-inbox-chat-item {{ $chatUnread > 0 ? 'is-unread' : '' }}"
                                data-inbox-chat-item
                                data-booking-reference="{{ $chatBooking->booking_reference }}"
                                data-chat-peer="{{ $peerName }}"
                                data-chat-list-url="{{ route('booking.chat.messages') }}"
                                data-chat-send-url="{{ route('booking.chat.send') }}"
                                data-chat-open="{{ ! empty($thread['open']) ? '1' : '0' }}">
                            <span class="customer-inbox-chat-item__top">
                                <span class="customer-inbox-chat-item__name">{{ $peerName }}</span>
                                @if($thread['last_at'] ?? null)
                                    <time>{{ $thread['last_at'] }}</time>
                                @endif
                            </span>
                            <span class="customer-inbox-chat-item__preview">{{ $thread['preview'] ?: '—' }}</span>
                            @if($chatUnread > 0)
                                <span class="customer-inbox-chat-item__badge">{{ $chatUnread > 99 ? '99+' : $chatUnread }}</span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="customer-inbox-thread d-none" data-inbox-thread hidden>
        @include('partials.trip-chat-panel', [
            'mode' => 'customer',
            'booking' => null,
            'embed' => true,
        ])
    </div>

    <button type="button"
            class="customer-inbox-chat-fab"
            data-inbox-open-chats
            aria-label="Trò chuyện"
            title="Trò chuyện">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <span class="customer-inbox-chat-fab__badge {{ $unreadChat > 0 ? '' : 'd-none' }}" data-inbox-chat-badge>
            {{ $unreadChat > 99 ? '99+' : $unreadChat }}
        </span>
    </button>
</section>
