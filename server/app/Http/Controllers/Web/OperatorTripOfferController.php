<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ScheduleTemplate;
use App\Services\TripOfferService;
use App\Services\TripPricingService;
use App\Support\LocationCatalog;
use App\Support\PlatformFees;
use App\Support\PageList;
use App\Support\VehicleCapacityOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class OperatorTripOfferController extends Controller
{
    public function __construct(
        private readonly TripOfferService $offers,
        private readonly TripPricingService $pricing,
    ) {
    }

    public function create(Request $request)
    {
        $editId = $request->query('edit');
        if ($editId) {
            $template = ScheduleTemplate::query()->find($editId);
            if ($template) {
                try {
                    $this->offers->assertOperatorOwnsTemplate($template, Auth::id());
                    $formData = $this->offers->formDataFromTemplate($template);

                    return view('operator.trip-offers.create', $this->pageData($template, $formData));
                } catch (InvalidArgumentException) {
                    abort(403);
                }
            }
        }

        return view('operator.trip-offers.create', $this->pageData());
    }

    public function edit(ScheduleTemplate $scheduleTemplate)
    {
        try {
            $this->offers->assertOperatorOwnsTemplate($scheduleTemplate, Auth::id());
        } catch (InvalidArgumentException) {
            abort(403);
        }

        return redirect()->route('operator.tripOffers.create', [
            'edit' => $scheduleTemplate->id,
        ])->withFragment('trip-offer-form');
    }

    public function store(Request $request)
    {
        return $this->persist($request);
    }

    public function update(Request $request, ScheduleTemplate $scheduleTemplate)
    {
        try {
            $this->offers->assertOperatorOwnsTemplate($scheduleTemplate, Auth::id());
        } catch (InvalidArgumentException) {
            abort(403);
        }

        return $this->persist($request, $scheduleTemplate);
    }

    public function quote(Request $request)
    {
        $request->validate([
            'distance_km' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'departure'   => ['required_without:distance_km', 'string', LocationCatalog::inRule()],
            'destination' => [
                'required_without:distance_km',
                'string',
                'different:departure',
                LocationCatalog::inRule(),
            ],
            'seats'       => ['nullable', 'integer', Rule::in(VehicleCapacityOptions::STANDARD)],
        ]);

        $distance = $request->filled('distance_km')
            ? (int) $request->input('distance_km')
            : \App\Support\RouteDistanceCatalog::resolveKm(
                $request->input('departure'),
                $request->input('destination'),
            );

        $rate = $distance > 100
            ? PlatformFees::kmRateOver100()
            : PlatformFees::kmRateUnder100();

        $pricesBySeats = [];
        foreach (VehicleCapacityOptions::STANDARD as $capacity) {
            $pricesBySeats[(string) $capacity] = $this->pricing->suggestOfferPrices($distance, $capacity);
        }

        $payload = [
            'distance_km'     => $distance,
            'rate_per_km'     => $rate,
            'prices_by_seats' => $pricesBySeats,
        ];

        if ($request->filled('seats')) {
            $payload = array_merge(
                $payload,
                $this->pricing->suggestOfferPrices($distance, (int) $request->input('seats')),
            );
        }

        return response()->json($payload);
    }

    public function destroy(ScheduleTemplate $scheduleTemplate)
    {
        try {
            $this->offers->deleteOffer($scheduleTemplate, Auth::id());
        } catch (InvalidArgumentException) {
            abort(403);
        }

        return redirect()->route('operator.tripOffers.create');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'template_ids'   => ['required', 'array', 'min:1'],
            'template_ids.*' => ['integer', 'distinct'],
        ], [
            'template_ids.required' => 'Vui lòng chọn ít nhất một tuyến.',
            'template_ids.min'      => 'Vui lòng chọn ít nhất một tuyến.',
        ]);

        try {
            $this->offers->deleteOffers(
                array_map('intval', $validated['template_ids']),
                Auth::id(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['template_ids' => $e->getMessage()]);
        }

        return redirect()->route('operator.tripOffers.create');
    }

    public function bulkQuickCreate(Request $request)
    {
        $seats = (int) $request->input('seats', 0);
        $hasVehiclePhoto = $this->offers->operatorHasVehiclePhoto(Auth::id(), $seats);

        $request->validate([
            'departure'             => ['required', 'string', LocationCatalog::inRule()],
            'destinations'          => ['required', 'array', 'min:1'],
            'destinations.*'        => ['required', 'string', LocationCatalog::inRule()],
            'service_date'          => ['required', 'date', 'after_or_equal:today'],
            'departure_time'        => ['nullable', 'date_format:H:i'],
            'expected_arrival_time' => ['nullable', 'date_format:H:i'],
            'seats'                 => ['required', 'integer', Rule::in(VehicleCapacityOptions::STANDARD)],
            'vehicle_photo'         => $hasVehiclePhoto
                ? ['nullable', 'image', 'max:5120']
                : ['required', 'image', 'max:5120'],
        ], [
            'departure.required'        => 'Vui lòng chọn điểm đi.',
            'destinations.required'     => 'Vui lòng chọn ít nhất một điểm đến.',
            'destinations.min'          => 'Vui lòng chọn ít nhất một điểm đến.',
            'service_date.required'     => 'Vui lòng chọn ngày chạy.',
            'service_date.after_or_equal' => 'Ngày chạy phải từ hôm nay trở đi.',
            'departure_time.required'   => 'Vui lòng nhập giờ khởi hành.',
            'seats.required'            => 'Vui lòng chọn số chỗ.',
            'seats.in'                  => 'Số chỗ không hợp lệ.',
            'vehicle_photo.required'    => 'Vui lòng tải ảnh xe.',
            'vehicle_photo.image'       => 'Ảnh xe phải là file hình ảnh.',
        ]);

        try {
            $result = $this->offers->bulkCreateFromDeparture(Auth::id(), [
                'departure'             => $request->input('departure'),
                'destinations'          => $request->input('destinations', []),
                'service_date'          => $request->input('service_date'),
                'departure_time'        => $request->input('departure_time'),
                'expected_arrival_time' => $request->input('expected_arrival_time'),
                'seats'                 => $seats,
                'photo'                 => $request->file('vehicle_photo'),
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['quick_trip' => $e->getMessage()])->withInput();
        }

        $total = $result['created'] + $result['updated'];

        return redirect()
            ->route('operator.tripOffers.create')
            ->with('success', sprintf(
                'Đã tạo nhanh %d tuyến từ %s (ngày chạy %s): %d mới, %d cập nhật%s.',
                $total,
                $request->input('departure'),
                $result['service_date_label'],
                $result['created'],
                $result['updated'],
                $result['skipped'] > 0 ? ', ' . $result['skipped'] . ' bỏ qua' : '',
            ));
    }

    private function persist(Request $request, ?ScheduleTemplate $editingFrom = null)
    {
        $hasVehiclePhoto = $this->operatorVehicleHasPhoto($editingFrom);

        $request->validate([
            'departure'             => ['required', 'string', LocationCatalog::inRule()],
            'destination'           => ['required', 'string', 'different:departure', LocationCatalog::inRule()],
            'departure_time'        => ['nullable', 'date_format:H:i'],
            'expected_arrival_time' => ['nullable', 'date_format:H:i'],
            'seats'                 => ['required', 'integer', Rule::in(VehicleCapacityOptions::STANDARD)],
            'whole_car_one_way'     => ['required', 'integer', 'min:10000'],
            'seat_one_way'          => ['required', 'integer', 'min:10000'],
            'whole_car_round'       => ['required', 'integer', 'min:10000'],
            'seat_round'            => ['required', 'integer', 'min:10000'],
            'distance_km'           => ['nullable', 'integer', 'min:1', 'max:2000'],
            'vehicle_photo'         => $hasVehiclePhoto
                ? ['nullable', 'image', 'max:5120']
                : ['required', 'image', 'max:5120'],
        ], $this->offerValidationMessages());

        try {
            $saved = $this->offers->save(Auth::id(), $this->payloadFromRequest($request), $editingFrom);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['offer' => $e->getMessage()])->withInput();
        }

        $route = $saved['route'];
        $editAnchor = $saved['anchor'] ?? $editingFrom;

        return redirect()
            ->route('operator.tripOffers.create', $editAnchor ? ['edit' => $editAnchor->id] : [])
            ->withFragment($editAnchor ? 'trip-offer-form' : '')
            ->with('success', sprintf(
                'Đã lưu tuyến %s → %s — hiển thị trên trang đặt vé khách.',
                $route->departure,
                $route->destination,
            ));
    }

    /** @return array<string, mixed> */
    private function payloadFromRequest(Request $request): array
    {
        return [
            'departure'             => $request->input('departure'),
            'destination'           => $request->input('destination'),
            'departure_time'        => $request->input('departure_time'),
            'expected_arrival_time' => $request->input('expected_arrival_time'),
            'seats'                 => (int) $request->input('seats'),
            'whole_car_one_way'     => (int) $request->input('whole_car_one_way'),
            'seat_one_way'          => (int) $request->input('seat_one_way'),
            'whole_car_round'       => (int) $request->input('whole_car_round'),
            'seat_round'            => (int) $request->input('seat_round'),
            'distance_km'           => $request->filled('distance_km') ? (int) $request->input('distance_km') : null,
            'photo'                 => $request->file('vehicle_photo'),
        ];
    }

    /** @return array<string, mixed> */
    private function pageData(?ScheduleTemplate $editingTemplate = null, ?array $formData = null): array
    {
        return [
            'editingTemplate' => $editingTemplate,
            'formAction'      => $editingTemplate
                ? route('operator.tripOffers.update', $editingTemplate)
                : route('operator.tripOffers.store'),
            'formMethod'      => $editingTemplate ? 'PUT' : 'POST',
            'formData'        => $formData ?? [
                'route'                 => null,
                'departure_time'        => old('departure_time', ''),
                'expected_arrival_time' => old('expected_arrival_time', ''),
                'distance_km'           => old('distance_km', ''),
                'vehicle'               => [
                    'seats'             => old('seats', ''),
                    'whole_car_one_way' => old('whole_car_one_way', ''),
                    'seat_one_way'      => old('seat_one_way', ''),
                    'whole_car_round'   => old('whole_car_round', ''),
                    'seat_round'        => old('seat_round', ''),
                    'photo_url'         => null,
                ],
            ],
            'activeOffers'    => PageList::paginateCollection(
                $this->offers->activeOffersForOperator(Auth::id()),
                request(),
                'offers_page',
            ),
            'quoteUrl'        => route('operator.tripOffers.quote'),
            'quickTrip'       => [
                'service_date'              => now()->toDateString(),
                'default_departure'         => LocationCatalog::hub(),
                'vehicle_photos'            => $this->offers->vehiclePhotoUrlsForOperator(Auth::id()),
                'destinations_by_departure' => collect(LocationCatalog::all())
                    ->mapWithKeys(fn (string $departure): array => [
                        $departure => $this->offers->destinationsForDeparture($departure),
                    ])
                    ->all(),
            ],
        ];
    }

    private function operatorVehicleHasPhoto(?ScheduleTemplate $editingFrom): bool
    {
        if (! $editingFrom) {
            return false;
        }

        $editingFrom->loadMissing('vehicle');

        return (bool) $editingFrom->vehicle?->photoUrl();
    }

    /** @return array<string, string> */
    private function offerValidationMessages(): array
    {
        return [
            'departure.required'             => 'Vui lòng chọn điểm đi.',
            'destination.required'           => 'Vui lòng chọn điểm đến.',
            'destination.different'          => 'Điểm đến phải khác điểm đi.',
            'departure_time.required'        => 'Vui lòng nhập giờ khởi hành.',
            'seats.required'                 => 'Vui lòng chọn số chỗ.',
            'seats.in'                       => 'Số chỗ không hợp lệ.',
            'whole_car_one_way.required'     => 'Vui lòng nhập giá cả xe một chiều.',
            'seat_one_way.required'          => 'Vui lòng nhập giá ghép xe một chiều.',
            'whole_car_round.required'       => 'Vui lòng nhập giá cả xe khứ hồi.',
            'seat_round.required'            => 'Vui lòng nhập giá ghép xe khứ hồi.',
            'vehicle_photo.required'         => 'Vui lòng tải ảnh xe.',
            'vehicle_photo.image'            => 'Ảnh xe phải là file hình ảnh.',
        ];
    }
}
