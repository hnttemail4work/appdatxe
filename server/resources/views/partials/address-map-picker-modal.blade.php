<div class="modal fade address-map-picker-modal" id="addressMapPickerModal" tabindex="-1"
     aria-labelledby="addressMapPickerTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="addressMapPickerTitle">Chọn điểm trên bản đồ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="address-map-province-wrap d-none" id="address-map-province-wrap">
                    <label class="form-label small text-muted mb-1" for="address-map-province">Khu vực hoạt động</label>
                    <select id="address-map-province" class="form-select form-select-sm mb-2">
                        @include('partials.province-options', ['selected' => ''])
                    </select>
                </div>
                <label class="form-label small text-muted mb-1" for="address-map-search">Tìm địa chỉ</label>
                <input type="search" class="form-control form-control-sm mb-2" id="address-map-search"
                       placeholder="Nhập tên đường, khu vực..." autocomplete="off">
                <div id="address-map-search-results" class="address-map-search-results d-none" role="listbox"></div>
                <div class="address-map-toolbar d-none" id="address-map-driver-toolbar">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="address-map-my-location">
                        Vị trí của tôi
                    </button>
                </div>
                <div id="address-map-canvas" class="address-map-canvas" aria-label="Bản đồ chọn điểm"></div>
                <p class="address-map-preview small mb-0 mt-2" id="address-map-preview">
                    Tìm địa chỉ để bay tới khu vực, kéo ghim đến đúng điểm đón, rồi bấm Xác nhận.
                </p>
                <p class="address-map-confirm-status small text-muted mb-0 mt-1 d-none" id="address-map-confirm-status"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary btn-sm" id="address-map-confirm" disabled>Xác nhận</button>
            </div>
        </div>
    </div>
</div>
