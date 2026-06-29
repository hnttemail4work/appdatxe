<?php

namespace App\Services;

use App\Models\ScheduleTemplate;
use App\Models\TripRoute;
use App\Models\Vehicle;
use App\Support\RouteDistanceCatalog;
use App\Support\VehicleCapacityOptions;
use App\Support\VehicleDisplay;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TripOfferService
{
    public function __construct(
        private readonly VehiclePhotoService $vehiclePhotos,
    ) {
    }

    /** @return Collection<int, ScheduleTemplate> */
    public function activeOffersForOperator(int $operatorId): Collection
    {
        return ScheduleTemplate::query()
            ->where('status', 'active')
            ->with(['route', 'vehicle'])
            ->whereHas('vehicle', fn ($q) => $q->where('operator_id', $operatorId))
            ->orderBy('route_id')
            ->orderBy('departure_time')
            ->orderBy(
                Vehicle::select('capacity')
                    ->whereColumn('vehicles.id', 'schedule_templates.vehicle_id')
                    ->limit(1),
            )
            ->get();
    }

    public function assertOperatorOwnsTemplate(ScheduleTemplate $template, int $operatorId): void
    {
        $template->loadMissing('vehicle');
        if ((int) $template->vehicle->operator_id !== $operatorId) {
            throw new InvalidArgumentException('Không có quyền chỉnh sửa chuyến này.');
        }
    }

    /** @return array<string, mixed> */
    public function formDataFromTemplate(ScheduleTemplate $template): array
    {
        $template->loadMissing(['route', 'vehicle']);

        return [
            'route'                  => $template->route,
            'departure_time'         => substr((string) $template->departure_time, 0, 5),
            'expected_arrival_time'  => $template->expected_arrival_time
                ? substr((string) $template->expected_arrival_time, 0, 5)
                : null,
            'distance_km'            => $template->route
                ? (int) ($template->route->distance_km ?: RouteDistanceCatalog::resolveKm(
                    $template->route->departure,
                    $template->route->destination,
                ))
                : null,
            'vehicle'                => [
                'seats'             => (int) $template->vehicle->capacity,
                'whole_car_one_way' => $template->whole_car_price !== null ? (int) $template->whole_car_price : '',
                'seat_one_way'      => $template->seat_price !== null ? (int) $template->seat_price : '',
                'whole_car_round'   => $template->whole_car_round_trip_price !== null ? (int) $template->whole_car_round_trip_price : '',
                'seat_round'        => $template->seat_round_trip_price !== null ? (int) $template->seat_round_trip_price : '',
                'photo_url'         => $template->vehicle->photoUrl(),
            ],
        ];
    }

    /**
     * @param  array{
     *   departure: string,
     *   destination: string,
     *   departure_time: string,
     *   expected_arrival_time?: string|null,
     *   seats: int,
     *   whole_car_one_way: int,
     *   seat_one_way: int,
     *   whole_car_round: int,
     *   seat_round: int,
     *   distance_km?: int|null,
     *   photo?: UploadedFile|null,
     * }  $data
     * @return array{route: TripRoute, anchor: ScheduleTemplate|null}
     */
    public function save(int $operatorId, array $data, ?ScheduleTemplate $editingFrom = null): array
    {
        $seats = (int) ($data['seats'] ?? 0);
        if (! VehicleCapacityOptions::isAllowed($seats)) {
            throw new InvalidArgumentException('Vui lòng chọn số chỗ hợp lệ.');
        }

        $arrivalTime = isset($data['expected_arrival_time']) ? trim((string) $data['expected_arrival_time']) : '';

        return DB::transaction(function () use ($operatorId, $data, $seats, $editingFrom, $arrivalTime): array {
            $departure = trim($data['departure']);
            $destination = trim($data['destination']);
            $manualKm = isset($data['distance_km']) ? (int) $data['distance_km'] : 0;
            $distance = $manualKm > 0
                ? $manualKm
                : RouteDistanceCatalog::resolveKm($departure, $destination);

            $route = TripRoute::query()->updateOrCreate(
                ['departure' => $departure, 'destination' => $destination],
                [
                    'base_price'  => max((int) $data['seat_one_way'], 0),
                    'distance_km' => $distance > 0 ? $distance : null,
                    'is_active'   => true,
                ],
            );

            $departureTime = $this->normalizeTime($data['departure_time']);
            $arrivalStored = $arrivalTime !== '' ? $this->normalizeTime($arrivalTime) : null;

            $vehicle = $this->resolveVehicle($operatorId, $seats, $data['photo'] ?? null, $editingFrom);

            if ($editingFrom) {
                $sameSlot = (int) $editingFrom->route_id === (int) $route->id
                    && substr((string) $editingFrom->departure_time, 0, 5) === substr($departureTime, 0, 5)
                    && (int) $editingFrom->vehicle_id === (int) $vehicle->id;

                if (! $sameSlot) {
                    $editingFrom->update(['status' => 'inactive']);
                }
            }

            $template = ScheduleTemplate::query()->updateOrCreate(
                [
                    'route_id'       => $route->id,
                    'vehicle_id'     => $vehicle->id,
                    'departure_time' => $departureTime,
                ],
                [
                    'driver_id'                  => null,
                    'driver_name'                => 'Chờ khách đặt',
                    'whole_car_price'            => (int) $data['whole_car_one_way'],
                    'seat_price'                 => (int) $data['seat_one_way'],
                    'whole_car_round_trip_price' => (int) $data['whole_car_round'],
                    'seat_round_trip_price'      => (int) $data['seat_round'],
                    'expected_arrival_time'      => $arrivalStored,
                    'status'                     => 'active',
                ],
            );

            return [
                'route'  => $route->fresh(),
                'anchor' => $template,
            ];
        });
    }

    public function deleteOffer(ScheduleTemplate $template, int $operatorId): ScheduleTemplate
    {
        $this->assertOperatorOwnsTemplate($template, $operatorId);
        $template->loadMissing('route');
        $template->update(['status' => 'inactive']);

        return $template;
    }

    private function resolveVehicle(
        int $operatorId,
        int $capacity,
        ?UploadedFile $photo,
        ?ScheduleTemplate $editingFrom = null,
    ): Vehicle {
        $vehicle = Vehicle::query()
            ->where('operator_id', $operatorId)
            ->where('capacity', $capacity)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if (! $vehicle) {
            $vehicle = Vehicle::query()->create([
                'operator_id'   => $operatorId,
                'license_plate' => sprintf('M-%d-%dCH', $operatorId, $capacity),
                'type'          => VehicleDisplay::typeForCapacity($capacity),
                'capacity'      => $capacity,
                'status'        => 'active',
            ]);
        }

        if ($photo instanceof UploadedFile) {
            $this->vehiclePhotos->storeForVehicle($vehicle, $photo);
            $vehicle = $vehicle->fresh();
        }

        if (! $vehicle->photoUrl()) {
            throw new InvalidArgumentException('Vui lòng tải ảnh xe.');
        }

        return $vehicle;
    }

    private function normalizeTime(string $time): string
    {
        return \App\Support\DepartureTimeDisplay::storageValue($time);
    }
}
