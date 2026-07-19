<section id="booking-flow" class="be-booking-flow d-none" aria-label="Đặt xe" hidden>
    <form method="POST" action="{{ route('booking.store') }}" id="booking-form" class="be-booking-flow__form" enctype="multipart/form-data" novalidate>
        @csrf
        <input type="hidden" name="capacity" id="modal-capacity">
        <input type="hidden" name="vehicle_type" id="modal-vehicle-type">
        <input type="hidden" name="booking_browser_id" id="booking-browser-id" value="">
        <input type="hidden" name="pickup_address" id="modal-pickup-address" value="{{ old('pickup_address') }}">
        <input type="hidden" name="dropoff_address" id="modal-dropoff-address" value="{{ old('dropoff_address') }}">
        <input type="hidden" name="notes" id="modal-notes-value" value="{{ old('notes') }}">

        {{-- Bước xác nhận điểm đón (ảnh 12) --}}
        <div id="booking-step-pickup" class="be-step be-step--pickup">
            <div class="be-step__map">
                <button type="button" class="be-step__map-back" data-modal-back aria-label="Quay lại">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button type="button" class="be-step__map-locate" id="booking-pickup-recenter" aria-label="Về vị trí điểm đón" title="Định vị">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>
                </button>
                <div class="be-step__map-canvas" id="booking-pickup-map-canvas" aria-label="Bản đồ điểm đón"></div>
            </div>
            <div class="be-step__sheet">
                <div class="be-pickup-addr be-pickup-addr--static" id="booking-pickup-addr-btn">
                    <span class="be-pickup-addr__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>
                    </span>
                    <span class="be-pickup-addr__text">
                        <strong id="booking-pickup-confirm-addr">—</strong>
                    </span>
                </div>
                <div class="be-pickup-nearby" id="booking-pickup-nearby" aria-label="Điểm đón gần đây trong 300m"></div>
                <button type="button" class="be-driver-note" id="booking-note-toggle" aria-expanded="false">
                    <span class="be-driver-note__icon" aria-hidden="true">✎</span>
                    <span class="be-driver-note__label" id="booking-note-label">Thêm ghi chú cho bác tài</span>
                </button>
                <div class="be-driver-note__editor d-none" id="booking-note-editor">
                    <textarea id="modal-notes" class="form-control" rows="2" maxlength="500"
                              placeholder="Ví dụ: đón ở cổng phụ…">{{ old('notes') }}</textarea>
                </div>
                <button type="button" class="btn btn-primary w-100 fw-semibold be-step__cta" id="booking-confirm-pickup-btn">
                    Xác nhận điểm đón
                </button>
            </div>
        </div>

        {{-- Bước map tuyến + chọn xe (ảnh 13) --}}
        <div id="booking-step-vehicle" class="be-step be-step--vehicle d-none">
            <div class="be-step__map be-step__map--route">
                <div class="be-step__route-chip" id="booking-flow-route-banner">
                    <button type="button" class="be-step__route-back" data-modal-back aria-label="Quay lại">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div class="be-step__route-rail" aria-hidden="true">
                        <span class="be-step__route-dot be-step__route-dot--pickup"></span>
                        <span class="be-step__route-line"></span>
                        <span class="be-step__route-dot be-step__route-dot--dropoff"></span>
                    </div>
                    <div class="be-step__route-ends">
                        <div class="be-step__route-end">
                            <span class="be-step__route-label">Đón</span>
                            <span class="be-step__route-addr" id="modal-route-pickup">—</span>
                        </div>
                        <div class="be-step__route-end">
                            <span class="be-step__route-label">Trả</span>
                            <span class="be-step__route-addr" id="modal-route-dropoff">—</span>
                        </div>
                    </div>
                    <div class="be-step__route-side">
                        <button type="button" class="be-step__route-swap" id="booking-route-swap" aria-label="Đảo điểm đi và điểm đến" title="Đảo điểm">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                <path d="M7 7h11l-3-3M17 17H6l3 3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <span class="be-step__route-dist" id="modal-route-distance"></span>
                    </div>
                </div>
                <div class="be-step__map-canvas" id="booking-flow-map-canvas" aria-label="Bản đồ lộ trình"></div>
            </div>
            <div class="be-step__sheet be-step__sheet--vehicle is-collapsed"
                 id="booking-vehicle-sheet"
                 data-vehicle-expanded="false">
                <div class="be-sheet-handle"
                     id="booking-vehicle-sheet-handle"
                     role="button"
                     tabindex="0"
                     aria-label="Vuốt xuống để thu gọn, vuốt lên để xem thêm loại xe"
                     aria-controls="trips-list">
                    <span class="be-sheet-handle__pill" aria-hidden="true"></span>
                </div>
                <div class="be-step__sheet-scroll">
                    <div id="booking-modal-flash" class="app-flash-stack" aria-live="polite" aria-atomic="true"></div>
                    @include('partials.booking-vehicle-select', ['driverOffers' => $driverOffers ?? collect()])

                    <div class="be-pay-bar">
                        <div class="be-pay-dropdown" data-pay-dropdown>
                            <button type="button"
                                    class="be-pay-dropdown__btn"
                                    id="booking-pay-method-btn"
                                    aria-expanded="false"
                                    aria-haspopup="listbox"
                                    aria-controls="booking-pay-method-menu">
                                <span class="be-pay-dropdown__label" data-pay-label>Tiền mặt</span>
                                <span class="be-pay-dropdown__chevron" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                        <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </button>
                            <div class="be-pay-dropdown__menu" id="booking-pay-method-menu" role="listbox" hidden>
                                <button type="button" class="be-pay-dropdown__option is-active" role="option" data-pay-value="cash" aria-selected="true">Tiền mặt</button>
                                <button type="button" class="be-pay-dropdown__option" role="option" data-pay-value="bank_transfer" aria-selected="false">Chuyển khoản</button>
                            </div>
                            <input type="hidden" name="payment_method" id="booking-payment-method" value="cash">
                        </div>
                        <div id="booking-pay-transfer" class="booking-pay-transfer d-none mt-2">
                            @include('partials.company-bank-transfer', [
                                'amount' => 0,
                                'dynamicAmount' => true,
                                'qrElementId' => 'booking-transfer-qr',
                                'addInfo' => \App\Support\PlatformPaymentInfo::driverTransferContent(auth()->user()?->phone),
                                'hideBankDetails' => false,
                            ])
                            <label class="form-label mt-2 mb-1" for="booking-payment-proof">Ảnh chuyển khoản <span class="text-danger">*</span></label>
                            <input type="file" name="payment_proof" id="booking-payment-proof"
                                   class="form-control form-control-sm"
                                   accept="image/jpeg,image/png,image/webp,image/gif"
                                   capture="environment">
                            <div class="booking-pay-proof-preview d-none mt-2" id="booking-pay-proof-preview" hidden>
                                <img src="" alt="Xem trước" id="booking-pay-proof-preview-img" class="booking-pay-proof-preview__img">
                            </div>
                        </div>
                    </div>

                </div>
                <div class="be-step__sheet-foot">
                    <button type="submit" class="btn btn-primary w-100 fw-semibold be-step__cta" id="modal-submit-btn" disabled>
                        Chọn loại xe
                    </button>
                </div>
            </div>
        </div>
    </form>
</section>

@include('partials.address-map-picker-modal')
