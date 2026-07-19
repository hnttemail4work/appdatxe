@php
    $inboxTab = in_array(request('inbox_tab'), ['info', 'notice'], true)
        ? request('inbox_tab')
        : 'info';
    $infoMessages = $inboxInfoMessages ?? collect();
    $noticeMessages = $inboxNoticeMessages ?? collect();
    $chatThreads = $inboxChatThreads ?? collect();
    $unreadInfo = (int) ($inboxUnread['info'] ?? 0);
    $unreadNotice = (int) ($inboxUnread['notice'] ?? 0);
    $unreadChat = (int) ($inboxUnread['chat'] ?? 0);
@endphp
<section class="driver-inbox-panel" aria-label="Hộp thư" data-driver-inbox-panel data-inbox-mark-url="{{ route('driver.inbox.read') }}">
    <div class="driver-inbox-head mb-3">
        <div class="driver-inbox-head__main" data-inbox-head-main>
            <h2 class="driver-panel-title mb-0">Hộp thư</h2>
            <button type="button"
                    class="driver-inbox-chat-btn"
                    data-inbox-open-chats
                    aria-label="Tin nhắn với khách">
                <span class="driver-inbox-chat-btn__label">Trò chuyện</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <span class="driver-inbox-chat-btn__badge {{ $unreadChat > 0 ? '' : 'd-none' }}" data-inbox-chat-badge>
                    {{ $unreadChat > 99 ? '99+' : $unreadChat }}
                </span>
            </button>
        </div>
        <div class="driver-inbox-head__chat d-none" data-inbox-head-chat hidden>
            <button type="button" class="driver-inbox-back" data-inbox-chat-back aria-label="Quay lại">←</button>
            <strong class="driver-inbox-head__chat-title" data-inbox-chat-title>Tin nhắn</strong>
        </div>
    </div>

    @if($profile?->isMissedTripLocked())
        <div class="driver-notice driver-notice-danger mb-3" role="alert" data-inbox-system-notice>
            <strong>Tài khoản tạm khóa</strong>
            <p class="mb-0 small">Không nhận chuyến được. Liên hệ quản lý để mở khóa.</p>
        </div>
    @endif

    @if($walletBlockReason ?? null)
        <div class="driver-notice driver-notice-warning mb-3" role="alert" data-inbox-system-notice>
            <strong>Ví tài xế</strong>
            <p class="mb-2 small">{{ $walletBlockReason }}</p>
            @if($walletNotice ?? null)
                <button type="button" class="btn btn-sm btn-outline-warning" data-driver-tab="wallet">
                    {{ $walletNotice['cta_label'] ?? 'Nạp ví ngay' }}
                </button>
            @endif
        </div>
    @endif

    <div class="driver-inbox-system" data-inbox-system>
        <div class="driver-inbox-tabs mb-3" role="tablist">
            <button type="button"
                    class="driver-inbox-tabs__btn {{ $inboxTab === 'info' ? 'is-active' : '' }}"
                    data-inbox-tab="info"
                    role="tab"
                    aria-selected="{{ $inboxTab === 'info' ? 'true' : 'false' }}">
                Tin tức
                @if($unreadInfo > 0)
                    <span class="driver-inbox-tabs__badge">{{ $unreadInfo }}</span>
                @endif
            </button>
            <button type="button"
                    class="driver-inbox-tabs__btn {{ $inboxTab === 'notice' ? 'is-active' : '' }}"
                    data-inbox-tab="notice"
                    role="tab"
                    aria-selected="{{ $inboxTab === 'notice' ? 'true' : 'false' }}">
                Thông báo
                @if($unreadNotice > 0)
                    <span class="driver-inbox-tabs__badge">{{ $unreadNotice }}</span>
                @endif
            </button>
        </div>

        <div class="driver-inbox-pane {{ $inboxTab === 'info' ? 'is-active' : '' }}" data-inbox-pane="info" @if($inboxTab !== 'info') hidden @endif>
            @if($infoMessages->isEmpty())
                <div class="driver-inbox-empty">
                    <p class="mb-0">Chưa có tin tức.</p>
                </div>
            @else
                <ul class="driver-inbox-list">
                    @foreach($infoMessages as $msg)
                        <li class="driver-inbox-list__item {{ $msg->isRead() ? '' : 'is-unread' }}"
                            data-inbox-message-id="{{ $msg->id }}"
                            role="button"
                            tabindex="0">
                            <strong class="driver-inbox-list__title">{{ $msg->title }}</strong>
                            <p class="driver-inbox-list__body mb-1">{{ $msg->body }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="driver-inbox-pane {{ $inboxTab === 'notice' ? 'is-active' : '' }}" data-inbox-pane="notice" @if($inboxTab !== 'notice') hidden @endif>
            @if($noticeMessages->isEmpty())
                <div class="driver-inbox-empty">
                    <p class="mb-0">Chưa có thông báo.</p>
                </div>
            @else
                <ul class="driver-inbox-list">
                    @foreach($noticeMessages as $msg)
                        <li class="driver-inbox-list__item {{ $msg->isRead() ? '' : 'is-unread' }}"
                            data-inbox-message-id="{{ $msg->id }}"
                            role="button"
                            tabindex="0">
                            <strong class="driver-inbox-list__title">{{ $msg->title }}</strong>
                            <p class="driver-inbox-list__body mb-1">{{ $msg->body }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="driver-inbox-chats d-none" data-inbox-chats hidden>
        @if($chatThreads->isEmpty())
            <div class="driver-inbox-empty">
                <p class="mb-0">Chưa có tin nhắn với khách.</p>
            </div>
        @else
            <ul class="driver-inbox-chat-list">
                @foreach($chatThreads as $thread)
                    @php
                        /** @var \App\Models\Booking $chatBooking */
                        $chatBooking = $thread['booking'];
                        $chatUnread = (int) ($thread['unread'] ?? 0);
                        $peerName = $chatBooking->passenger_name ?: 'Hành khách';
                    @endphp
                    <li>
                        <button type="button"
                                class="driver-inbox-chat-item {{ $chatUnread > 0 ? 'is-unread' : '' }}"
                                data-inbox-chat-item
                                data-booking-reference="{{ $chatBooking->booking_reference }}"
                                data-chat-peer="{{ $peerName }}"
                                data-chat-list-url="{{ route('driver.bookings.chat.messages', $chatBooking) }}"
                                data-chat-send-url="{{ route('driver.bookings.chat.send', $chatBooking) }}"
                                data-chat-open="{{ ! empty($thread['open']) ? '1' : '0' }}">
                            <span class="driver-inbox-chat-item__top">
                                <span class="driver-inbox-chat-item__name">{{ $peerName }}</span>
                                @if($thread['last_at'] ?? null)
                                    <time>{{ $thread['last_at'] }}</time>
                                @endif
                            </span>
                            <span class="driver-inbox-chat-item__preview">{{ $thread['preview'] ?: '—' }}</span>
                            @if($chatUnread > 0)
                                <span class="driver-inbox-chat-item__badge">{{ $chatUnread > 99 ? '99+' : $chatUnread }}</span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="driver-inbox-thread d-none" data-inbox-thread hidden>
        @include('partials.trip-chat-panel', [
            'mode' => 'driver',
            'booking' => null,
            'embed' => true,
        ])
    </div>
</section>
