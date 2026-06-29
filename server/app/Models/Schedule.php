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
        'seat_price',
        'whole_car_price',
        'service_date',
        'available_seats',
        'status',
        'trip_code',
    ];

    protected function casts(): array
    {
        return [
            'departure_time' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'service_date'   => 'date',
            'seat_price'      => 'decimal:2',
            'whole_car_price' => 'decimal:2',
            'available_seats' => 'integer',
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

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function tripSettlement()
    {
        return $this->hasOne(DriverTripSettlement::class);
    }

    public function seatReservations()
    {
        return $this->hasMany(SeatReservation::class);
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

    public function seatPrice(): float
    {
        if ($this->seat_price !== null) {
            return (float) $this->seat_price;
        }

        return (float) app(\App\Services\TripPricingService::class)
            ->sharedSeatFromWholeCar((int) $this->wholeCarPriceAmount());
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

        return $this->seatReservations()
            ->whereIn('status', ['held', 'booked'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
    }

    public function bookedSeatsCount(): int
    {
        return $this->activeReservationCount();
    }

    public function seatsLabel(): string
    {
        $capacity = $this->capacity();

        return $this->bookedSeatsCount() . '/' . $capacity;
    }

    public function isBookable(): bool
    {
        if ($this->displayStatus() !== 'scheduled') {
            return false;
        }

        return $this->departure_time > now() && $this->bookedSeatsCount() < $this->capacity();
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

    public function tripTimeLabel(): string
    {
        $departure = $this->departure_time->format('H:i');
        $arrival = $this->expectedArrivalAt();

        if ($arrival->isSameDay($this->departure_time)) {
            return $departure . ' → ' . $arrival->format('H:i') . ', ' . $this->departure_time->format('d/m/Y');
        }

        return $departure . ', ' . $this->departure_time->format('d/m')
            . ' → ' . $arrival->format('H:i') . ', ' . $arrival->format('d/m/Y');
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

        if ($this->status === 'running' || $this->departure_time <= now()) {
            return 'running';
        }

        return 'scheduled';
    }

    public function statusLabel(): string
    {
        $display = $this->displayStatus();

        if ($display === 'scheduled' && $this->departure_time <= now()) {
            return 'Hết giờ';
        }

        if ($display === 'scheduled') {
            return 'Sắp chạy';
        }

        return match ($display) {
            'running'   => 'Đang chạy',
            'completed' => 'Chạy xong',
            'cancelled' => 'Đã hủy',
            'draft'     => 'Nháp',
            default     => 'Sắp chạy',
        };
    }

    public function activeGuestBookingsCount(): int
    {
        if ($this->relationLoaded('bookings')) {
            return $this->bookings
                ->filter(fn (Booking $b): bool => ! in_array($b->booking_status, ['cancelled', 'rejected'], true)
                    && ! $b->isExpired())
                ->count();
        }

        return $this->bookings()
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->get()
            ->filter(fn (Booking $b): bool => ! $b->isExpired())
            ->count();
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

    public function tripRevenueTotal(): float
    {
        return (float) $this->driverRelevantBookings()->sum(fn (Booking $b) => (float) $b->total_price);
    }

    /** Bước xử lý trên dashboard tài xế — một lần cho cả chuyến xe. */
    public function driverWorkflowPhase(): string
    {
        $this->loadMissing('tripSettlement');

        if ($this->tripSettlement) {
            return DriverTripSettlement::workflowPhaseFromStatus($this->tripSettlement->status);
        }

        $bookings = $this->driverRelevantBookings();
        if ($bookings->isEmpty()) {
            return 'other';
        }

        if ($bookings->every(fn (Booking $b): bool => $b->trip_status === 'completed')) {
            return 'needs_settle';
        }

        $hasActive = $bookings->contains(function (Booking $b): bool {
            $this->loadMissing('bookings');

            return in_array($b->trip_status, ['confirmed', 'pending'], true)
                && ($this->status === 'running' || $this->departure_time <= now());
        });

        if ($hasActive) {
            return 'active';
        }

        return 'upcoming';
    }

    public function driverWorkflowLabel(): string
    {
        $bookings = $this->driverRelevantBookings();
        $count = $bookings->count();

        return match ($this->driverWorkflowPhase()) {
            'upcoming'          => $count > 1 ? "Sắp chạy ({$count} vé)" : 'Sắp chạy',
            'active'            => $count > 1 ? "Đang phục vụ ({$count} vé)" : 'Đang phục vụ',
            'needs_settle'      => 'Chuyển phí nền tảng',
            'enter_settle_code' => 'Nhập mã kết chuyến',
            'settled'           => 'Hoàn thành chuyến',
            default             => '—',
        };
    }

    public function driverWorkflowColor(): string
    {
        return match ($this->driverWorkflowPhase()) {
            'upcoming'           => \App\Support\StatusBadge::NEUTRAL,
            'active'             => \App\Support\StatusBadge::GOLD,
            'needs_settle'      => \App\Support\StatusBadge::PENDING,
            'enter_settle_code' => \App\Support\StatusBadge::PENDING,
            'settled'           => \App\Support\StatusBadge::SUCCESS,
            default              => \App\Support\StatusBadge::NEUTRAL,
        };
    }

    public function driverViewSortKey(): string
    {
        $phase = $this->driverWorkflowPhase();

        $priority = match ($phase) {
            'active'            => 0,
            'enter_settle_code' => 1,
            'needs_settle'      => 2,
            'upcoming'          => 3,
            'settled'           => 4,
            default             => 5,
        };

        $timeKey = in_array($phase, ['active', 'enter_settle_code', 'needs_settle'], true)
            ? 9_999_999_999 - $this->departure_time->timestamp
            : $this->departure_time->timestamp;

        return sprintf('%02d-%010d', $priority, $timeKey);
    }

    public function driverIncompleteBookings(): \Illuminate\Support\Collection
    {
        return $this->driverRelevantBookings()
            ->filter(fn (Booking $b): bool => $b->trip_status !== 'completed')
            ->values();
    }

    /** Vé đã kết xong — hiển thị mục gần đây. */
    public function driverSettledBookings(): \Illuminate\Support\Collection
    {
        $bookings = $this->relationLoaded('bookings')
            ? $this->bookings
            : $this->bookings()->get();

        return $bookings
            ->filter(fn (Booking $b): bool => ! in_array($b->booking_status, ['cancelled', 'rejected'], true))
            ->values();
    }
}
