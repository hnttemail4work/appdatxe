<div class="modal fade address-map-picker-modal" id="addressMapPickerModal" tabindex="-1"
     aria-labelledby="addressMapPickerTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog address-map-picker-dialog">
        <div class="modal-content address-map-picker-content">
            <div class="address-map-fullscreen">
                <div id="address-map-canvas" class="address-map-canvas" aria-label="Bản đồ chọn điểm"></div>
                <button type="button" class="address-map-close-btn" data-bs-dismiss="modal" aria-label="Đóng">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                </button>
            </div>

            <div class="address-map-sheet">
                <div class="address-map-sheet-handle" aria-hidden="true"></div>
                <h2 class="address-map-sheet-title" id="addressMapPickerTitle">Chọn điểm trên bản đồ</h2>

                <div class="address-map-province-wrap d-none" id="address-map-province-wrap">
                    <label class="form-label small text-muted mb-1" for="address-map-province">Khu vực hoạt động</label>
                    <select id="address-map-province" class="form-select form-select-sm mb-2">
                        @include('partials.province-options', ['selected' => ''])
                    </select>
                </div>

                <div class="address-map-search-wrap">
                    <div class="address-map-search-field">
                        <svg class="address-map-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/>
                        </svg>
                        <input type="text" class="address-map-search-input" id="address-map-search"
                               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                               inputmode="search" enterkeyhint="search">
                        <button type="button" class="address-map-search-clear d-none" id="address-map-search-clear" aria-label="Xóa từ khóa">×</button>
                    </div>
                </div>

                <div class="address-map-toolbar d-none" id="address-map-driver-toolbar">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="address-map-my-location">
                        Vị trí của tôi
                    </button>
                </div>

                <div class="address-map-sheet-scroll">
                    <div id="address-map-search-results" class="address-map-search-results d-none" role="listbox" aria-label="Kết quả tìm kiếm"></div>

                    <div id="address-map-recent-wrap" class="address-map-recent d-none">
                        <div class="address-map-recent-title">Địa điểm gần đây</div>
                        <div id="address-map-recent-list"></div>
                    </div>

                    <p class="address-map-preview small mb-0" id="address-map-preview">
                        Tìm địa chỉ để bay tới khu vực, kéo ghim đến đúng điểm đón, rồi bấm xác nhận.
                    </p>
                    <p class="address-map-confirm-status small text-muted mb-0 d-none" id="address-map-confirm-status"></p>
                </div>

                <div class="address-map-sheet-footer">
                    <button type="button" class="address-map-confirm-cta" id="address-map-confirm" disabled>
                        <span>Xác nhận vị trí</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
