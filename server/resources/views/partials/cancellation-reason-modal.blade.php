<div class="modal fade" id="cancellationReasonModal" tabindex="-1" aria-labelledby="cancellationReasonModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancellationReasonModalTitle">Chọn lý do hủy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3" id="cancellationReasonModalHint"></p>
                <div id="cancellationReasonModalList" class="cancellation-reason-list"></div>
                <div class="mt-2 d-none" id="cancellationReasonModalNoteWrap">
                    <label class="form-label small mb-1" for="cancellationReasonModalNote">Nhập lý do</label>
                    <input type="text"
                           class="form-control form-control-sm"
                           id="cancellationReasonModalNote"
                           maxlength="160"
                           autocomplete="off"
                           placeholder="Ví dụ: đổi giờ đi, nhầm điểm đón…">
                </div>
                <div class="text-danger small mt-2 d-none" id="cancellationReasonModalError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Huỷ</button>
                <button type="button" class="btn btn-danger" id="cancellationReasonModalConfirm" disabled>Xác nhận hủy</button>
            </div>
        </div>
    </div>
</div>
