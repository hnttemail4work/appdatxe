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
    <div class="trip-chat-bar">
        <button type="button" class="trip-chat-toggle" data-chat-toggle aria-expanded="false"
                aria-label="{{ $chatMode === 'driver' ? 'Nhắn khách' : 'Nhắn tài xế' }}"
                data-chat-label-closed="{{ $chatMode === 'driver' ? 'Nhắn khách' : 'Nhắn tài xế' }}"
                data-chat-label-open="Quay lại">
            <span class="trip-chat-toggle__icon trip-chat-toggle__icon--chat" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </span>
            <span class="trip-chat-toggle__icon trip-chat-toggle__icon--back" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </span>
            <span class="trip-chat-toggle__label" data-chat-toggle-label>{{ $chatMode === 'driver' ? 'Nhắn khách' : 'Nhắn tài xế' }}</span>
        </button>
        <button type="button"
                class="trip-chat-expand"
                data-chat-expand
                aria-pressed="false"
                aria-label="Phóng to toàn màn hình"
                title="Phóng to toàn màn hình">
            <span class="trip-chat-expand__icon trip-chat-expand__icon--grow" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3"/>
                    <path d="M16 3h3a2 2 0 0 1 2 2v3"/>
                    <path d="M8 21H5a2 2 0 0 1-2-2v-3"/>
                    <path d="M16 21h3a2 2 0 0 0 2-2v-3"/>
                </svg>
            </span>
            <span class="trip-chat-expand__icon trip-chat-expand__icon--shrink" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 3v3a2 2 0 0 1-2 2H3"/>
                    <path d="M21 8h-3a2 2 0 0 1-2-2V3"/>
                    <path d="M3 16h3a2 2 0 0 1 2 2v3"/>
                    <path d="M16 21v-3a2 2 0 0 1 2-2h3"/>
                </svg>
            </span>
        </button>
    </div>
    @endunless

    <div class="trip-chat-body {{ $embed ? '' : 'd-none' }}" data-chat-body>
        <div class="trip-chat-thread">
            <div class="trip-chat-messages" data-chat-messages aria-live="polite"></div>
            <p class="trip-chat-empty" data-chat-empty>Chưa có tin nhắn với {{ $peerLabel }}. Gửi tin đầu tiên bên dưới.</p>
        </div>
        <form class="trip-chat-form" data-chat-form>
            <label class="trip-chat-form__attach" title="Đính kèm ảnh">
                <input type="file" class="trip-chat-form__file" data-chat-image accept="image/jpeg,image/png,image/webp,image/gif" hidden @if($embed) disabled @endif>
                <span class="trip-chat-form__attach-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.44 11.05l-8.49 8.49a5.5 5.5 0 0 1-7.78-7.78l8.49-8.49a3.5 3.5 0 0 1 4.95 4.95l-8.49 8.49a1.5 1.5 0 0 1-2.12-2.12l7.78-7.78"/>
                    </svg>
                </span>
                <span class="visually-hidden">Đính kèm ảnh</span>
            </label>
            <div class="trip-chat-form__compose">
                <div class="trip-chat-form__preview d-none" data-chat-image-preview>
                    <img src="" alt="" data-chat-image-preview-img>
                    <button type="button" class="trip-chat-form__preview-clear" data-chat-image-clear aria-label="Bỏ ảnh">×</button>
                </div>
                <input type="text" class="trip-chat-form__input" data-chat-input maxlength="1000"
                       placeholder="Nhập tin nhắn cho {{ $peerLabel }}…" autocomplete="off"
                       @if($embed) disabled @endif>
            </div>
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
