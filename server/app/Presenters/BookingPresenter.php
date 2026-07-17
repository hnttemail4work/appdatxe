<?php

namespace App\Presenters;

use App\Models\Booking;
use App\Models\ReferralCode;

/**
 * Rút toàn bộ logic hiển thị (nhãn tiếng Việt, màu badge) của Booking ra khỏi
 * Model — Model chỉ giữ business logic, Presenter chỉ giữ "hiển thị thế nào".
 * Các method trên Booking vẫn còn (delegate sang đây) để không phải sửa lại
 * toàn bộ Blade/Controller đang gọi $booking->primaryStatusLabel() v.v.
 */
class BookingPresenter
{
    public function __construct(private readonly Booking $booking)
    {
    }

    public function passengerGenderLabel(): string
    {
        return ($this->booking->passenger_gender ?? 'male') === 'female' ? 'Nữ' : 'Nam';
    }

    public function passengerAgeLabel(): ?string
    {
        if ($this->booking->passenger_age === null) {
            return null;
        }

        return $this->booking->passenger_age . ' tuổi';
    }

    public function passengerProfileDetail(): string
    {
        $parts = [$this->passengerGenderLabel()];
        if ($age = $this->passengerAgeLabel()) {
            $parts[] = $age;
        }

        return implode(', ', $parts);
    }

    public function cancelledByLabel(): ?string
    {
        if (! in_array($this->booking->booking_status, ['cancelled', 'rejected'], true)
            && $this->booking->trip_status !== 'cancelled') {
            return null;
        }

        return match ($this->booking->cancelled_by) {
            'customer' => 'Khách hủy',
            'driver'   => 'Tài xế hủy',
            'system'   => 'Hệ thống chặn',
            default    => 'Đã hủy',
        };
    }

    public function pickupLabel(): string
    {
        $city = trim((string) $this->booking->pickup_address);
        $detail = trim((string) $this->booking->pickup_detail);

        if ($city !== '' && $detail !== '') {
            return $city . ', ' . $detail;
        }

        return $detail !== '' ? $detail : ($city !== '' ? $city : '—');
    }

    /** Chỉ chi tiết đón — không ghép tỉnh/thành phố. */
    public function driverPickupDetailLabel(): string
    {
        $detail = trim((string) $this->booking->pickup_detail);
        if ($detail !== '') {
            return $detail;
        }

        $city = trim((string) $this->booking->pickup_address);

        return $city !== '' ? $city : 'liên hệ khách';
    }

    public function pickupTimeLabel(): ?string
    {
        if (! $this->booking->pickup_time) {
            return null;
        }

        return \App\Support\DepartureTimeDisplay::label($this->booking->pickup_time);
    }

    /** Ngày đón thực tế — đồng bộ với trang chuyến khách. */
    public function driverPickupDateLabel(): ?string
    {
        return $this->booking->guestPickupAt()?->format('d/m/Y');
    }

    /** Giờ đón · ngày đón cho màn tài xế. */
    public function driverPickupScheduleLabel(): ?string
    {
        $at = $this->booking->guestPickupAt();
        if (! $at instanceof \Carbon\Carbon) {
            return null;
        }

        $time = $this->pickupTimeLabel();
        $date = $at->format('d/m/Y');

        if ($time) {
            return $time . ' · ' . $date;
        }

        return $at->format('H:i') . ' · ' . $date;
    }

    /** Chỉ chi tiết trả — nếu trống thì báo liên hệ khách. */
    public function driverDropoffDetailLabel(): string
    {
        $detail = trim((string) $this->booking->dropoff_detail);
        if ($detail !== '') {
            return $detail;
        }

        return 'liên hệ khách';
    }

    public function dropoffLabel(): string
    {
        $city = trim((string) $this->booking->dropoff_address);
        $detail = trim((string) $this->booking->dropoff_detail);

        if ($city !== '' && $detail !== '') {
            return $city . ', ' . $detail;
        }

        return $detail !== '' ? $detail : ($city !== '' ? $city : '—');
    }

    public function referralDiscountLabel(): ?string
    {
        $this->booking->loadMissing('appliedReferralCode');
        $applied = $this->booking->appliedReferralCode;
        if (! $applied || $applied->type !== ReferralCode::TYPE_BOOKING_TEMP) {
            return null;
        }

        $percent = $applied->customerDiscountPercent();
        if ($percent <= 0) {
            return null;
        }

        $formatted = rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.');

        return 'Giảm ' . $formatted . '% (' . $applied->customerDiscountSourceLabel() . ')';
    }

