<?php

namespace App\Services;

use App\Models\ScheduleTemplate;
use App\Models\TripRoute;
use App\Models\Vehicle;
use App\Support\LocationCatalog;
use App\Support\RouteDistanceCatalog;
use App\Support\ServiceDate;
use App\Support\VehicleCapacityOptions;
use App\Support\VehicleDisplay;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Carbon\Carbon;

class TripOfferService
{
    public function __construct(
        private readonly VehiclePhotoService $vehiclePhotos,
        private readonly TripPricingService $pricing,
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
            'departure_time'         => substr((string) $template->departure_time, 0, 5) ?: null,
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

            $departureStored = $this->nullableTime($data['departure_time'] ?? null);
            $arrivalStored = $arrivalTime !== '' ? $this->normalizeTime($arrivalTime) : null;

            $vehicle = $this->resolveVehicle($operatorId, $seats, $data['photo'] ?? null, $editingFrom);

            if ($editingFrom) {
                $sameSlot = (int) $editingFrom->route_id === (int) $route->id
                    && (int) $editingFrom->vehicle_id === (int) $vehicle->id
                    && $this->sameDepartureSlot($editingFrom->departure_time, $departureStored);

                if (! $sameSlot) {
                    $editingFrom->update(['status' => 'inactive']);
                }
            }

            $template = $this->findTemplateSlot((int) $route->id, (int) $vehicle->id, $departureStored);

            $payload = [
                'driver_id'                  => null,
                'driver_name'                => 'Chờ khách đặt',
                'whole_car_price'            => (int) $data['whole_car_one_way'],
                'seat_price'                 => (int) $data['seat_one_way'],
                'whole_car_round_trip_price' => (int) $data['whole_car_round'],
                'seat_round_trip_price'      => (int) $data['seat_round'],
                'expected_arrival_time'      => $arrivalStored,
                'status'                     => 'active',
            ];

            if ($template) {
                $template->update($payload);
            } else {
                $template = ScheduleTemplate::query()->create(array_merge($payload, [
                    'route_id'       => $route->id,
                    'vehicle_id'     => $vehicle->id,
                    'departure_time' => $departureStored,
                ]));
            }

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

    /** @param  list<int>  $templateIds */
    public function deleteOffers(array $templateIds, int $operatorId): int
    {
        $deleted = 0;

        foreach (array_unique($templateIds) as $templateId) {
            $template = ScheduleTemplate::query()->find($templateId);
            if (! $template) {
                continue;
            }

            $this->deleteOffer($template, $operatorId);
            $deleted++;
        }

        if ($deleted === 0) {
            throw new InvalidArgumentException('Không có tuyến nào được xóa.');
        }

        return $deleted;
    }

    /**
     * Tạo nhanh tất cả tuyến từ một điểm đi — giá/km lấy từ cấu hình admin.
     *
     * @param  array{
     *   departure: string,
     *   service_date: string,
     *   departure_time: string,
     *   expected_arrival_time?: string|null,
     *   seats: int,
     *   photo?: \Illuminate\Http\UploadedFile|null,
     * }  $data
     * @return array{
     *   created: int,
     *   updated: int,
     *   skipped: int,
     *   service_date: string,
     *   service_date_label: string,
     *   destinations: list<string>,
     *   schedules_opened: int,
     * }
     */
    public function bulkCreateFromDeparture(int $operatorId, array $data): array
    {
        $seats = (int) ($data['seats'] ?? 0);
        if (! VehicleCapacityOptions::isAllowed($seats)) {
            throw new InvalidArgumentException('Vui lòng chọn số chỗ hợp lệ.');
        }

        $departure = trim((string) $data['departure']);
        if ($departure === '') {
            throw new InvalidArgumentException('Vui lòng chọn điểm đi.');
        }

        if (! LocationCatalog::isAllowed($departure)) {
            throw new InvalidArgumentException('Điểm đi không hợp lệ.');
        }

        $photo = ($data['photo'] ?? null) instanceof UploadedFile ? $data['photo'] : null;
        $vehicle = $this->resolveVehicle($operatorId, $seats, $photo);

        $departureStored = $this->nullableTime($data['departure_time'] ?? null);
        $arrivalTime = isset($data['expected_arrival_time']) ? trim((string) $data['expected_arrival_time']) : '';
        $arrivalStored = $arrivalTime !== '' ? $this->normalizeTime($arrivalTime) : null;
        $serviceDateString = trim((string) ($data['service_date'] ?? ''));
        $serviceDate = $this->resolveServiceDate($serviceDateString);
        $destinations = $this->destinationsForDeparture($departure);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $schedulesOpened = 0;
        $templates = [];

        DB::transaction(function () use (
            $departure,
            $departureStored,
            $arrivalStored,
            $seats,
            $vehicle,
            $destinations,
            &$created,
            &$updated,
            &$skipped,
            &$templates,
        ): void {
            foreach ($destinations as $destination) {
                $destination = trim((string) $destination);
                if ($destination === '' || $destination === $departure) {
                    $skipped++;

                    continue;
                }

                $distance = RouteDistanceCatalog::resolveKm($departure, $destination);
                if ($distance <= 0) {
                    $skipped++;

                    continue;
                }

                $prices = $this->pricing->suggestOfferPrices($distance, $seats);

                $route = TripRoute::query()->updateOrCreate(
                    ['departure' => $departure, 'destination' => $destination],
                    [
                        'base_price'  => $prices['seat_one_way'],
                        'distance_km' => $distance,
                        'is_active'   => true,
                    ],
                );

                $existing = $this->findTemplateSlot((int) $route->id, (int) $vehicle->id, $departureStored);

                $payload = [
                    'driver_id'                  => null,
                    'driver_name'                => 'Chờ khách đặt',
                    'whole_car_price'            => $prices['whole_car_one_way'],
                    'seat_price'                 => $prices['seat_one_way'],
                    'whole_car_round_trip_price' => $prices['whole_car_round'],
                    'seat_round_trip_price'      => $prices['seat_round'],
                    'expected_arrival_time'      => $arrivalStored,
                    'status'                     => 'active',
                ];

                if ($existing) {
                    $existing->update($payload);
                    $template = $existing->fresh();
                    $updated++;
                } else {
                    $template = ScheduleTemplate::query()->create(array_merge($payload, [
                        'route_id'       => $route->id,
                        'vehicle_id'     => $vehicle->id,
                        'departure_time' => $departureStored,
                    ]));
                    $created++;
                }

                $templates[] = $template->fresh(['route', 'vehicle']);
            }
        });

        $lifecycle = app(ScheduleLifecycleService::class);

        foreach ($templates as $template) {
            if ($departureStored !== null) {
                $departureAt = $template->departureAt($serviceDate->copy());
                if ($departureAt <= now()) {
                    continue;
                }
            }

            try {
                $lifecycle->resolveScheduleForBooking($template, $serviceDateString);
                $schedulesOpened++;
            } catch (\Throwable) {
                // Bỏ qua tuyến không mở được lịch ngay lúc tạo nhanh.
            }
        }

        return [
            'created'            => $created,
            'updated'            => $updated,
            'skipped'            => $skipped,
            'service_date'       => $serviceDateString,
            'service_date_label' => $serviceDate->format('d/m/Y'),
            'destinations'       => $destinations,
            'schedules_opened'   => $schedulesOpened,
        ];
    }

    /** @return array<int, string> capacity => photo URL */
    public function vehiclePhotoUrlsForOperator(int $operatorId): array
    {
        $urls = [];

        Vehicle::query()
            ->where('operator_id', $operatorId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->each(function (Vehicle $vehicle) use (&$urls): void {
                $url = $vehicle->photoUrl();
                if ($url && ! isset($urls[(int) $vehicle->capacity])) {
                    $urls[(int) $vehicle->capacity] = $url;
                }
            });

        return $urls;
    }

    public function operatorHasVehiclePhoto(int $operatorId, int $capacity): bool
    {
        return $this->findVehicleWithPhoto($operatorId, $capacity) !== null;
    }

    /** @return list<string> */
    private function destinationsForDeparture(string $departure): array
    {
        if ($departure === LocationCatalog::hub()) {
            return LocationCatalog::hubDestinations();
        }

        return collect(LocationCatalog::all())
            ->reject(fn (string $name): bool => $name === $departure)
            ->values()
            ->all();
    }

    private function findVehicleWithPhoto(int $operatorId, int $capacity): ?Vehicle
    {
        return Vehicle::query()
            ->where('operator_id', $operatorId)
            ->where('capacity', $capacity)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->first(fn (Vehicle $vehicle): bool => (bool) $vehicle->photoUrl());
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

    private function nullableTime(mixed $time): ?string
    {
        $raw = trim((string) $time);

        return $raw === '' ? null : $this->normalizeTime($raw);
    }

    private function findTemplateSlot(int $routeId, int $vehicleId, ?string $departureTime): ?ScheduleTemplate
    {
        $query = ScheduleTemplate::query()
            ->where('route_id', $routeId)
            ->where('vehicle_id', $vehicleId);

        if ($departureTime === null) {
            $query->whereNull('departure_time');
        } else {
            $query->where('departure_time', $departureTime);
        }

        return $query->first();
    }

    private function sameDepartureSlot(mixed $left, ?string $right): bool
    {
        $leftStored = $left === null || trim((string) $left) === ''
            ? null
            : substr((string) $left, 0, 8);

        $rightStored = $right === null
            ? null
            : substr($right, 0, 8);

        return $leftStored === $rightStored;
    }

    private function resolveServiceDate(mixed $value): Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            throw new InvalidArgumentException('Vui lòng chọn ngày chạy.');
        }

        $date = ServiceDate::parse($raw);
        if ($date->toDateString() < ServiceDate::today()) {
            throw new InvalidArgumentException('Ngày chạy phải từ hôm nay trở đi.');
        }

        return $date;
    }
}
