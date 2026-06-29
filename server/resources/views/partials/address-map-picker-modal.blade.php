<div class="modal fade address-map-picker-modal" id="addressMapPickerModal" tabindex="-1"
     aria-labelledby="addressMapPickerTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="addressMapPickerTitle">Chọn điểm trên bản đồ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body pt-2">
                <label class="form-label small text-muted mb-1" for="address-map-search">Tìm địa chỉ</label>
                <input type="search" class="form-control form-control-sm mb-2" id="address-map-search"
                       placeholder="Nhập tên đường, khu vực..." autocomplete="off">
                <div id="address-map-search-results" class="address-map-search-results d-none" role="listbox"></div>
                <div id="address-map-canvas" class="address-map-canvas" aria-label="Bản đồ chọn điểm"></div>
                <p class="address-map-preview small mb-0 mt-2" id="address-map-preview">
                    Gõ tìm hoặc chạm bản đồ — địa chỉ tự điền và đóng.
                </p>
            </div>
        </div>
    </div>
</div>
