<?php

namespace App\Models;

use App\Support\PlatformFees;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Booking extends Model
{
    protected static function booted(): void
    {
        static::updated(function (Booking $booking): void {
            if (! $booking->wasChanged(['booking_status', 'trip_status'])) {
                return;
            }

            $cancelled = in_array($booking->booking_status, ['cancelled', 'rejected'], true)
                || $booking->trip_status === 'cancelled';

            if ($cancelled) {
                app(\App\Services\ReferralCodeService::class)->purgeForBooking($booking);
            }
        });
    }

    protected $fillable = [
        'contact_phone',
        'passenger_name',
        'passenger_gender',
        'passenger_age',
        'schedule_id',
        'assigned_driver_id',
        'booking_reference',
        'applied_referral_code_id',
        'total_price',
        'payment_status',
        'trip_status',
        'booking_status',
        'pickup_address',
        'pickup_detail',
        'pickup_lat',
        'pickup_lng',
        'driver_pickup_distance_km',
        'pickup_time',
        'dropoff_address',
        'dropoff_detail',
        'dropoff_lat',
        'dropoff_lng',
        'notes',
        'operator_confirmed_at',
        'hold_expires_at',
        'driver_search_started_at',
        'needs_operator_help_at',
        'operator_help_reason',
        'operator_dismissed_at',
        'repeat_cancel_flag',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason_id',
        'cancellation_reason_label',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'passenger_age'  => 'integer',
            'pickup_lat'     => 'float',
            'pickup_lng'     => 'float',
            'dropoff_lat'    => 'float',
            'dropoff_lng'    => 'float',
            'driver_pickup_distance_km' => 'float',
            'total_price'    => 'decimal:2',
            'hold_expires_at' => 'datetime',
            'driver_search_started_at' => 'datetime',
            'needs_operator_help_at' => 'datetime',
            'operator_dismissed_at' => 'datetime',
            'repeat_cancel_flag' => 'boolean',
            'confirmed_at'   => 'datetime',
            'completed_at'   => 'datetime',
            'cancelled_at'   => 'datetime',
            'expired_at'     => 'datetime',
            'operator_confirmed_at' => 'datetime',
        ];
    }

    public function contactPhone(): ?string
    {
        return $this->contact_phone;
    }

    public function matchesContactPhone(string $phone): bool
    {
        $stored = \App\Support\AuthIdentifier::normalizePhone((string) $this->contact_phone);
        $given = \App\Support\AuthIdentifier::normalizePhone($phone);

        return $stored !== '' && $stored === $given;
    }

    public function passengerGenderLabel(): string
    {
        return ($this->passenger_gender ?? 'male') === 'female' ? 'Nữ' : 'Nam';
    }

    public function passengerAgeLabel(): ?string
    {
        if ($this->passenger_age === null) {
            return null;
        }

        return $this->passenger_age . ' tuổi';
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
        if (! in_array($this->booking_status, ['cancelled', 'rejected'], true)
            && $this->trip_status !== 'cancelled') {
            return null;
        }

        return match ($this->cancelled_by) {
            'customer' => 'Khách hủy',
            'driver'   => 'Tài xế hủy',
            'system'   => 'Hệ thống chặn',
            default    => 'Đã hủy',
        };
    }

    public function pickupLabel(): string
    {
        $city = trim((string) $this->pickup_address);
        $detail = trim((string) $this->pickup_detail);

        if ($city !== '' && $detail !== '') {
            return $city . ', ' . $detail;
        }

        return $detail !== '' ? $detail : ($city !== '' ? $city : '—');
    }

    /** Chỉ chi tiết đón — không ghép tỉnh/thành phố. */
    public function driverPickupDetailLabel(): string
    {
        $detail = trim((string) $this->pickup_detail);
        if ($detail !== '') {
            return $detail;
        }

        $city = trim((string) $this->pickup_address);

        return $city !== '' ? $city : 'liên hệ khách';
    }

    public function pickupTimeLabel(): ?string
    {
        if (! $this->pickup_time) {
            return null;
        }

        return \App\Support\DepartureTimeDisplay::label($this->pickup_time);
    }

    public function tripDistanceKm(): int
    {
        $this->loadMissing('schedule.route');

        $route = $this->schedule?->route;
        if ($route && (int) $route->distance_km > 0) {
            return (int) $route->distance_km;
        }

        $departure = trim((string) ($route?->departure ?: $this->pickup_address));
        $destination = trim((string) ($route?->destination ?: $this->dropoff_address));

        if ($departure === '' || $destination === '') {
            return 0;
        }

        return max(0, (int) \App\Support\RouteDistanceCatalog::resolveKm($departure, $destination));
    }

    public function tripStartAt(): ?\Carbon\Carbon
    {
        $this->loadMissing('schedule');

        if (! $this->schedule) {
            return null;
        }

        $serviceDate = $this->schedule->service_date
            ? \App\Support\ServiceDate::dayStart($this->schedule->service_date)
            : $this->schedule->departure_time->copy()->startOfDay();

        if ($this->pickup_time) {
            $clock = \App\Support\DepartureTimeDisplay::normalizeForClock($this->pickup_time);

            return \Carbon\Carbon::parse(
                $serviceDate->toDateString() . ' ' . $clock . ':00',
                config('app.timezone'),
            );
        }

        return $this->schedule->departure_time?->copy();
    }

    public function isPastPickupTime(): bool
    {
        $pickupAt = $this->tripStartAt();

        return $pickupAt !== null && $pickupAt->lte(now());
    }

    /**
     * Cuốc này còn chặn khách đặt thêm (SĐT / trình duyệt).
     * Cho đặt lại khi: đã hủy, hoàn tất, hết hạn, admin ẩn, hoặc đã qua giờ đón.
     */
    public function blocksGuestRebooking(): bool
    {
        if (in_array($this->booking_status, ['cancelled', 'rejected'], true)) {
            return false;
        }

        if (in_array($this->trip_status, ['completed', 'cancelled'], true)) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if (static::supportsOperatorDismiss() && $this->isOperatorDismissed()) {
            return false;
        }

        if ($this->isPastPickupTime()) {
            return false;
        }

        return true;
    }

    public function expectedTripDurationMinutes(): int
    {
        $km = $this->tripDistanceKm();

        if ($km <= 0) {
            return 120;
        }

        return (int) ceil($km / \App\Services\DriverMovementConfirmService::ASSUMED_SPEED_KMH * 60);
    }

    public function expectedTripCompletionAt(): ?\Carbon\Carbon
    {
        $start = $this->tripStartAt();

        if (! $start) {
            return null;
        }

        return $start->copy()->addMinutes($this->expectedTripDurationMinutes());
    }

    public function isPastExpectedCompletion(): bool
    {
        $expected = $this->expectedTripCompletionAt();

        return $expected !== null && now()->greaterThan($expected);
    }

    public function isOperatorDismissed(): bool
    {
        return $this->operator_dismissed_at !== null;
    }

    /** Chỉ chi tiết trả — nếu trống thì báo liên hệ khách. */
    public function driverDropoffDetailLabel(): string
    {
        $detail = trim((string) $this->dropoff_detail);
        if ($detail !== '') {
            return $detail;
        }

        return 'liên hệ khách';
    }

    public function dropoffLabel(): string
    {
        $city = trim((string) $this->dropoff_address);
        $detail = trim((string) $this->dropoff_detail);

        if ($city !== '' && $detail !== '') {
            return $city . ', ' . $detail;
        }

        return $detail !== '' ? $detail : ($city !== '' ? $city : '—');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function assignedDriver()
    {
        return $this->belongsTo(User::class, 'assigned_driver_id');
    }

    public function stampAssignedDriver(?int $driverUserId): void
    {
        if (! $driverUserId || ! Schema::hasColumn('bookings', 'assigned_driver_id')) {
            return;
        }

        if ($this->assigned_driver_id === null) {
            $this->update(['assigned_driver_id' => $driverUserId]);
        }
    }

    /** Tài xế đã gắn với vé — từ cột, chuyến, hoặc yêu cầu nhận chuyến. */
    public function resolveAssignedDriverId(?Schedule $schedule = null): ?int
    {
        if ($this->assigned_driver_id) {
            return (int) $this->assigned_driver_id;
        }

        $schedule ??= $this->relationLoaded('schedule') ? $this->schedule : $this->schedule()->first();

        if ($schedule?->driver_id) {
            return (int) $schedule->driver_id;
        }

        if (! $this->schedule_id || ! $this->contact_phone) {
            return null;
        }

        $fromRequest = DriverTripRequest::query()
            ->where('schedule_id', $this->schedule_id)
            ->where('contact_phone', $this->contact_phone)
            ->whereIn('status', ['accepted', 'pending', 'cancelled'])
            ->orderByDesc('updated_at')
            ->value('driver_id');

        return $fromRequest ? (int) $fromRequest : null;
    }

    /** Vé hiển thị tab lịch sử tài xế — không gồm khách hủy (quản lý xem riêng). */
    public function isVisibleInDriverHistory(): bool
    {
        if ($this->trip_status === 'completed') {
            return true;
        }

        return $this->trip_status === 'cancelled'
            && $this->cancelled_by === 'driver';
    }

    public function isTerminalForDriverHistory(): bool
    {
        return $this->isVisibleInDriverHistory();
    }

    public function scopeTerminalForDriverHistory(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('trip_status', 'completed')
                ->orWhere(function (Builder $q2): void {
                    $q2->where('trip_status', 'cancelled')
                        ->where('cancelled_by', 'driver');
                });
        });
    }

    public function scopeVisibleInDriverHistory(Builder $query): Builder
    {
        return $query->terminalForDriverHistory();
    }

    public function scopeAssignedToDriver(Builder $query, int $driverUserId): Builder
    {
        return $query->where(function (Builder $q) use ($driverUserId): void {
            if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $q->where('assigned_driver_id', $driverUserId)
                    ->orWhere(function (Builder $q2) use ($driverUserId): void {
                        $q2->whereNull('assigned_driver_id')
                            ->whereHas('schedule', fn (Builder $s) => $s->where('driver_id', $driverUserId));
                    })
                    ->orWhere(function (Builder $q2) use ($driverUserId): void {
                        $q2->whereNull('assigned_driver_id')
                            ->whereExists(function ($sub) use ($driverUserId): void {
                                $sub->select(DB::raw(1))
                                    ->from('driver_trip_requests')
                                    ->whereColumn('driver_trip_requests.schedule_id', 'bookings.schedule_id')
                                    ->whereColumn('driver_trip_requests.contact_phone', 'bookings.contact_phone')
                                    ->where('driver_trip_requests.driver_id', $driverUserId)
                                    ->whereIn('driver_trip_requests.status', ['accepted', 'pending', 'cancelled']);
                            });
                    });
            } else {
                $q->whereHas('schedule', fn (Builder $s) => $s->where('driver_id', $driverUserId));
            }
        });
    }

    /** Vé còn được tính là có khách trên chuyến (không hủy / hết hạn). */
    public function scopeValidForTrip(Builder $query): Builder
    {
        return $query
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->whereNull('expired_at');
    }

    public function tripReview()
    {
        return $this->hasOne(TripReview::class);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isConfirmedForDriver(): bool
    {
        return $this->booking_status === 'confirmed'
            && $this->payment_status === 'paid'
            && $this->trip_status === 'confirmed';
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null;
    }

    public function referralCode()
    {
        return $this->hasOne(ReferralCode::class)->where('type', ReferralCode::TYPE_BOOKING_TEMP);
    }

    public function appliedReferralCode()
    {
        return $this->belongsTo(ReferralCode::class, 'applied_referral_code_id');
    }

    public function tripRevenueAmount(): int
    {
        return (int) round((float) $this->total_price);
    }

    public function platformFeeAmount(): int
    {
        return $this->projectedPlatformFeeAmount();
    }

    public function projectedPlatformFeeAmount(): int
    {
        if ($this->tripRevenueAmount() <= 0) {
            return 0;
        }

        return (int) round($this->tripRevenueAmount() * PlatformFees::appCommissionPercent() / 100);
    }

    public function projectedReferrerCommissionAmount(): int
    {
        $this->loadMissing('appliedReferralCode');
        if (! $this->appliedReferralCode
            || $this->appliedReferralCode->type !== ReferralCode::TYPE_REFERRER) {
            return 0;
        }

        return (int) round($this->tripRevenueAmount() * $this->appliedReferralCode->commissionPercent() / 100);
    }

    public function referrerCommissionAmount(): int
    {
        if ($this->trip_status !== 'completed') {
            return 0;
        }

        return $this->projectedReferrerCommissionAmount();
    }

    public function referralCommissionAmount(): int
    {
        return $this->referrerCommissionAmount();
    }

    public function referralDiscountLabel(): ?string
    {
        $this->loadMissing('appliedReferralCode');
        if (! $this->appliedReferralCode
            || $this->appliedReferralCode->type !== ReferralCode::TYPE_BOOKING_TEMP) {
            return null;
        }

        $percent = $this->appliedReferralCode->customerDiscountPercent();
        if ($percent <= 0) {
            return null;
        }

        $formatted = rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.');

        return 'Giảm ' . $formatted . '% (mã QR)';
    }

    public function catalogChosenDriverProfile(): ?DriverProfile
    {
        $this->loadMissing('schedule.template');
        $driverUserId = (int) ($this->schedule?->template?->driver_id ?? 0);
        if ($driverUserId <= 0) {
            return null;
        }

        return DriverProfile::query()
            ->where('user_id', $driverUserId)
            ->with('user')
            ->first();
    }

    public function activeDriverProfile(): ?DriverProfile
    {
        $driverUserId = (int) ($this->resolveAssignedDriverId() ?? 0);
        if ($driverUserId <= 0) {
            return null;
        }

        return DriverProfile::query()
            ->where('user_id', $driverUserId)
            ->with('user')
            ->first();
    }

    /** @return array{level: string, label: string, detail: string}|null */
    public function adminPickupAlert(): ?array
    {
        return app(\App\Services\DriverLatePickupService::class)->adminAlertForBooking($this);
    }

    /** @return array{level: string, label: string, detail: string}|null */
    public function adminWalletTopUpAlert(): ?array
    {
        if (in_array($this->booking_status, ['cancelled', 'rejected'], true)) {
            return null;
        }

        if ($this->trip_status === 'completed') {
            return null;
        }

        $profile = $this->activeDriverProfile() ?? $this->catalogChosenDriverProfile();
        if (! $profile) {
            return null;
        }

        return app(\App\Services\DriverWalletService::class)->adminTopUpAlertFor($profile);
    }

    /** Khách chọn TX catalog nhưng TX chưa bật sẵn sàng — admin cần gán TX khác. */
    public function catalogDriverOffDutyAlert(): bool
    {
        if (in_array($this->booking_status, ['cancelled', 'rejected'], true)) {
            return false;
        }

        if ($this->hasDriverAccepted()) {
            return false;
        }

        $this->loadMissing('schedule.template');
        $templateDriverId = (int) ($this->schedule?->template?->driver_id ?? 0);
        if ($templateDriverId <= 0) {
            return false;
        }

        $driver = DriverProfile::query()->where('user_id', $templateDriverId)->first();
        if (! $driver) {
            return false;
        }

        return $driver->effectiveAvailabilityStatus() !== 'available';
    }

    public function scopeCatalogDriverOffDuty(Builder $query): Builder
    {
        return $query
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->whereHas('schedule', function (Builder $scheduleQuery): void {
                $scheduleQuery
                    ->whereNull('driver_id')
                    ->whereHas('template', fn (Builder $templateQuery) => $templateQuery->whereNotNull('driver_id'));
            });
    }

    public function audits()
    {
        return $this->hasMany(BookingAudit::class);
    }

    public function cancellationReason()
    {
        return $this->belongsTo(CancellationReason::class);
    }

    public function cancellationReasonText(): ?string
    {
        return $this->cancellation_reason_label
            ?: $this->cancellationReason?->label;
    }

    public function hasDriverAccepted(): bool
    {
        $this->loadMissing('schedule');

        return $this->resolveAssignedDriverId($this->schedule) !== null;
    }

    public function passengerPickedUp(): bool
    {
        $this->loadMissing('schedule');

        return (bool) $this->schedule?->passengerPickedUp();
    }

    /** Admin còn được gán / đổi tài xế hoặc hủy chuyến. */
    public function adminCanModifyDriverOrCancel(): bool
    {
        if (in_array($this->booking_status, ['cancelled', 'rejected'], true)) {
            return false;
        }

        if ($this->trip_status === 'completed') {
            return false;
        }

        return ! $this->passengerPickedUp();
    }

    public function adminWaitingMinutesRemaining(): ?int
    {
        $this->loadMissing('schedule');
        $schedule = $this->schedule;

        if (! $schedule) {
            return null;
        }

        $pendingRequest = DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', (string) $this->contact_phone)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($pendingRequest?->expires_at?->isFuture()) {
            return max(1, (int) now()->diffInMinutes($pendingRequest->expires_at, false));
        }

        if (! $schedule->driver_id && $this->driver_search_started_at) {
            $deadline = app(\App\Services\DriverTripRequestService::class)
                ->customerSearchStartedAt($this)
                ->copy()
                ->addMinutes(\App\Services\DriverTripRequestService::CUSTOMER_SEARCH_DEADLINE_MINUTES);

            if ($deadline->isFuture()) {
                return max(1, (int) now()->diffInMinutes($deadline, false));
            }
        }

        return null;
    }

    /** Chuyến đang chờ tài xế hoặc cần admin gán / gán lại TX. */
    public function needsAdminWaitingAttention(): bool
    {
        if (! $this->adminCanModifyDriverOrCancel()) {
            return false;
        }

        if ($this->adminWaitingMinutesRemaining() !== null) {
            return true;
        }

        $this->loadMissing('schedule');
        $schedule = $this->schedule;

        if (! $schedule) {
            return false;
        }

        if ($this->hasDriverAccepted()) {
            return $schedule->departure_time > now();
        }

        return true;
    }

    private function awaitingDriverLabel(): string
    {
        $this->loadMissing('schedule.template', 'schedule.driverTripRequests');

        if ($this->schedule?->driverTripRequests
            ?->where('status', 'pending')
            ->filter(fn ($request) => $request->expires_at === null || $request->expires_at->isFuture())
            ->isNotEmpty()) {
            return 'Chờ tài xế nhận';
        }

        if ($this->schedule?->designatedDriverProfile()) {
            return 'Chờ tài xế nhận';
        }

        return 'Đang tìm tài xế';
    }

    public function chargedTotal(): float
    {
        $stored = (float) $this->total_price;
        $this->loadMissing('schedule');

        if (! $this->schedule) {
            return $stored;
        }

        return app(\App\Services\TripPricingService::class)->bookingTotal(
            $this->schedule,
            $this->pickup_address,
            $this->dropoff_address,
            $this->pickup_lat,
            $this->pickup_lng,
            $this->dropoff_lat,
            $this->dropoff_lng,
        );
    }

    public function vehicleBookingLabel(): string
    {
        $this->loadMissing('schedule.vehicle');

        return \App\Support\VehicleDisplay::labelFromVehicle($this->schedule?->vehicle);
    }

    /** Nhãn trạng thái thống nhất — luồng mới: khách → tài xế nhận → thu tiền trực tiếp. */
    public function primaryStatusLabel(): string
    {
        if ($this->isExpired()) {
            return 'Hết hạn';
        }

        if ($this->booking_status === 'cancelled') {
            return 'Đã hủy';
        }

        if ($this->booking_status === 'rejected') {
            return 'Từ chối';
        }

        if ($this->trip_status === 'completed') {
            return 'Hoàn tất';
        }

        if (! $this->hasDriverAccepted()) {
            return $this->awaitingDriverLabel();
        }

        $this->loadMissing('schedule');
        if ($this->schedule?->driver_id) {
            return $this->schedule->bookingStatusLabel();
        }

        return 'Sắp chạy';
    }

    /** Nhãn theo dõi trên dashboard quản lý — đồng bộ khách / tài xế. */
    public function operatorMonitorLabel(): string
    {
        if ($this->isExpired()) {
            return 'Hết hạn';
        }

        if ($this->booking_status === 'cancelled') {
            return 'Đã hủy';
        }

        if ($this->booking_status === 'rejected') {
            return 'Từ chối';
        }

        if ($this->trip_status === 'completed') {
            return 'Hoàn thành';
        }

        if (! $this->hasDriverAccepted()) {
            return $this->awaitingDriverLabel();
        }

        $this->loadMissing('schedule');
        if ($this->schedule?->driver_id) {
            return $this->schedule->bookingStatusLabel();
        }

        return 'Sắp chạy';
    }

    public function primaryStatusColor(): string
    {
        return $this->statusColorForLabel($this->primaryStatusLabel());
    }

    public function operatorMonitorColor(): string
    {
        if (in_array($this->booking_status, ['cancelled', 'rejected'], true)) {
            return \App\Support\StatusBadge::DANGER;
        }

        if ($this->isExpired()) {
            return \App\Support\StatusBadge::NEUTRAL;
        }

        if ($this->trip_status === 'completed') {
            return \App\Support\StatusBadge::SUCCESS;
        }

        if (! $this->hasDriverAccepted()) {
            return \App\Support\StatusBadge::PENDING;
        }

        $this->loadMissing('schedule');

        return $this->schedule?->driver_id
            ? $this->schedule->bookingStatusColor()
            : \App\Support\StatusBadge::PENDING;
    }

    private function statusColorForLabel(string $label): string
    {
        if ($label === 'Hết hạn') {
            return \App\Support\StatusBadge::NEUTRAL;
        }

        return match ($label) {
            'Đã hủy', 'Từ chối'     => \App\Support\StatusBadge::DANGER,
            'Hoàn thành'            => \App\Support\StatusBadge::SUCCESS,
            'Đang phục vụ', 'Đang chạy', 'Đã đón khách', 'Tài xế đã đến điểm đón', 'Đã có tài xế' => \App\Support\StatusBadge::GOLD,
            'Chờ QL xác nhận', 'Chờ tài xế nhận', 'Đang tìm tài xế' => \App\Support\StatusBadge::PENDING,
            'Cần QL hỗ trợ'        => \App\Support\StatusBadge::DANGER,
            default                 => \App\Support\StatusBadge::NEUTRAL,
        };
    }

    public function tripDisplayLabel(): ?string
    {
        if ($this->isExpired()) {
            return null;
        }

        return match ($this->trip_status) {
            'completed'           => 'Hoàn tất',
            'awaiting_completion' => 'Chờ xác nhận hoàn',
            'cancelled'           => 'Đã hủy',
            'confirmed'           => $this->scheduleTripPhaseLabel(),
            default               => null,
        };
    }

    public function tripDisplayColor(): string
    {
        if ($this->isExpired()) {
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
        $this->loadMissing('schedule');

        if (! $this->schedule) {
            return 'Sắp chạy';
        }

        if ($this->schedule->driver_id) {
            return $this->schedule->bookingStatusLabel();
        }

        return match ($this->schedule->displayStatus()) {
            'completed' => 'Chạy xong',
            'running'   => 'Đang chạy',
            default     => 'Sắp chạy',
        };
    }

    /** Đơn hiển thị trên dashboard quản lý — ẩn đơn đã dismiss, giữ đơn hủy lần 4+. */
    public function scopeVisibleOnOperatorDashboard(Builder $query): Builder
    {
        if (Schema::hasColumn('bookings', 'operator_dismissed_at')) {
            $query->whereNull('operator_dismissed_at');
        }

        return $query->where(function (Builder $q): void {
            $q->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->orWhereNotNull('expired_at')
                ->orWhere('cancelled_by', 'driver')
                ->orWhere('cancelled_by', 'system');

            if (Schema::hasColumn('bookings', 'repeat_cancel_flag')) {
                $q->orWhere(function (Builder $q2): void {
                    $q2->where('cancelled_by', 'customer')
                        ->where('repeat_cancel_flag', true);
                });
            }
        });
    }

    public static function supportsOperatorDismiss(): bool
    {
        return Schema::hasColumn('bookings', 'operator_dismissed_at');
    }

    public function operatorListBucket(): string
    {
        if (in_array($this->booking_status, ['cancelled', 'rejected'], true)
            || $this->trip_status === 'cancelled') {
            return 'cancelled';
        }

        if ($this->trip_status === 'completed') {
            $this->loadMissing('tripReview');

            return $this->tripReview ? 'feedback' : 'completed';
        }

        return 'active';
    }

    public function isOperatorCancelled(): bool
    {
        return $this->operatorListBucket() === 'cancelled';
    }

    public function scopeOperatorListBucket(Builder $query, string $bucket): Builder
    {
        return match ($bucket) {
            'cancelled' => $query->where(function (Builder $q): void {
                $q->whereIn('booking_status', ['cancelled', 'rejected'])
                    ->orWhere('trip_status', 'cancelled');
            }),
            'completed' => $query
                ->where('trip_status', 'completed')
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->whereDoesntHave('tripReview'),
            'feedback' => $query
                ->where('trip_status', 'completed')
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->whereHas('tripReview'),
            default => $query
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->where('trip_status', '!=', 'completed'),
        };
    }

    public function scopeForOperatorVehicle(Builder $query, int $operatorId): Builder
    {
        return $query->whereHas('schedule.vehicle', fn ($q) => $q->where('operator_id', $operatorId));
    }
}
