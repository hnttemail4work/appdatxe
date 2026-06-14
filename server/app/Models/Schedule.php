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
        'service_date',
        'available_seats',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'departure_time' => 'datetime',
            'service_date'   => 'date',
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

    public function seatReservations()
    {
        return $this->hasMany(SeatReservation::class);
    }

    public function driverTripRequests()
    {
        return $this->hasMany(DriverTripRequest::class);
    }

    public function capacity(): int
    {
        return (int) ($this->vehicle?->capacity ?? 0);
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
        if (! in_array($this->status, ['scheduled'], true)) {
            return false;
        }

        return $this->departure_time > now() && $this->bookedSeatsCount() < $this->capacity();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'running'   => 'Đang chạy',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            'draft'     => 'Nháp',
            default     => $this->departure_time <= now() ? 'Sắp chạy' : 'Đã lên lịch',
        };
    }
}
