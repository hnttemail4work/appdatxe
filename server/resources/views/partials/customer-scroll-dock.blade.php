<nav class="customer-scroll-dock{{ ($guestShowTrackTab ?? false) ? '' : ' customer-scroll-dock--two-tabs' }}" aria-label="Điều hướng nhanh">
    <button type="button" class="customer-scroll-dock-item is-active" data-scroll-target="booking-search-block" aria-label="Tìm chuyến">
        <span class="customer-scroll-dock-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        </span>
        <span class="customer-scroll-dock-label">Tìm</span>
    </button>
    <button type="button" class="customer-scroll-dock-item customer-scroll-dock-item--primary" data-scroll-target="booking-results-main" aria-label="Danh sách chuyến có thể đặt">
        <span class="customer-scroll-dock-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
        </span>
        <span class="customer-scroll-dock-label">Chuyến</span>
    </button>
    <button type="button"
            class="customer-scroll-dock-item customer-scroll-dock-item--track{{ ($guestShowTrackTab ?? false) ? '' : ' d-none' }}"
            data-scroll-target="guest-trip-watch-section"
            aria-label="Đơn đặt của bạn">
        <span class="customer-scroll-dock-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4h12a2 2 0 0 1 2 2v1H4V6a2 2 0 0 1 2-2z"/><path d="M4 9h16v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9z"/><path d="M8 13h8"/></svg>
        </span>
        <span class="customer-scroll-dock-label">Đơn đặt</span>
        <span class="customer-scroll-dock-badge d-none" data-scroll-dock-badge="track"></span>
    </button>
</nav>
