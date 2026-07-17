@php
$chatMode = $mode ?? 'customer';
$chatBooking = $booking ?? null;
$isDriverChat = $chatMode === 'driver' && $chatBooking;
$chatOpen = $isDriverChat
    ? app(\App\Services\TripChatService::class)->isOpen($chatBooking)
    : false;
@endphp

<section class="trip-chat-panel {{ $chatOpen ? '' : 'd-none' }}"
         data-trip-chat
         data-chat-mode="{{ $chatMode }}"
         data-chat-open="{{ $chatOpen ? '1' : '0' }}"
         @if($isDriverChat)
         data-booking-reference="{{ $chatBooking->booking_reference }}"
         data-chat-list-url="{{ route('driver.bookings.chat.messages', $chatBooking) }}"
         data-chat-send-url="{{ route('driver.bookings.chat.send', $chatBooking) }}"
         @else
         data-chat-list-url="{{ route('booking.chat.messages') }}"
         data-chat-send-url="{{ route('booking.chat.send') }}"
         @endif>
    <button type="button" class="trip-chat-toggle" data-chat-toggle aria-expanded="false">
        <span class="trip-chat-toggle__icon" aria-hidden="true">💬</span>
        <span>
            <strong>{{ $isDriverChat ? 'Nhắn khách' : 'Nhắn tài xế' }}</strong>
            <small data-chat-status>
                {{ $isDriverChat ? 'Trao đổi thông tin đón khách' : 'Trao đổi trực tiếp về điểm đón' }}
            </small>
        </span>
        <span class="trip-chat-toggle__chevron" aria-hidden="true">›</span>
    </button>

    <div class="trip-chat-body d-none" data-chat-body>
        <div class="trip-chat-messages" data-chat-messages aria-live="polite"></div>
        <p class="trip-chat-empty text-muted small" data-chat-empty>Chưa có tin nhắn.</p>
        <form class="trip-chat-form" data-chat-form>
            <input type="text" class="form-control" data-chat-input maxlength="1000"
                   placeholder="Nhập tin nhắn…" autocomplete="off">
            <button type="submit" class="btn btn-primary" data-chat-send>Gửi</button>
        </form>
        <p class="trip-chat-error d-none" data-chat-error></p>
    </div>
</section>