    public function vehicleBookingLabel(): string
    {
        $this->booking->loadMissing('schedule.vehicle');

        return \App\Support\VehicleDisplay::labelFromVehicle($this->booking->schedule?->vehicle);
    }

    /** Nhãn trạng thái thống nhất — luồng mới: khách → tài xế nhận → thu tiền trực tiếp. */
    public function primaryStatusLabel(): string
    {
        $booking = $this->booking;

        if ($booking->isExpired()) {
            return 'Hết hạn';
        }

        if ($booking->booking_status === 'cancelled') {
            return 'Đã hủy';
        }

        if ($booking->booking_status === 'rejected') {
            return 'Từ chối';
        }

        if ($booking->trip_status === 'completed') {
            return 'Hoàn tất';
        }

        if (! $booking->hasDriverAccepted()) {
            return $this->awaitingDriverLabel();
        }

        $booking->loadMissing('schedule');
        if ($booking->schedule?->driver_id) {
            return $booking->schedule->bookingStatusLabel();
        }

        return 'Sắp chạy';
    }

    /** Nhãn theo dõi trên dashboard quản lý — đồng bộ khách / tài xế. */
    public function operatorMonitorLabel(): string
    {
        $booking = $this->booking;

        if ($booking->isExpired()) {
            return 'Hết hạn';
        }

        if ($booking->booking_status === 'cancelled') {
            return 'Đã hủy';
        }

        if ($booking->booking_status === 'rejected') {
            return 'Từ chối';
        }

        if ($booking->trip_status === 'completed') {
            if ($booking->showsLaterPickupReminder()) {
                return 'Nhắc đón khách';
            }

            return 'Hoàn thành';
        }

        if ($booking->needs_operator_help_at) {
            return match ($booking->operator_help_reason) {
                'driver_invite_timeout'    => 'TX không nhận — cần gán lại',
                'driver_late_no_show'      => 'Quá giờ đón — cần gán lại',
                'driver_movement_timeout'  => 'TX chưa xác nhận đi đón',
                'driver_cancelled_trip'    => 'TX hủy cuốc — cần gán lại',
                default                    => 'Cần quản lý xử lý',
            };
        }

        $acceptance = $booking->driverAcceptanceState();
        if ($acceptance === 'none') {
            if ($booking->adminReleasedAfterDriverEngagement()) {
                return $booking->adminStillSearchingReplacementDriver()
                    ? 'Đang tìm tài xế khác'
                    : 'TX hủy cuốc — cần gán lại';
            }

            return $this->awaitingDriverLabel();
        }

        if ($acceptance === 'pending') {
            return 'Chờ TX nhận cuốc';
        }

        $booking->loadMissing('schedule');
        if ($booking->schedule?->driver_id) {
            return $booking->schedule->bookingStatusLabel();
        }

        return 'TX đã nhận';
    }

    public function primaryStatusColor(): string
    {
        return $this->statusColorForLabel($this->primaryStatusLabel());
    }

    public function operatorMonitorColor(): string
    {
        $booking = $this->booking;

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return \App\Support\StatusBadge::DANGER;
        }

        if ($booking->isExpired()) {
            return \App\Support\StatusBadge::NEUTRAL;
        }

        if ($booking->trip_status === 'completed') {
            if ($booking->showsLaterPickupReminder()) {
                return \App\Support\StatusBadge::ACCENT;
            }

            return \App\Support\StatusBadge::SUCCESS;
        }

        if ($booking->needs_operator_help_at) {
            return \App\Support\StatusBadge::DANGER;
        }

        $acceptance = $booking->driverAcceptanceState();
        if ($acceptance === 'none') {
            return \App\Support\StatusBadge::PENDING;
        }

        if ($acceptance === 'pending') {
            return \App\Support\StatusBadge::ACCENT;
        }

        $booking->loadMissing('schedule');

