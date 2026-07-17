<?php

namespace App\Models;

use App\Services\DriverAvailabilityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ScheduleTemplate extends Model
{
    protected $fillable = [
        'route_id',
        'vehicle_id',
        'driver_id',
        'driver_name',
        'departure_time',
        'expected_arrival_time',
        'whole_car_price',
        'duration_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'whole_car_price'  => 'decimal:2',
        ];
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

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'template_id');
    }

    public function hasFixedDepartureTime(): bool
    {
        return $this->departure_time !== null && trim((string) $this->departure_time) !== '';
    }

    public function departureAt(Carbon $serviceDate): Carbon
    {
        if (! $this->hasFixedDepartureTime()) {
            return app(DriverAvailabilityService::class)
                ->resolveDepartureTime($this, $serviceDate->toDateString(), null);
        }

        return $this->clockOnDate($serviceDate, $this->departure_time);
    }

    public function expectedArrivalAt(Carbon $serviceDate): Carbon
    {
        if ($this->expected_arrival_time && $this->hasFixedDepartureTime()) {
            $departure = $this->departureAt($serviceDate);
            $arrival = $this->clockOnDate($serviceDate, $this->expected_arrival_time);

            if ($arrival <= $departure) {
                $arrival->addDay();
            }

            return $arrival;
        }

        return $this->departureAt($serviceDate)->copy()->addMinutes((int) ($this->duration_minutes ?? 720));
    }

    public function wholeCarPriceAmount(): float
    {
        if ($this->whole_car_price !== null) {
            return (float) $this->whole_car_price;
        }

        return (float) app(\App\Services\TripPricingService::class)->wholeCarPrice($this);
    }

    public function capacity(): int
    {
        $this->loadMissing('vehicle');

        return (int) ($this->vehicle?->capacity ?? 0);
    }

    /** Thông tin lịch chuyến theo ngày khách chọn. */
    private function clockOnDate(Carbon $serviceDate, mixed $time): Carbon
    {
        $timeStr = \App\Support\DepartureTimeDisplay::normalizeForClock($time) . ':00';

        return Carbon::parse($serviceDate->toDateString() . ' ' . $timeStr, config('app.timezone'));
    }
}
