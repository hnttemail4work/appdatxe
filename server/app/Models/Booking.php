<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Booking extends Model
{
    public const HELP_SEARCH_TIMEOUT = 'search_timeout';

    public const HELP_NO_DRIVER_IN_PROVINCE = 'no_driver_in_province';

    public const HELP_DRIVER_DECLINED = 'driver_declined';

    public const HELP_TRIP_OVERDUE = 'trip_not_completed';

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
        'seat_numbers',
        'trip_type',
        'booking_mode',
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
        'pickup_time',
        'dropoff_address',
        'dropoff_detail',
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
            'seat_numbers'   => 'array',
            'passenger_age'  => 'integer',
            'pickup_lat'     => 'float',
            'pickup_lng'     => 'float',
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

    public function tripTypeLabel(): string
    {
        return app(\App\Services\TripPricingService::class)->tripTypeLabel($this->trip_type ?? 'one_way');
    }

    public function contactPhone(): ?string
    {
        return $this->contact_phone;
    }

    public function matchesContactPhone(string $phone): bool
    {
        $stored = preg_replace('/\D+/', '', (string) $this->contact_phone);
        $given = preg_replace('/\D+/', '', $phone);

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

    public function expectedTripDurationMinutes(): int
    {
        $km = $this->tripDistanceKm();

        if ($km <= 0) {
            return 120;
        }

        return (int) ceil($km / \App\Services\OperatorTripOverdueService::ASSUMED_SPEED_KMH * 60);
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

    public function isTripOverdueStuck(): bool
    {
        return $this->operator_help_reason === self::HELP_TRIP_OVERDUE
            && $this->isInOperatorPendingQueue();
    }

    public function tripOverdueHelpLabel(): string
    {
        $expected = $this->expectedTripCompletionAt();
        $km = $this->tripDistanceKm();
        $label = 'Chuyến treo — quá hạn hoàn thành';

        if ($expected) {
            $label .= ' (dự kiến trước ' . $expected->format('H:i, d/m/Y') . ')';
        }

        if ($km > 0) {
            $label .= ' · ~' . $km . ' km @ 30 km/h';
        }

        return $label;
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

    public function seatReservations()
    {
        return $this->hasMany(SeatReservation::class);
    }

    public function referralCode()
    {
        return $this->hasOne(ReferralCode::class)->where('type', ReferralCode::TYPE_BOOKING_TEMP);
    }

    public function appliedReferralCode()
    {
        return $this->belongsTo(ReferralCode::class, 'applied_referral_code_id');
    }

    public function referralCommissionAmount(): int
    {
        $this->loadMissing('appliedReferralCode');
        if (! $this->appliedReferralCode || $this->trip_status !== 'completed') {
            return 0;
        }

        return (int) round((float) $this->total_price * $this->appliedReferralCode->commissionPercent() / 100, 0);
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

        return $this->schedule && (bool) $this->schedule->driver_id;
    }

    public function needsOperatorConfirmation(): bool
    {
        return $this->isInOperatorPendingQueue();
    }

    public function isInOperatorPendingQueue(): bool
    {
        return $this->needs_operator_help_at !== null
            && ! in_array($this->booking_status, ['cancelled', 'rejected'], true)
            && $this->trip_status !== 'completed'
            && ! $this->isExpired();
    }

    public function isAwaitingAutoDriverAssign(): bool
    {
        return ! $this->hasDriverAccepted()
            && $this->needs_operator_help_at === null
            && ! in_array($this->booking_status, ['cancelled', 'rejected'], true)
            && $this->trip_status !== 'completed'
            && ! $this->isExpired();
    }

    public function operatorHelpReasonLabel(): ?string
    {
        return match ($this->operator_help_reason) {
            self::HELP_SEARCH_TIMEOUT => 'Quá ' . \App\Services\DriverTripRequestService::OPERATOR_ESCALATION_MINUTES . ' phút chưa có tài xế',
            self::HELP_NO_DRIVER_IN_PROVINCE => 'Không có tài xế phù hợp trong khu vực',
            self::HELP_DRIVER_DECLINED => 'Tài xế từ chối / hết hạn — không gán lại được',
            self::HELP_TRIP_OVERDUE => $this->tripOverdueHelpLabel(),
            default => $this->needs_operator_help_at ? 'Cần quản lý gán tài xế thủ công' : null,
        };
    }

    public function bookingModeLabel(): string
    {
        return match ($this->booking_mode) {
            'whole_car' => 'Đặt cả xe',
            default     => 'Ghép xe',
        };
    }

    public function seatCount(): int
    {
        return count($this->seat_numbers ?? []);
    }

    /** Tổng tiền đơn theo loại đặt (cả xe / ghép × số ghế × loại chuyến). */
    public function chargedTotal(): float
    {
        $stored = (float) $this->total_price;
        $this->loadMissing('schedule');

        if (! $this->schedule) {
            return $stored;
        }

        return app(\App\Services\TripPricingService::class)->bookingTotal(
            $this->schedule,
            $this->trip_type ?? 'one_way',
            $this->booking_mode ?? 'shared',
            max($this->seatCount(), 1),
            $this->pickup_address,
            $this->dropoff_address,
        );
    }

    public function seatCountLabel(): string
    {
        if (($this->booking_mode ?? 'shared') === 'whole_car') {
            return 'Cả xe';
        }

        $count = $this->seatCount();

        return $count > 0 ? $count . ' ghế' : '';
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
            if ($this->needs_operator_help_at) {
                return 'Cần QL hỗ trợ';
            }

            return 'Đang tìm tài xế';
        }

        $this->loadMissing('schedule');
        if ($this->schedule
            && ($this->schedule->status === 'running' || $this->schedule->departure_time <= now())) {
            return 'Đang phục vụ';
        }

        return 'Sắp chạy';
    }

    /** Nhãn theo dõi trên dashboard quản lý — có thêm bước kết chuyến / phí nền tảng. */
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
            if ($this->needs_operator_help_at) {
                return 'Cần QL hỗ trợ';
            }

            return 'Đang tìm tài xế';
        }

        $this->loadMissing('schedule');
        if ($this->schedule
            && ($this->schedule->status === 'running' || $this->schedule->departure_time <= now())) {
            return 'Đang phục vụ';
        }

        return 'Sắp chạy';
    }

    public function primaryStatusColor(): string
    {
        return $this->statusColorForLabel($this->primaryStatusLabel());
    }

    public function operatorMonitorColor(): string
    {
        return $this->statusColorForLabel($this->operatorMonitorLabel());
    }

    private function statusColorForLabel(string $label): string
    {
        if ($label === 'Hết hạn') {
            return \App\Support\StatusBadge::NEUTRAL;
        }

        return match ($label) {
            'Đã hủy', 'Từ chối'     => \App\Support\StatusBadge::DANGER,
            'Hoàn thành'            => \App\Support\StatusBadge::SUCCESS,
            'Đang phục vụ'          => \App\Support\StatusBadge::GOLD,
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
                ->orWhereNotNull('needs_operator_help_at');

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

        if ($this->isInOperatorPendingQueue()) {
            return 'pending';
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
            'pending' => $query
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->where('trip_status', '!=', 'completed')
                ->whereNull('expired_at')
                ->whereNotNull('needs_operator_help_at'),
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
                ->where('trip_status', '!=', 'completed')
                ->whereNull('needs_operator_help_at')
                ->whereHas('schedule', fn (Builder $s) => $s->whereNotNull('driver_id')),
        };
    }

    public function scopeForOperatorVehicle(Builder $query, int $operatorId): Builder
    {
        return $query->whereHas('schedule.vehicle', fn ($q) => $q->where('operator_id', $operatorId));
    }
}
