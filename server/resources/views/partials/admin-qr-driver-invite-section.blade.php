@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\DriverProfile> $assignableDrivers */
    $assignableDrivers = $assignableDrivers ?? collect();
    /** @var \App\Models\DriverProfile|null $inviteDriver */
    $inviteDriver = $inviteDriver ?? null;
@endphp

<section class="mt-4 pt-4 border-top" aria-label="QR mời tài xế">
    <h3 class="h6 fw-semibold mb-2">QR mời tài xế</h3>
    <p class="text-muted small mb-3">Tạo / ngưng / mở lại QR giảm giá gắn với từng tài xế đã duyệt. App tài xế vẫn hiện QR khi đang dùng.</p>

    <form method="GET" action="{{ route('admin.referrals') }}" class="console-form mb-3">
        <input type="hidden" name="tab" value="codes">
        <div class="row g-3 align-items-end">
            <div class="col-md-6 col-lg-5">
                <label class="form-label" for="invite-driver-select">Chọn tài xế</label>
                <select name="invite_driver" id="invite-driver-select" class="form-select" onchange="this.form.submit()">
                    <option value="">— Chọn tài xế —</option>
                    @foreach($assignableDrivers as $driver)
                        <option value="{{ $driver->id }}" @selected($inviteDriver && (int) $inviteDriver->id === (int) $driver->id)>
                            {{ $driver->driver_code ?: ('TX#'.$driver->id) }}
                            @if($driver->user?->phone)
                                — {{ $driver->user->phone }}
                            @endif
                            @if($driver->user?->preferredDisplayName())
                                ({{ $driver->user->preferredDisplayName() }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    @if($inviteDriver)
        @include('partials.admin-driver-invite-panel', [
            'driver' => $inviteDriver,
            'inviteReferral' => $inviteReferral ?? null,
            'commissionReferral' => $commissionReferral ?? null,
            'inviteFrom' => 'qr',
        ])
    @endif
</section>
