@php
$emergencyPhone = config('app.contact_phone');
$emergencyPhoneTel = preg_replace('/[^\d+]/', '', $emergencyPhone);
@endphp
<button type="button" class="btn btn-sm btn-outline-danger driver-emergency-call-btn share-booking-btn-icon"
        data-bs-toggle="modal" data-bs-target="#driverEmergencyCallModal"
        aria-label="Tổng đài gọi yêu cầu hỗ trợ" title="Tổng đài gọi yêu cầu hỗ trợ">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
    </svg>
</button>

<div class="modal fade" id="driverEmergencyCallModal" tabindex="-1" aria-labelledby="driverEmergencyCallModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="driverEmergencyCallModalLabel">Tổng đài gọi yêu cầu hỗ trợ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body text-center pt-2 pb-4">
                <a href="tel:{{ $emergencyPhoneTel }}" class="driver-emergency-phone display-6 fw-bold text-decoration-none">
                    {{ $emergencyPhone }}
                </a>
                <div class="mt-3">
                    <a href="tel:{{ $emergencyPhoneTel }}" class="btn btn-danger btn-sm px-4">Gọi ngay</a>
                </div>
            </div>
        </div>
    </div>
</div>
