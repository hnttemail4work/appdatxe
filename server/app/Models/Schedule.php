<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'template_id',
        'route_id',
        'vehicle_id',
        'driver_id',
        'driver_name',
        'departure_time',
        'expected_arrival_at',
        'whole_car_price',
        'service_date',
        'status',
        'driver_stage',
        'driver_assigned_at',
        'driver_movement_deadline_at',
        'driver_movement_confirmed_at',
        'driver_late_pickup_prompt_at',
        'driver_late_pickup_continue_deadline_at',
        'driver_depart_reminder_sent_at',
        'trip_code',
    ];

    protected function casts(): array
    {
        return [
            'departure_time' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'driver_assigned_at' => 'datetime',
            'driver_movement_deadline_at' => 'datetime',
            'driver_movement_confirmed_at' => 'datetime',
            'driver_late_pickup_prompt_at' => 'datetime',
            'driver_late_pickup_continue_deadline_at' => 'datetime',
            'driver_depart_reminder_sent_at'        => 'datetime',
            'service_date'   => 'date',
            'whole_car_price' => 'decimal:2',
        ];
    }

    public function template()
    {
        return $this->belongsTo(ScheduleTemplate::class, 'template_id');
    }

    public function route()
    {
        return $this->belongsTo(TripRoute::class, 'route_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /** Hồ sơ tài xế đã gán chuyến (eager load qua schedule.driver.driverProfile). */
    public function assignedDriverProfile()
    {
        return $this->belongsTo(DriverProfile::class, 'driver_id', 'user_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function tripSettlement()
    {
        return $this->hasOne(DriverTripSettlement::class);
    }

    public function driverTripRequests()
    {
        return $this->hasMany(DriverTripRequest::class);
    }

    /** Tài xế đã nhận chuyến hoặc đang được giao (chờ phản hồi) — dùng chung cho mọi khách ghép. */
    public function designatedDriverProfile(): ?DriverProfile
    {
        if ($this->driver_id) {
            return DriverProfile::query()
                ->where('user_id', $this->driver_id)
                ->with('user')
                ->first();
        }

        $this->loadMissing('template');

        if ($this->template?->driver_id) {
            $catalogDriver = DriverProfile::query()
                ->where('user_id', $this->template->driver_id)
                ->with('user')
                ->first();

            if ($catalogDriver) {
                return $catalogDriver;
            }
        }

        $pendingDriverId = $this->driverTripRequests()
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('created_at')
            ->value('driver_id');

        if (! $pendingDriverId) {
            return null;
        }

        return DriverProfile::query()
            ->where('user_id', $pendingDriverId)
            ->with('user')
            ->first();
    }

    public function capacity(): int
    {
        return (int) ($this->vehicle?->capacity ?? 0);
    }

    public function shortTripCode(): string
    {
        return \App\Support\TripCode::short($this->trip_code);
    }

    public function routeDepartureLabel(): string
    {
        if ($this->route?->departure) {
            return (string) $this->route->departure;
        }

        $booking = $this->driverRelevantBookings()->first();

        return $booking?->pickup_address ? (string) $booking->pickup_address : '—';
    }

    public function routeDestinationLabel(): string
    {
        if ($this->route?->destination) {
            return (string) $this->route->destination;
        }

        $booking = $this->driverRelevantBookings()->first();

        return $booking?->dropoff_address ? (string) $booking->dropoff_address : '—';
    }

    public function tripMetaLabel(): string
    {
        $parts = [$this->departure_time->format('H:i, d/m/Y')];

        if ($this->vehicle) {
            $parts[] = ucfirst($this->vehicle->type) . ' ' . $this->vehicle->license_plate;
        }

        $guestCount = $this->driverRelevantBookings()->count();
        if ($guestCount > 0) {
            $parts[] = $guestCount . ' khách';
        }

        return implode(', ', $parts);
    }

    public function wholeCarPriceAmount(): float
    {
        if ($this->whole_car_price !== null) {
            return (float) $this->whole_car_price;
        }

        return (float) app(\App\Services\TripPricingService::class)->wholeCarPrice($this);
    }

    public function activeReservationCount(): int
    {
        if (isset($this->active_reservations_count)) {
            return (int) $this->active_reservations_count;
        }

        $hasActive = $this->bookings()
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->whereNotIn('trip_status', ['completed', 'cancelled'])
            ->exists();

        return $hasActive ? $this->capacity() : 0;
    }

    public function bookedSeatsCount(): int
    {
        return $this->activeReservationCount();
    }

    public function expectedArrivalAt(): \Carbon\Carbon
    {
        if ($this->expected_arrival_at) {
            return $this->expected_arrival_at->copy();
        }

        $this->loadMissing('template');

        if ($this->template && $this->service_date) {
            return $this->template->expectedArrivalAt(\Carbon\Carbon::parse($this->service_date));
        }

        return $this->departure_time->copy()->addHours(12);
    }

    public function completesAt(): \Carbon\Carbon
    {
        return $this->expectedArrivalAt();
    }

    /** Chỉ chuyến đã duyệt mới coi là đang / đã chạy theo giờ. */
    public function displayStatus(): string
    {
        if ($this->status === 'completed') {
            return 'completed';
        }

        if (in_array($this->status, ['cancelled', 'draft'], true)) {
            return $this->status;
        }

        $hasConfirmed = $this->relationLoaded('bookings')
            ? $this->bookings->contains(fn ($b) => $b->booking_status === 'confirmed')
            : $this->bookings()->where('booking_status', 'confirmed')->exists();

        if (! $hasConfirmed) {
            return 'scheduled';
        }

        if (now() >= $this->completesAt()) {
            return 'completed';
        }

        if ($this->driver_id) {
            return match ($this->resolvedDriverStage()) {
                self::DRIVER_STAGE_RUNNING   => 'running',
                self::DRIVER_STAGE_COMPLETED => 'completed',
                default                      => 'scheduled',
            };
        }

        if ($this->status === 'running') {
            return 'running';
        }

        return 'scheduled';
    }

    public function statusLabel(): string
    {
        if (in_array($this->status, ['cancelled', 'draft'], true)) {
            return match ($this->status) {
                'cancelled' => 'Đã hủy',
                'draft'     => 'Nháp',
                default     => '—',
            };
        }

        if ($this->driver_id) {
            if ($this->resolvedDriverStage() === self::DRIVER_STAGE_COMPLETED
                || $this->status === 'completed'
                || now() >= $this->completesAt()) {
                return 'Chạy xong';
            }

            return $this->bookingStatusLabel();
        }

        $display = $this->displayStatus();

        if ($display === 'scheduled' && $this->departure_time <= now()) {
            return 'Chờ tài xế';
        }

        if ($display === 'scheduled') {
            return 'Sắp chạy';
        }

        return match ($display) {
            'running'   => 'Đang chạy',
            'completed' => 'Chạy xong',
            default     => 'Sắp chạy',
        };
    }

    public function driverRelevantBookings(): \Illuminate\Support\Collection
    {
        $bookings = $this->relationLoaded('bookings')
            ? $this->bookings
            : $this->bookings()->get();

        $this->loadMissing('tripSettlement');

        if ($this->tripSettlement && $this->tripSettlement->status !== 'completed') {
            return $bookings
                ->filter(fn (Booking $booking): bool => ! in_array($booking->booking_status, ['cancelled', 'rejected'], true))
                ->values();
        }

        return $bookings->filter(function (Booking $booking): bool {
            if (! in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
                return true;
            }

            return false;
        })->values();
    }

    public const DRIVER_STAGE_ASSIGNED = 'assigned';

    public const DRIVER_STAGE_AT_PICKUP = 'at_pickup';

    public const DRIVER_STAGE_PICKED_UP = 'picked_up';

    public const DRIVER_STAGE_RUNNING = 'running';

    public const DRIVER_STAGE_COMPLETED = 'completed';

    /** @return list<string> */
    public static function driverStageOrder(): array
    {
        return [
            self::DRIVER_STAGE_ASSIGNED,
            self::DRIVER_STAGE_AT_PICKUP,
            self::DRIVER_STAGE_PICKED_UP,
            self::DRIVER_STAGE_RUNNING,
            self::DRIVER_STAGE_COMPLETED,
        ];
    }

    public function resolvedDriverStage(): string
    {
        if (! $this->driver_id) {
            return self::DRIVER_STAGE_ASSIGNED;
        }

        $stage = $this->driver_stage ?: self::DRIVER_STAGE_ASSIGNED;

        return $stage;
    }

    /** Nhãn trạng thái thống nhất — khách, quản lý, theo dõi chuyến. */
    public function bookingStatusLabel(): string
    {
        $stage = $this->resolvedDriverStage();

        if ($stage === self::DRIVER_STAGE_ASSIGNED) {
            return $this->driver_movement_confirmed_at
                ? 'Tài xế đang đi đón'
                : 'Đã có tài xế';
        }

        return match ($stage) {
            self::DRIVER_STAGE_AT_PICKUP => 'Tài xế đã đến điểm đón',
            self::DRIVER_STAGE_PICKED_UP => 'Đã đón khách',
            self::DRIVER_STAGE_RUNNING   => 'Đang chạy',
            self::DRIVER_STAGE_COMPLETED => 'Hoàn thành',
            default                      => 'Sắp chạy',
        };
    }

    public function bookingStatusColor(): string
    {
        $stage = $this->resolvedDriverStage();

        if ($stage === self::DRIVER_STAGE_ASSIGNED) {
            return $this->driver_movement_confirmed_at
                ? \App\Support\StatusBadge::ACCENT
                : \App\Support\StatusBadge::INFO;
        }

        return match ($stage) {
            self::DRIVER_STAGE_RUNNING, self::DRIVER_STAGE_PICKED_UP => \App\Support\StatusBadge::GOLD,
            self::DRIVER_STAGE_AT_PICKUP => \App\Support\StatusBadge::ACCENT,
            self::DRIVER_STAGE_COMPLETED => \App\Support\StatusBadge::SUCCESS,
            default                      => \App\Support\StatusBadge::PENDING,
        };
    }

    /** Khách đang trên xe, tài xế đã bắt đầu chạy. */
    /** Tài xế đã đón khách — không cho admin gán lại / hủy. */
    public function passengerPickedUp(): bool
    {
        return in_array($this->resolvedDriverStage(), [
            self::DRIVER_STAGE_PICKED_UP,
            self::DRIVER_STAGE_RUNNING,
            self::DRIVER_STAGE_COMPLETED,
        ], true);
    }

    public function driverNextStage(): ?string
    {
        $order = self::driverStageOrder();
        $current = $this->resolvedDriverStage();
        $index = array_search($current, $order, true);

        if ($index === false || $index >= count($order) - 1) {
            return null;
        }

        return $order[$index + 1];
    }

    public function driverStageLabel(?string $stage = null): string
    {
        return match ($stage ?? $this->resolvedDriverStage()) {
            self::DRIVER_STAGE_ASSIGNED  => 'Chờ khởi hành',
            self::DRIVER_STAGE_AT_PICKUP => 'Đến điểm đón',
            self::DRIVER_STAGE_PICKED_UP => 'Đón khách',
            self::DRIVER_STAGE_RUNNING   => 'Đang chạy',
            self::DRIVER_STAGE_COMPLETED => 'Hoàn thành',
            default                      => '—',
        };
    }

    public function driverNextStageActionLabel(): ?string
    {
        return match ($this->driverNextStage()) {
            self::DRIVER_STAGE_AT_PICKUP => 'Đến điểm đón',
            self::DRIVER_STAGE_PICKED_UP => 'Đón khách',
            self::DRIVER_STAGE_RUNNING   => 'Bắt đầu chạy',
            self::DRIVER_STAGE_COMPLETED => 'Hoàn thành chuyến',
            default                      => null,
        };
    }

    /** Chưa bấm «Xác nhận» đi đón — bắt buộc trước khi chuyển sang «Đến điểm đón». */
    public function needsDriverMovementConfirm(): bool
    {
        return $this->resolvedDriverStage() === self::DRIVER_STAGE_ASSIGNED
            && $this->driver_id
            && $this->driver_movement_confirmed_at === null;
    }

    /** TX đã bấm «Xác nhận» — khách bắt đầu thấy ETA đến điểm đón. */
    public function driverHasConfirmedMovement(): bool
    {
        if ($this->driver_movement_confirmed_at) {
            return true;
        }

        if (! $this->driver_id) {
            return false;
        }

        return in_array($this->resolvedDriverStage(), [
            self::DRIVER_STAGE_AT_PICKUP,
            self::DRIVER_STAGE_PICKED_UP,
            self::DRIVER_STAGE_RUNNING,
            self::DRIVER_STAGE_COMPLETED,
        ], true);
    }

    /** Bước xử lý trên dashboard tài xế — một lần cho cả chuyến xe. */
    public function driverWorkflowPhase(): string
    {
        $this->loadMissing('tripSettlement');

        if ($this->tripSettlement?->status === 'completed') {
            return 'settled';
        }

        $bookings = $this->driverRelevantBookings();
        if ($bookings->isEmpty()) {
            return 'other';
        }

        if ($bookings->every(fn (Booking $b): bool => $b->trip_status === 'completed')) {
            return 'settled';
        }

        $stage = $this->resolvedDriverStage();

        if (in_array($stage, [self::DRIVER_STAGE_PICKED_UP, self::DRIVER_STAGE_RUNNING], true)) {
            return 'active';
        }

        if (in_array($stage, [self::DRIVER_STAGE_ASSIGNED, self::DRIVER_STAGE_AT_PICKUP], true)) {
            return 'upcoming';
        }

        return 'upcoming';
    }

    /** Tài xế có thể hủy sau khi nhận — chỉ khi chưa đón khách (hệ thống sẽ tìm TX khác). */
    public function driverCanCancelTrip(): bool
    {
        if (! $this->driver_id) {
            return false;
        }

        return in_array($this->resolvedDriverStage(), [
            self::DRIVER_STAGE_ASSIGNED,
            self::DRIVER_STAGE_AT_PICKUP,
        ], true);
    }

    /** Hệ thống đã qua giờ kết thúc dự kiến — tài xế cần bấm hoàn thành. */
    public function driverPendingClosure(): bool
    {
        return $this->driverRelevantBookings()->contains(
            fn (Booking $b): bool => $b->trip_status === 'awaiting_completion',
        );
    }

    public function driverWorkflowLabel(): string
    {
        $bookings = $this->driverRelevantBookings();
        $count = $bookings->count();
        $stageLabel = $this->driverStageLabel();

        if ($this->driverWorkflowPhase() === 'settled') {
            return 'Hoàn thành chuyến';
        }

        if ($count > 1) {
            return "{$stageLabel} ({$count} vé)";
        }

        return $stageLabel;
    }

    public function driverWorkflowColor(): string
    {
        return match ($this->driverWorkflowPhase()) {
            'upcoming' => \App\Support\StatusBadge::NEUTRAL,
            'active'   => \App\Support\StatusBadge::GOLD,
            'settled'  => \App\Support\StatusBadge::SUCCESS,
            default    => \App\Support\StatusBadge::NEUTRAL,
        };
    }

    public function driverViewSortKey(): string
    {
        $phase = $this->driverWorkflowPhase();

        $priority = match ($phase) {
            'active'   => 0,
            'upcoming' => 1,
            'settled'  => 4,
            default    => 5,
        };

        $timeKey = $phase === 'active'
            ? 9_999_999_999 - $this->departure_time->timestamp
            : $this->departure_time->timestamp;

        return sprintf('%02d-%010d', $priority, $timeKey);
    }

    /** Tất cả vé trên chuyến (kể cả đã hủy) — dùng tab lịch sử. */
    public function driverHistoryBookings(): \Illuminate\Support\Collection
    {
        $bookings = $this->relationLoaded('bookings')
            ? $this->bookings
            : $this->bookings()->get();

        return $bookings
            ->whereNotIn('booking_status', ['rejected'])
            ->values();
    }

    /** Vé thuộc lịch sử của một tài xế (theo assigned_driver_id hoặc legacy driver_id). */
    public function driverHistoryBookingsFor(int $driverUserId): \Illuminate\Support\Collection
    {
        return $this->driverHistoryBookings()->filter(function (Booking $booking) use ($driverUserId): bool {
            if (! $booking->isVisibleInDriverHistory()) {
                return false;
            }

            if ($booking->cancelled_by === 'customer') {
                return false;
            }

            if ((int) $booking->assigned_driver_id === $driverUserId) {
                return true;
            }

            if ($booking->assigned_driver_id === null && (int) $this->driver_id === $driverUserId) {
                return true;
            }

            if ($booking->assigned_driver_id === null
                && $booking->resolveAssignedDriverId($this) === $driverUserId) {
                return true;
            }

            return false;
        })->values();
    }

    public function driverHistoryOutcomeFor(int $driverUserId): string
    {
        $bookings = $this->driverHistoryBookingsFor($driverUserId);

        if ($bookings->contains(fn (Booking $b): bool => $b->trip_status === 'completed')) {
            return 'completed';
        }

        if ($bookings->contains(fn (Booking $b): bool => $b->cancelled_by === 'driver')) {
            return 'cancelled_driver';
        }

        return 'other';
    }

    public function driverHistoryLabelFor(int $driverUserId): string
    {
        $bookings = $this->driverHistoryBookingsFor($driverUserId);
        $count = $bookings->count();

        return match ($this->driverHistoryOutcomeFor($driverUserId)) {
            'completed'        => $count > 1 ? "Hoàn thành ({$count} vé)" : 'Hoàn thành',
            'cancelled_driver' => $count > 1 ? "Tài xế hủy ({$count} vé)" : 'Tài xế hủy',
            default            => '—',
        };
    }

    public function driverHistoryColorFor(int $driverUserId): string
    {
        return match ($this->driverHistoryOutcomeFor($driverUserId)) {
            'completed'        => \App\Support\StatusBadge::SUCCESS,
            'cancelled_driver' => \App\Support\StatusBadge::DANGER,
            default            => \App\Support\StatusBadge::NEUTRAL,
        };
    }

    public function completedRevenueTotalFor(int $driverUserId): float
    {
        return (float) $this->driverHistoryBookingsFor($driverUserId)
            ->filter(fn (Booking $b): bool => $b->trip_status === 'completed')
            ->sum(fn (Booking $b): float => (float) $b->total_price);
    }

    /** Chuyến đang phục vụ trên tab Chuyến — giữ hiển thị sau giờ khởi hành dự kiến cho đến khi kết thúc. */
    public function scopeForDriverActiveTrips($query, int $driverUserId)
    {
        return $query
            ->where('driver_id', $driverUserId)
            ->whereNot('status', 'cancelled')
            ->whereHas('bookings', fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected']))
            ->whereDoesntHave('bookings', fn ($q) => $q
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->where('trip_status', 'awaiting_completion'));
    }

    /** Chuyến đang hiện trên dashboard TX — chặn «Đặt ngay» và coi TX bận phục vụ. */
    public function blocksDriverCatalogBooking(): bool
    {
        if ($this->driverRelevantBookings()->isEmpty()) {
            return false;
        }

        $phase = $this->driverWorkflowPhase();
        if ($phase === 'settled' || ! in_array($phase, ['upcoming', 'active'], true)) {
            return false;
        }

        return $this->isVisibleOnDriverDashboard();
    }

    /** Ẩn trên dashboard tài xế — chỉ quản lý xử lý. */
    public function isVisibleOnDriverDashboard(): bool
    {
        if ((int) $this->driver_id < 1) {
            return false;
        }

        if ($this->driverPendingClosure()) {
            return false;
        }

        if ($this->driverRelevantBookings()->contains(
            fn (Booking $booking): bool => $booking->trip_status === 'awaiting_completion',
        )) {
            return false;
        }

        if ($this->resolvedDriverStage() === self::DRIVER_STAGE_ASSIGNED) {
            $booking = $this->driverRelevantBookings()->first();
            $pickupAt = $booking?->operationalPickupAt();
            if ($pickupAt && $pickupAt->lte(now())) {
                return false;
            }
        }

        return true;
    }

    public function scopeForDriverHistory($query, int $driverUserId)
    {
        return $query->where(function ($q) use ($driverUserId): void {
            $q->whereHas('bookings', function ($b) use ($driverUserId): void {
                $b->assignedToDriver($driverUserId)->visibleInDriverHistory();
            })->orWhere(function ($q2) use ($driverUserId): void {
                $q2->where('driver_id', $driverUserId)
                    ->whereHas('bookings', fn ($b) => $b->visibleInDriverHistory());
            });
        });
    }
}
