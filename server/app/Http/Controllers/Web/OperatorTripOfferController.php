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
            $template = $this->offers->deleteOffer($scheduleTemplate, Auth::id());
            $template->loadMissing('route');
        } catch (InvalidArgumentException) {
            abort(403);
        }

        return redirect()
            ->route('operator.tripOffers.create')
            ->with('success', sprintf(
                'Đã xóa tuyến %s → %s khỏi trang đặt vé.',
                $template->route->departure,
                $template->route->destination,
            ));
    }

    private function persist(Request $request, ?ScheduleTemplate $editingFrom = null)
    {
        $hasVehiclePhoto = $this->operatorVehicleHasPhoto($editingFrom);

        $request->validate([
            'departure'             => ['required', 'string', LocationCatalog::inRule()],
            'destination'           => ['required', 'string', 'different:departure', LocationCatalog::inRule()],
            'departure_time'        => ['required', 'date_format:H:i'],
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
                'departure_time'        => old('departure_time', '06:00'),
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
