<?php

namespace App\Models;

use App\Support\PlatformFees;
use App\Support\PushAudience;
use App\Support\PushNotificationSettings;
use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Booking extends Model
{
    protected static function booted(): void
    {
        static::updated(function (Booking $booking): void {
            if ($booking->wasChanged('trip_status') && $booking->trip_status === 'completed') {
                try {
                    app(\App\Services\PushNotificationService::class)->onTripCompleted($booking);
                } catch (\Throwable) {
                }
            }

            if (! $booking->wasChanged(['booking_status', 'trip_status'])) {
                return;
            }

            $cancelled = in_array($booking->booking_status, ['cancelled', 'rejected'], true)
                || $booking->trip_status === 'cancelled';

            if ($cancelled) {
                app(\App\Services\ReferralCodeService::class)->purgeForBooking($booking);

                try {
                    $push = app(\App\Services\PushNotificationService::class);
                    if ($booking->cancelled_by === 'system' && ! $booking->hadDriverEngagedForPickup()) {
                        $push->onNoDriverFound($booking);
                    } else {
                        $push->onTripCancelled($booking);
                    }
                } catch (\Throwable) {
                }
            }
        });
    }

    protected $fillable = [
        'customer_id',
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
        'departure_plan',
        'later_return_days',
        'later_return_booking_id',
        'later_pickup_dispatched_at',
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
            'later_pickup_dispatched_at' => 'datetime',
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

    /** Ngày đón thực tế — đồng bộ với trang chuyến khách. */
    public function driverPickupDateLabel(): ?string
    {
        return $this->guestPickupAt()?->format('d/m/Y');
    }

    /** Giờ đón · ngày đón cho màn tài xế. */
    public function driverPickupScheduleLabel(): ?string
    {
        $at = $this->guestPickupAt();
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

    public function tripDistanceKm(): int
    {
        if ($this->pickup_lat !== null && $this->pickup_lng !== null
            && $this->dropoff_lat !== null && $this->dropoff_lng !== null) {
            return max(1, (int) ceil(\App\Support\ProvinceCenters::distanceKm(
                (float) $this->pickup_lat,
                (float) $this->pickup_lng,
                (float) $this->dropoff_lat,
                (float) $this->dropoff_lng,
            )));
        }

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

    /** Giờ đón khách thấy trên trang chuyến — ngày đón + giờ đón, không dùng ngày về (gói «Trên 2 ngày»). */
    public function guestPickupAt(): ?\Carbon\Carbon
    {
        $this->loadMissing('schedule');

        if (! $this->schedule) {
            return null;
        }

        $plan = \App\Support\DeparturePlan::normalize($this->departure_plan ?? \App\Support\DeparturePlan::ONE_WAY);

        $pickupDay = match ($plan) {
            \App\Support\DeparturePlan::LATER => ($this->created_at ?? now())
                ->copy()
                ->timezone(config('app.timezone'))
                ->startOfDay(),
            default => $this->schedule->departure_time
                ? $this->schedule->departure_time->copy()->timezone(config('app.timezone'))->startOfDay()
                : ($this->schedule->service_date
                    ? \App\Support\ServiceDate::dayStart($this->schedule->service_date)
                    : null),
        };

        if (! $pickupDay instanceof \Carbon\Carbon) {
            return null;
        }

        if ($this->pickup_time) {
            $clock = \App\Support\DepartureTimeDisplay::normalizeForClock($this->pickup_time);

            return \Carbon\Carbon::parse(
                $pickupDay->toDateString() . ' ' . $clock . ':00',
                config('app.timezone'),
            );
        }

        return $this->schedule->departure_time?->copy();
    }

    /** Giờ đón dùng cho cảnh báo admin / ẩn dashboard TX — ưu tiên {@see guestPickupAt()}. */
    public function operationalPickupAt(): ?\Carbon\Carbon
    {
        return $this->guestPickupAt() ?? $this->tripStartAt();
    }

    // TODO (Fix Flow): Đặt Ngay = còn ≤ 30 phút tới giờ đón dự kiến.
    public function minutesUntilOperationalPickup(): ?int
    {
        $pickupAt = $this->operationalPickupAt();
        if (! $pickupAt instanceof \Carbon\Carbon) {
            return null;
        }

        return (int) now()->diffInMinutes($pickupAt, false);
    }

    public function isOnDemandPickup(): bool
    {
        $minutes = $this->minutesUntilOperationalPickup();

        return $minutes !== null && $minutes <= 30;
    }

    public function isScheduledPickup(): bool
    {
        return ! $this->isOnDemandPickup();
    }

    public function isPastPickupTime(): bool
    {
        $pickupAt = $this->operationalPickupAt();

        return $pickupAt !== null && $pickupAt->lte(now());
    }

    /** Giờ đón − 15 phút — lúc admin có thể hủy / hết hạn nhận cuốc. */
    public function pickupAdminActionStartsAt(): ?\Carbon\Carbon
    {
        $pickupAt = $this->operationalPickupAt();

        return $pickupAt?->copy()->subMinutes(\App\Services\DriverTripRequestService::PICKUP_INVITE_LEAD_MINUTES);
    }

    /** Ẩn khỏi khách + admin (tab đặt xe) sau giờ đón nếu chưa đón khách. */
    public function shouldHideFromGuestAndOperatorActiveLists(): bool
    {
        if (! $this->isPastPickupTime()) {
            return false;
        }

        if (in_array($this->booking_status, ['cancelled', 'rejected'], true)) {
            return false;
        }

        if ($this->trip_status === 'completed') {
            return false;
        }

        if ($this->passengerPickedUp()) {
            return false;
        }

        return true;
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

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function laterReturnBooking()
    {
        return $this->belongsTo(self::class, 'later_return_booking_id');
    }

    public function isLaterDeparturePlan(): bool
    {
        return \App\Support\DeparturePlan::normalize($this->departure_plan ?? '') === \App\Support\DeparturePlan::LATER;
    }

    public function laterPickupReminderStartsAt(): \Carbon\Carbon
    {
        if ($this->isLaterDeparturePlan()) {
            $this->loadMissing('schedule');

            if ($this->schedule?->service_date) {
                return \App\Support\ServiceDate::dayStart($this->schedule->service_date);
            }
        }

        return \App\Support\DeparturePlan::laterPickupReminderStartsAt($this->created_at ?? now());
    }

    public function laterReturnDays(): ?int
    {
        if (! $this->isLaterDeparturePlan()) {
            return null;
        }

        $days = (int) ($this->later_return_days ?? 0);

        return $days > 0 ? $days : \App\Support\DeparturePlan::DEFAULT_LATER_RETURN_DAYS;
    }

    /** Đơn trên 2 ngày đã hoàn thành — đến ngày về cần admin nhắc đón khách. */
    public function showsLaterPickupReminder(): bool
    {
        if (! $this->isLaterDeparturePlan()) {
            return false;
        }

        if ($this->trip_status !== 'completed') {
            return false;
        }

        if ($this->later_pickup_dispatched_at !== null) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->laterPickupReminderStartsAt());
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

    /** Tài xế đã gắn với vé — chuyến đã nhận hoặc đang chờ TX xác nhận. */
    public function resolveAssignedDriverId(?Schedule $schedule = null): ?int
    {
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
            ->where(function ($query): void {
                $query->where('status', 'accepted')
                    ->orWhere(function ($query): void {
                        $query->where('status', 'pending')
                            ->where(function ($query): void {
                                $query->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    });
            })
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
                                    ->whereIn('driver_trip_requests.status', ['accepted', 'pending']);
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

        return 'Giảm ' . $formatted . '% (' . $this->appliedReferralCode->customerDiscountSourceLabel() . ')';
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

    /** Hồ sơ TX hiển thị cho khách — đã gán, đang mời, hoặc chọn từ catalog. */
    public function guestDriverProfile(): ?DriverProfile
    {
        return $this->activeDriverProfile() ?? $this->catalogChosenDriverProfile();
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

        // Đã giao / đang mời TX (admin gán thủ công hoặc auto-assign) — không báo catalog.
        if ($this->driverAcceptanceState() === 'pending') {
            return false;
        }

        if ($this->adminReleasedAfterDriverEngagement()) {
            return false;
        }

        $this->loadMissing('schedule.template');
        $schedule = $this->schedule;
        $templateDriverId = (int) ($schedule?->template?->driver_id ?? 0);
        if ($templateDriverId <= 0 || ! $schedule) {
            return false;
        }

        $exclude = app(\App\Services\DriverTripRequestService::class)
            ->assignmentExcludeDriverIds($schedule, (string) $this->contact_phone);
        if ($exclude->contains($templateDriverId)) {
            return false;
        }

        $assignedId = (int) ($this->resolveAssignedDriverId() ?? 0);
        if ($assignedId > 0 && $assignedId !== $templateDriverId) {
            return false;
        }

        $driver = DriverProfile::query()->where('user_id', $templateDriverId)->first();
        if (! $driver) {
            return false;
        }

        // Chỉ báo khi TX tắt «Sẵn sàng» — không nhầm với đang chạy chuyến khác (on_trip).
        return ($driver->availability_status ?? 'off_duty') !== 'available';
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

        return (int) ($this->schedule?->driver_id ?? 0) > 0;
    }

    /** Đã từng có TX nhận / được mời nhận — dùng phân biệt «không có TX» vs «TX không đến». */
    public function hadDriverEngagedForPickup(): bool
    {
        if ($this->hasDriverAccepted()) {
            return true;
        }

        if (Schema::hasColumn('bookings', 'assigned_driver_id')
            && (int) ($this->assigned_driver_id ?? 0) > 0) {
            return true;
        }

        if (! $this->schedule_id || ! $this->contact_phone) {
            return false;
        }

        return DriverTripRequest::query()
            ->where('schedule_id', $this->schedule_id)
            ->where('contact_phone', (string) $this->contact_phone)
            ->whereIn('status', ['accepted', 'expired', 'cancelled', 'rejected'])
            ->exists();
    }

    /** TX đã từng gắn với vé — hiển thị tab Đã hủy (không lấy cuốc chờ nhận). */
    public function historicalAssignedDriverProfile(): ?DriverProfile
    {
        if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
            $assignedId = (int) ($this->assigned_driver_id ?? 0);
            if ($assignedId > 0) {
                return DriverProfile::query()
                    ->where('user_id', $assignedId)
                    ->with('user')
                    ->first();
            }
        }

        if (! $this->schedule_id || ! $this->contact_phone) {
            return null;
        }

        $driverUserId = (int) (DriverTripRequest::query()
            ->where('schedule_id', $this->schedule_id)
            ->where('contact_phone', (string) $this->contact_phone)
            ->whereIn('status', ['accepted', 'expired', 'cancelled'])
            ->orderByDesc('id')
            ->value('driver_id') ?? 0);

        if ($driverUserId <= 0) {
            return null;
        }

        return DriverProfile::query()
            ->where('user_id', $driverUserId)
            ->with('user')
            ->first();
    }

    /** TX hiển thị trên bảng quản lý — đang chạy hoặc đã từng nhận (tab hủy). */
    public function adminTripDriverProfile(): ?DriverProfile
    {
        if (in_array($this->booking_status, ['cancelled', 'rejected'], true)
            || $this->trip_status === 'cancelled') {
            return $this->historicalAssignedDriverProfile();
        }

        return $this->activeDriverProfile();
    }

    /** Khách thấy thẻ tài xế — đã nhận, đang mời, hoặc đã chọn TX từ catalog. */
    public function guestShowsDriverInfo(): bool
    {
        if ($this->guestDriverProfile() === null) {
            return false;
        }

        if (in_array($this->driverAcceptanceState(), ['pending', 'accepted'], true)) {
            return true;
        }

        return $this->catalogChosenDriverProfile() !== null && $this->blocksGuestRebooking();
    }

    public function passengerPickedUp(): bool
    {
        $this->loadMissing('schedule');

        return (bool) $this->schedule?->passengerPickedUp();
    }

    /** Admin hủy khi đã tới khung giờ đón − 15 phút. */
    public function adminCanCancelAfterInviteTimeout(): bool
    {
        if (! $this->adminCanModifyDriverOrCancel()) {
            return false;
        }

        $windowStart = $this->pickupAdminActionStartsAt();
        if (! $windowStart) {
            return false;
        }

        return now()->gte($windowStart);
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

    // TODO (Fix Flow): Chỉ hiện nút gán thủ công khi có cảnh báo — không khi auto-assign đang chạy bình thường.
    public function adminShouldShowManualAssign(): bool
    {
        if (! $this->adminCanModifyDriverOrCancel()) {
            return false;
        }

        if ($this->needs_operator_help_at) {
            return true;
        }

        if ($this->adminReleasedAfterDriverEngagement()) {
            return true;
        }

        if (in_array($this->operator_help_reason, [
            'driver_search_timeout',
            'driver_invite_timeout',
            'driver_movement_timeout',
            'driver_late_no_show',
            'driver_cancelled_trip',
        ], true)) {
            return true;
        }

        $alert = app(\App\Services\DriverLatePickupService::class)->adminAlertForBooking($this);
        if ($alert && in_array($alert['level'] ?? '', ['warning', 'danger'], true)) {
            return true;
        }

        return false;
    }

    public function adminWaitingMinutesRemaining(): ?int
    {
        $this->loadMissing('schedule');
        $schedule = $this->schedule;

        if (! $schedule) {
            return null;
        }

        $pendingRequest = $this->eligiblePendingDriverTripRequest();

        if ($pendingRequest?->expires_at?->isFuture()) {
            return max(1, (int) now()->diffInMinutes($pendingRequest->expires_at, false));
        }

        if ($this->driverAcceptanceState() !== 'accepted') {
            return null;
        }

        $inviteDeadline = $this->pickupAdminActionStartsAt();
        if ($inviteDeadline?->isFuture()) {
            return max(1, (int) now()->diffInMinutes($inviteDeadline, false));
        }

        return null;
    }

    /** TX đã nhận rồi bị gỡ — không còn offer đang chờ. */
    public function adminReleasedAfterDriverEngagement(): bool
    {
        if ($this->schedule?->driver_id) {
            return false;
        }

        if ($this->driverAcceptanceState() === 'pending') {
            return false;
        }

        return $this->hadDriverEngagedForPickup();
    }

    /** Còn trong khung tự động tìm TX sau khi TX cũ hủy / bị gỡ. */
    public function adminStillSearchingReplacementDriver(): bool
    {
        if (! $this->adminReleasedAfterDriverEngagement()) {
            return false;
        }

        if ($this->needs_operator_help_at) {
            return false;
        }

        return ! app(\App\Services\DriverTripRequestService::class)
            ->hasExceededCustomerSearchDeadline($this);
    }

    /** Chuyến đang chờ tài xế hoặc cần admin gán / gán lại TX. */
    public function needsAdminWaitingAttention(): bool
    {
        if (! $this->adminCanModifyDriverOrCancel()) {
            return false;
        }

        if ($this->needs_operator_help_at) {
            return true;
        }

        if ($this->adminWaitingMinutesRemaining() !== null) {
            return true;
        }

        $this->loadMissing('schedule');
        $schedule = $this->schedule;

        if (! $schedule) {
            return false;
        }

        if ($this->driverAcceptanceState() === 'accepted') {
            return $schedule->departure_time > now();
        }

        return true;
    }

    private function awaitingDriverLabel(): string
    {
        $this->loadMissing('schedule.template', 'schedule.driverTripRequests');

        if ($this->driverAcceptanceState() === 'pending') {
            return 'Chờ tài xế';
        }

        if ($this->adminReleasedAfterDriverEngagement()) {
            if ($this->adminStillSearchingReplacementDriver()) {
                return 'Đang tìm tài xế khác';
            }

            return 'TX hủy — cần gán lại';
        }

        return 'Đang tìm tài xế';
    }

    public function latestDriverTripRequest(): ?DriverTripRequest
    {
        if (! $this->schedule_id || ! $this->contact_phone) {
            return null;
        }

        $this->loadMissing('schedule.driverTripRequests');

        if ($this->schedule?->relationLoaded('driverTripRequests')) {
            $fromSchedule = $this->schedule->driverTripRequests
                ->filter(fn (DriverTripRequest $request): bool => $request->contact_phone === $this->contact_phone)
                ->sortByDesc('id')
                ->first();

            if ($fromSchedule) {
                return $fromSchedule;
            }
        }

        return DriverTripRequest::query()
            ->where('schedule_id', $this->schedule_id)
            ->where('contact_phone', (string) $this->contact_phone)
            ->latest('id')
            ->first();
    }

    /** @return 'none'|'pending'|'accepted' */
    public function driverAcceptanceState(): string
    {
        $this->loadMissing('schedule');

        if ($this->schedule?->driver_id) {
            return 'accepted';
        }

        if ($this->eligiblePendingDriverTripRequest() !== null) {
            return 'pending';
        }

        return 'none';
    }

    /** Offer pending còn hiệu lực — bỏ qua TX đã từ chối / hủy / hết hạn trên cùng đơn. */
    public function eligiblePendingDriverTripRequest(): ?DriverTripRequest
    {
        $request = $this->latestDriverTripRequest();
        if (! $request?->isPending()) {
            return null;
        }

        if ($request->expires_at !== null && $request->expires_at->isPast()) {
            return null;
        }

        $this->loadMissing('schedule');
        $schedule = $this->schedule;
        if (! $schedule || trim((string) $this->contact_phone) === '') {
            return $request;
        }

        $exclude = app(\App\Services\DriverTripRequestService::class)
            ->assignmentExcludeDriverIds($schedule, (string) $this->contact_phone);

        if ($exclude->contains((int) $request->driver_id)) {
            $hidden = app(\App\Services\DriverCuocOfferHideService::class)->isHidden(
                (int) $request->driver_id,
                $schedule,
                (string) $this->contact_phone,
            );
            if ($hidden) {
                return null;
            }
        }

        return $request;
    }

    public function assignedDriverHasPushSubscription(): bool
    {
        $driverUserId = (int) ($this->resolveAssignedDriverId() ?? 0);
        if ($driverUserId <= 0) {
            return false;
        }

        return PushSubscription::query()
            ->where('audience', PushAudience::DRIVER)
            ->where('user_id', $driverUserId)
            ->exists();
    }

    /** TX đang bật app và chia sẻ GPS (hoặc heartbeat poll tab tài xế). */
    public function assignedDriverSharesLiveLocation(): bool
    {
        $profile = $this->activeDriverProfile();
        if (! $profile) {
            return false;
        }

        return app(\App\Services\DriverAvailabilityService::class)
            ->isDriverAppActiveForAdmin($profile);
    }

    /** @return array{label: string, color: string, can_nudge: bool}|null */
    public function adminDriverDispatchDetail(): ?array
    {
        if (in_array($this->booking_status, ['cancelled', 'rejected'], true)) {
            return null;
        }

        if ($this->trip_status === 'completed') {
            return null;
        }

        $state = $this->driverAcceptanceState();
        if ($state === 'none') {
            return null;
        }

        $hasPush = $this->assignedDriverHasPushSubscription();
        $sharesLocation = $this->assignedDriverSharesLiveLocation();
        $pushReady = PushNotificationSettings::isEnabled()
            && PushNotificationSettings::vapidKeys() !== null
            && PushNotificationSettings::isEventEnabled('driver.new_trip_request');

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

        $driverName = trim((string) ($this->schedule?->driver_name ?? ''));
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
            $this->departure_plan ?? \App\Support\DeparturePlan::TODAY,
            $this->laterReturnDays(),
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
            if ($this->showsLaterPickupReminder()) {
                return 'Nhắc đón khách';
            }

            return 'Hoàn thành';
        }

        if ($this->needs_operator_help_at) {
            return match ($this->operator_help_reason) {
                'driver_invite_timeout'    => 'TX không nhận — cần gán lại',
                'driver_late_no_show'      => 'Quá giờ đón — cần gán lại',
                'driver_movement_timeout'  => 'TX chưa xác nhận đi đón',
                'driver_cancelled_trip'    => 'TX hủy cuốc — cần gán lại',
                default                    => 'Cần quản lý xử lý',
            };
        }

        $acceptance = $this->driverAcceptanceState();
        if ($acceptance === 'none') {
            if ($this->adminReleasedAfterDriverEngagement()) {
                return $this->adminStillSearchingReplacementDriver()
                    ? 'Đang tìm tài xế khác'
                    : 'TX hủy cuốc — cần gán lại';
            }

            return $this->awaitingDriverLabel();
        }

        if ($acceptance === 'pending') {
            return 'Chờ TX nhận cuốc';
        }

        $this->loadMissing('schedule');
        if ($this->schedule?->driver_id) {
            return $this->schedule->bookingStatusLabel();
        }

        return 'TX đã nhận';
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
            if ($this->showsLaterPickupReminder()) {
                return \App\Support\StatusBadge::ACCENT;
            }

            return \App\Support\StatusBadge::SUCCESS;
        }

        if ($this->needs_operator_help_at) {
            return \App\Support\StatusBadge::DANGER;
        }

        $acceptance = $this->driverAcceptanceState();
        if ($acceptance === 'none') {
            return \App\Support\StatusBadge::PENDING;
        }

        if ($acceptance === 'pending') {
            return \App\Support\StatusBadge::ACCENT;
        }

        $this->loadMissing('schedule');

        return $this->schedule?->driver_id
            ? $this->schedule->bookingStatusColor()
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
                ->orWhere('cancelled_by', 'system')
                ->orWhere('cancelled_by', 'customer');
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