        return $booking->schedule?->driver_id
            ? $booking->schedule->bookingStatusColor()
            : \App\Support\StatusBadge::INFO;
    }

    private function statusColorForLabel(string $label): string
    {
        if ($label === 'Hết hạn') {
            return \App\Support\StatusBadge::NEUTRAL;
        }

        return match ($label) {
            'Đã hủy', 'Từ chối'     => \App\Support\StatusBadge::DANGER,
            'Hoàn thành'            => \App\Support\StatusBadge::SUCCESS,
            'Đang phục vụ', 'Đang chạy', 'Đã đón khách', 'Tài xế đã đến điểm đón', 'Tài xế đang đi đón', 'Đã có tài xế' => \App\Support\StatusBadge::GOLD,
            'Chờ QL xác nhận', 'Chờ tài xế nhận', 'Đang tìm tài xế', 'Đang tìm tài xế khác', 'TX hủy — cần gán lại', 'TX hủy cuốc — cần gán lại' => \App\Support\StatusBadge::PENDING,
            'Cần QL hỗ trợ'        => \App\Support\StatusBadge::DANGER,
            default                 => \App\Support\StatusBadge::NEUTRAL,
        };
    }

    public function tripDisplayLabel(): ?string
    {
        $booking = $this->booking;

        if ($booking->isExpired()) {
            return null;
        }

        return match ($booking->trip_status) {
            'completed'           => 'Hoàn tất',
            'awaiting_completion' => 'Chờ xác nhận hoàn',
            'cancelled'           => 'Đã hủy',
            'confirmed'           => $this->scheduleTripPhaseLabel(),
            default               => null,
        };
    }

    public function tripDisplayColor(): string
    {
        if ($this->booking->isExpired()) {
            return \App\Support\StatusBadge::NEUTRAL;
        }

        $label = $this->tripDisplayLabel();

        return match ($label) {
            'Hoàn tất', 'Chạy xong' => \App\Support\StatusBadge::SUCCESS,
            'Chờ xác nhận hoàn'     => \App\Support\StatusBadge::INFO,
            'Đã hủy'                => \App\Support\StatusBadge::DANGER,
            'Đang chạy'             => \App\Support\StatusBadge::GOLD,
            'Sắp chạy'             => \App\Support\StatusBadge::PENDING,
            default                 => \App\Support\StatusBadge::NEUTRAL,
        };
    }

    private function scheduleTripPhaseLabel(): ?string
    {
        $booking = $this->booking;
        $booking->loadMissing('schedule');

        if (! $booking->schedule) {
            return 'Sắp chạy';
        }

        if ($booking->schedule->driver_id) {
            return $booking->schedule->bookingStatusLabel();
        }

        return match ($booking->schedule->displayStatus()) {
            'completed' => 'Chạy xong',
            'running'   => 'Đang chạy',
            default     => 'Sắp chạy',
        };
    }

    private function awaitingDriverLabel(): string
    {
        $booking = $this->booking;
        $booking->loadMissing('schedule.template', 'schedule.driverTripRequests');

        if ($booking->driverAcceptanceState() === 'pending') {
            return 'Chờ tài xế';
        }

        if ($booking->adminReleasedAfterDriverEngagement()) {
            if ($booking->adminStillSearchingReplacementDriver()) {
                return 'Đang tìm tài xế khác';
            }

            return 'TX hủy — cần gán lại';
        }

        return 'Đang tìm tài xế';
    }

    /** @return array{label: string, color: string, can_nudge: bool}|null */
    public function adminDriverDispatchDetail(): ?array
    {
        $booking = $this->booking;

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return null;
        }

        if ($booking->trip_status === 'completed') {
            return null;
        }

        $state = $booking->driverAcceptanceState();
        if ($state === 'none') {
            return null;
        }

        $hasPush = $booking->assignedDriverHasPushSubscription();
        $sharesLocation = $booking->assignedDriverSharesLiveLocation();
        $pushReady = \App\Support\PushNotificationSettings::isEnabled()
            && \App\Support\PushNotificationSettings::vapidKeys() !== null
            && \App\Support\PushNotificationSettings::isEventEnabled('driver.new_trip_request');

        if ($state === 'pending') {
            if ($hasPush) {
                return [
                    'label'     => $pushReady ? 'Đã gửi TB · chờ TX nhận' : 'TB đẩy chưa sẵn sàng',
                    'color'     => $pushReady ? 'pending' : 'neutral',
                    'can_nudge' => $pushReady,
                ];
            }

            if ($sharesLocation) {
                return [
                    'label'     => 'TX đã chia sẻ vị trí · chờ nhận',
                    'color'     => 'success',
                    'can_nudge' => false,
                ];
            }

            return [
                'label'     => 'TX chưa bật app',
                'color'     => 'danger',
                'can_nudge' => false,
            ];
        }

        $driverName = trim((string) ($booking->schedule?->driver_name ?? ''));
        if ($driverName === '') {
            $driverName = 'Tài xế';
        }

        $suffix = match (true) {
            $sharesLocation => ' · đã chia sẻ vị trí',
            $hasPush        => ' · đã bật app',
            default         => ' · chưa bật app',
        };

        return [
            'label'     => 'TX đã nhận cuốc · ' . $driverName . $suffix,
            'color'     => $sharesLocation ? 'success' : ($hasPush ? 'info' : 'neutral'),
            'can_nudge' => false,
        ];
    }
}
