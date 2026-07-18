<div class="driver-trip-sheet__mock" id="driver-trip-mock" hidden data-mock-stage="0">
    <header class="driver-trip-sheet-mock__head">
        <div>
            <p class="driver-trip-sheet-mock__eyebrow">Cuốc mới · Demo</p>
            <strong class="driver-trip-sheet-mock__passenger" data-mock-passenger>Nguyễn Văn A</strong>
        </div>
        <div class="driver-trip-sheet-mock__fare">
            <span class="driver-trip-sheet-mock__fare-label">Thu khách</span>
            <strong data-mock-fare>185.000 đ</strong>
        </div>
    </header>

    <div class="driver-trip-sheet-mock__route" aria-label="Lộ trình">
        <div class="driver-trip-sheet-mock__rail" aria-hidden="true">
            <span class="driver-trip-sheet-mock__pin driver-trip-sheet-mock__pin--pickup"></span>
            <span class="driver-trip-sheet-mock__line"></span>
            <span class="driver-trip-sheet-mock__pin driver-trip-sheet-mock__pin--dropoff"></span>
        </div>
        <div class="driver-trip-sheet-mock__stops">
            <div class="driver-trip-sheet-mock__stop">
                <span class="driver-trip-sheet-mock__stop-label">Điểm đón</span>
                <p class="driver-trip-sheet-mock__stop-value mb-0" data-mock-pickup>123 Nguyễn Huệ, Quận 1, TP.HCM</p>
            </div>
            <div class="driver-trip-sheet-mock__stop">
                <span class="driver-trip-sheet-mock__stop-label">Điểm trả</span>
                <p class="driver-trip-sheet-mock__stop-value mb-0" data-mock-dropoff>Sân bay Tân Sơn Nhất, Tân Bình</p>
            </div>
        </div>
    </div>

    <nav class="driver-trip-sheet-mock__actions" aria-label="Liên hệ nhanh">
        <button type="button" class="driver-trip-sheet-mock__icon-btn" aria-label="Gọi khách">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/>
            </svg>
            <span>Gọi</span>
        </button>
        <button type="button" class="driver-trip-sheet-mock__icon-btn" aria-label="Nhắn tin">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <span>Nhắn tin</span>
        </button>
        <button type="button" class="driver-trip-sheet-mock__icon-btn" aria-label="Điều hướng">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polygon points="3 11 22 2 13 21 11 13 3 11"/>
            </svg>
            <span>Điều hướng</span>
        </button>
    </nav>

    <button type="button" class="driver-trip-sheet-mock__primary" id="driver-mock-primary-cta" data-mock-cta>
        Nhận chuyến
    </button>
</div>
