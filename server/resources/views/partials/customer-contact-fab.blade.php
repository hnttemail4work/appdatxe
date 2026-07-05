@php
$hotlinePhone = (string) ($hotlinePhone ?? config('app.contact_phone'));
$hotlineTel = preg_replace('/[^\d+]/', '', $hotlinePhone);
$zaloDigits = preg_replace('/\D+/', '', $hotlinePhone);
if (str_starts_with($zaloDigits, '0')) {
    $zaloDigits = '84' . substr($zaloDigits, 1);
} elseif ($zaloDigits !== '' && ! str_starts_with($zaloDigits, '84')) {
    $zaloDigits = '84' . $zaloDigits;
}
$zaloUrl = $zaloDigits !== '' ? 'https://zalo.me/' . $zaloDigits : '#';
$variant = $variant ?? 'fixed';
@endphp

<div class="customer-contact-fab customer-contact-fab--{{ $variant }}" aria-label="Liên hệ tổng đài">
    <a href="tel:{{ $hotlineTel }}"
       class="customer-contact-fab-btn customer-contact-fab-btn--phone"
       data-contact-hotline="phone"
       aria-label="Gọi tổng đài {{ $hotlinePhone }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
    </a>
    <a href="{{ $zaloUrl }}"
       class="customer-contact-fab-btn customer-contact-fab-btn--zalo"
       data-contact-hotline="zalo"
       target="_blank"
       rel="noopener noreferrer"
       aria-label="Chat Zalo tổng đài {{ $hotlinePhone }}">
        <span class="customer-contact-fab-zalo-label" aria-hidden="true">zalo</span>
    </a>
</div>
