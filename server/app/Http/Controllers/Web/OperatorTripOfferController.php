<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ScheduleTemplate;
use App\Services\TripOfferService;
use App\Support\PageList;
use App\Support\SouthernProvinces;
use App\Support\VehicleCapacityOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class OperatorTripOfferController extends Controller
{
    public function __construct(private readonly TripOfferService $offers)
    {
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
            'departure'             => ['required', 'string', SouthernProvinces::inRule()],
            'destination'           => ['required', 'string', 'different:departure', SouthernProvinces::inRule()],
            'departure_time'        => ['required', 'date_format:H:i'],
            'expected_arrival_time' => ['required', 'date_format:H:i'],
            'seats'                 => ['required', 'integer', Rule::in(VehicleCapacityOptions::STANDARD)],
            'whole_car_one_way'     => ['required', 'integer', 'min:10000'],
            'seat_one_way'          => ['required', 'integer', 'min:10000'],
            'whole_car_round'       => ['required', 'integer', 'min:10000'],
            'seat_round'            => ['required', 'integer', 'min:10000'],
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
            'expected_arrival_time.required' => 'Vui lòng nhập giờ dự kiến đến.',
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
