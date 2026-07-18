@php
$chatMode = $mode ?? 'customer';
$chatBooking = $booking ?? null;
$embed = (bool) ($embed ?? false);
$isDriverChat = $chatMode === 'driver' && $chatBooking;
$chatOpen = $isDriverChat
    ? app(\App\Services\TripChatService::class)->isOpen($chatBooking)
    : false;
$peerLabel = $isDriverChat || ($chatMode === 'driver' && $embed) ? 'khách' : 'tài xế';
$showPanel = $embed || $chatOpen || $chatMode === 'customer';
@endphp

<section class="trip-chat-panel {{ $embed ? 'trip-chat-panel--embed' : '' }} {{ $showPanel ? 'is-available' : 'd-none' }}"
         data-trip-chat
         data-chat-mode="{{ $chatMode }}"
         data-chat-open="{{ $chatOpen ? '1' : '0' }}"
         @if($embed) data-chat-embed="1" @endif
         @if($isDriverChat)
         data-booking-reference="{{ $chatBooking->booking_reference }}"
         data-chat-list-url="{{ route('driver.bookings.chat.messages', $chatBooking) }}"
         data-chat-send-url="{{ route('driver.bookings.chat.send', $chatBooking) }}"
         @elseif($chatMode === 'customer')
         data-chat-list-url="{{ route('booking.chat.messages') }}"
         data-chat-send-url="{{ route('booking.chat.send') }}"
         @endif>
    @unless($embed)
    <button type="button" class="trip-chat-toggle" data-chat-toggle aria-expanded="false">
        <span class="trip-chat-toggle__icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </span>
        <span class="trip-chat-toggle__copy">
            <strong>{{ $chatMode === 'driver' ? 'Nhắn khách' : 'Nhắn tài xế' }}</strong>
            <small data-chat-status>
                {{ $chatMode === 'driver' ? 'Trao đổi điểm đón / ghi chú chuyến' : 'Hỏi điểm đón, giờ đón với tài xế' }}
            </small>
        </span>
        <span class="trip-chat-toggle__chevron" aria-hidden="true"></span>
    </button>
    @endunless

    <div class="trip-chat-body {{ $embed ? '' : 'd-none' }}" data-chat-body>
        @if($embed)
            <p class="trip-chat-status-line" data-chat-status></p>
        @endif
        <div class="trip-chat-thread">
            <div class="trip-chat-messages" data-chat-messages aria-live="polite"></div>
            <p class="trip-chat-empty" data-chat-empty>Chưa có tin nhắn với {{ $peerLabel }}. Gửi tin đầu tiên bên dưới.</p>
        </div>
        <form class="trip-chat-form" data-chat-form>
            <input type="text" class="trip-chat-form__input" data-chat-input maxlength="1000"
                   placeholder="Nhập tin nhắn cho {{ $peerLabel }}…" autocomplete="off"
                   @if($embed) disabled @endif>
            <button type="submit" class="trip-chat-form__send" data-chat-send aria-label="Gửi" @if($embed) disabled @endif>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 2 11 13"/>
                    <path d="M22 2 15 22 11 13 2 9 22 2z"/>
                </svg>
            </button>
        </form>
        <p class="trip-chat-error d-none" data-chat-error></p>
    </div>
</section>
