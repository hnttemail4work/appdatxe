<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Models\User;
use App\Services\BookingWorkflowService;
use App\Services\DriverPhotoService;
use App\Services\DriverTripRequestService;
use App\Services\ScheduleLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class DriverController extends Controller
{
    public function __construct(
        private readonly DriverPhotoService $photoService,
        private readonly BookingWorkflowService $workflow,
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly DriverTripRequestService $driverRequests,
    ) {
    }

    public function myDashboard()
    {
        $this->scheduleLifecycle->sync();
        $this->driverRequests->expireStale();

        $user    = Auth::user();
        $profile = DriverProfile::query()->where('user_id', $user->id)->with('operator')->first();

        $pendingRequests = DriverTripRequest::query()
            ->where('driver_id', $user->id)
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with(['schedule.route', 'schedule.vehicle', 'customer'])
            ->latest()
            ->get();

        $schedules = Schedule::query()
            ->with([
                'route',
                'vehicle',
                'bookings' => function ($q): void {
                    $q->where('booking_status', 'confirmed')
                        ->with('customer')
                        ->latest();
                },
            ])
            ->where('driver_id', $user->id)
            ->where('departure_time', '>=', now()->subHours(2))
            ->orderBy('departure_time')
            ->get();

        return view('driver.dashboard', compact('user', 'profile', 'schedules', 'pendingRequests'));
    }

    public function acceptTripRequest(Request $request, DriverTripRequest $driverTripRequest)
    {
        try {
            $this->driverRequests->accept($driverTripRequest, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['driver_request' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard')->with('success', 'Đã nhận chuyến. Thông tin đã đồng bộ tới khách và quản lý.');
    }

    public function rejectTripRequest(Request $request, DriverTripRequest $driverTripRequest)
    {
        try {
            $this->driverRequests->reject($driverTripRequest, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['driver_request' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard')->with('success', 'Đã từ chối yêu cầu nhận chuyến.');
    }

    public function myProfile()
    {
        $user    = Auth::user();
        $profile = DriverProfile::query()->where('user_id', $user->id)->with('operator')->first();

        return view('driver.profile', compact('user', 'profile'));
    }

    public function updateAvailability(Request $request)
    {
        $validated = $request->validate([
            'availability_status' => ['required', Rule::in(['available', 'on_trip', 'off_duty'])],
        ]);

        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();
        $profile->update($validated);

        return redirect()->route('driver.dashboard')->with('success', 'Đã cập nhật trạng thái hoạt động.');
    }

    public function updateMyProfile(Request $request)
    {
        $user = Auth::user();
        $profile = DriverProfile::query()->where('user_id', $user->id)->firstOrFail();

        $validated = $request->validate([
            'phone'              => ['required', 'string', 'max:30'],
            'address'            => ['nullable', 'string', 'max:255'],
            'id_number'          => ['nullable', 'string', 'max:20'],
            'license_number'     => ['nullable', 'string', 'max:50', Rule::unique('driver_profiles', 'license_number')->ignore($profile->id)],
            'license_class'      => ['nullable', Rule::in(['B1', 'B2', 'C', 'D', 'E', 'F'])],
            'license_expiry'     => ['nullable', 'date'],
            'experience_years'    => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes'              => ['nullable', 'string'],
            'bank_name'          => ['nullable', 'string', 'max:100'],
            'bank_account'       => ['nullable', 'string', 'max:50'],
        ]);

        $user->update([
            'phone'     => $validated['phone'],
            'address'   => $validated['address'] ?? null,
            'id_number' => $validated['id_number'] ?? null,
        ]);

        $profile->update([
            'license_number'   => $validated['license_number'] ?? $profile->license_number,
            'license_class'    => $validated['license_class'] ?? $profile->license_class,
            'license_expiry'   => $validated['license_expiry'] ?? $profile->license_expiry,
            'experience_years' => $validated['experience_years'] ?? $profile->experience_years ?? 0,
            'notes'            => $validated['notes'] ?? null,
            'bank_name'        => $validated['bank_name'] ?? null,
            'bank_account'     => $validated['bank_account'] ?? null,
        ]);

        return redirect()->route('driver.profile')->with('success', 'Đã cập nhật hồ sơ tài xế.');
    }

    public function uploadMyPhotos(Request $request)
    {
        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();

        try {
            $this->photoService->syncPhotos($profile, $request);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['photos' => $e->getMessage()])->withInput();
        }

        return redirect()->route('driver.profile')->with('success', 'Đã upload ảnh hồ sơ thành công.');
    }

    /** Tài xế báo hoàn thành chuyến — chờ khách xác nhận. */
    public function completeTrip(Request $request, Booking $booking)
    {
        try {
            $this->workflow->driverCompleteTrip($booking, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard')
            ->with('success', 'Đã báo hoàn thành chuyến. Chờ khách hàng xác nhận.');
    }

    public function index()
    {
        $query = DriverProfile::query()->with(['user', 'operator'])->latest();

        if (Auth::user()->role !== 'admin') {
            $query->where('operator_id', Auth::id());
        }

        $drivers = $query->get();

        return view('operator.drivers.index', compact('drivers'));
    }

    public function create()
    {
        $operators = $this->operatorsForForm();

        return view('operator.drivers.create', compact('operators'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'            => ['required', 'string', 'max:30'],
            'password'         => ['required', 'string', 'min:8'],
            'address'          => ['nullable', 'string', 'max:255'],
            'id_number'        => ['nullable', 'string', 'max:20'],
            'license_number'   => ['nullable', 'string', 'max:50', 'unique:driver_profiles,license_number'],
            'license_class'    => ['nullable', Rule::in(['B1', 'B2', 'C', 'D', 'E', 'F'])],
            'license_expiry'   => ['nullable', 'date'],
            'experience_years'   => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes'              => ['nullable', 'string'],
            'bank_name'          => ['nullable', 'string', 'max:100'],
            'bank_account'       => ['nullable', 'string', 'max:50'],
            'operator_id'      => [Rule::requiredIf(Auth::user()->role === 'admin'), 'nullable', 'exists:users,id'],
        ]);

        $operatorId = Auth::user()->role === 'admin'
            ? (int) $validated['operator_id']
            : Auth::id();

        $profile = null;

        DB::transaction(function () use ($validated, $operatorId, &$profile): void {
            $user = User::query()->create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'phone'     => $validated['phone'],
                'address'   => $validated['address'] ?? null,
                'id_number' => $validated['id_number'] ?? null,
                'role'      => 'driver',
                'status'    => 'active',
            ]);

            $profile = DriverProfile::query()->create([
                'user_id'             => $user->id,
                'operator_id'         => $operatorId,
                'license_number'      => $validated['license_number'] ?? 'Chưa cập nhật',
                'license_class'       => $validated['license_class'] ?? 'B2',
                'license_expiry'      => $validated['license_expiry'] ?? now()->addYear(),
                'experience_years'    => $validated['experience_years'] ?? 0,
                'status'              => 'active',
                'availability_status' => 'off_duty',
                'notes'               => $validated['notes'] ?? null,
                'bank_name'           => $validated['bank_name'] ?? null,
                'bank_account'        => $validated['bank_account'] ?? null,
            ]);
        });

        return redirect()
            ->route('operator.drivers.edit', $profile)
            ->with('success', 'Đã tạo tài khoản tài xế. Tài xế có thể đăng nhập và tự bổ sung hồ sơ.');
    }

    public function edit(DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        $driverProfile->load(['user', 'operator']);
        $operators = $this->operatorsForForm();

        return view('operator.drivers.edit', [
            'driver'    => $driverProfile,
            'operators' => $operators,
        ]);
    }

    public function update(Request $request, DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        $profileValidated = $request->validate([
            'status'              => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'availability_status' => ['nullable', Rule::in(['available', 'on_trip', 'off_duty'])],
            'license_number'      => ['nullable', 'string', 'max:50', Rule::unique('driver_profiles', 'license_number')->ignore($driverProfile->id)],
            'license_class'       => ['nullable', Rule::in(['B1', 'B2', 'C', 'D', 'E', 'F'])],
            'license_expiry'      => ['nullable', 'date'],
            'experience_years'    => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes'               => ['nullable', 'string'],
            'bank_name'           => ['nullable', 'string', 'max:100'],
            'bank_account'        => ['nullable', 'string', 'max:50'],
            'operator_id'         => ['nullable', 'exists:users,id'],
        ]);

        $userValidated = $request->validate([
            'name'      => ['nullable', 'string', 'max:255'],
            'email'     => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($driverProfile->user_id)],
            'phone'     => ['nullable', 'string', 'max:30'],
            'address'   => ['nullable', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:20'],
            'password'  => ['nullable', 'string', 'min:8'],
        ]);

        $profileData = array_filter($profileValidated, fn ($v) => $v !== null && $v !== '');
        if ($profileData) {
            if (Auth::user()->role !== 'admin') {
                unset($profileData['operator_id']);
            }
            $driverProfile->update($profileData);
        }

        $userData = array_filter($userValidated, fn ($v) => $v !== null && $v !== '');
        if (isset($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        } else {
            unset($userData['password']);
        }
        if ($userData) {
            $driverProfile->user->update($userData);
        }

        return redirect()
            ->route('operator.drivers.edit', $driverProfile)
            ->with('success', 'Đã cập nhật thông tin tài xế.');
    }

    public function destroy(DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        $driverProfile->update(['status' => 'inactive']);
        $driverProfile->user->update(['status' => 'inactive']);

        return redirect()->route('operator.drivers')->with('success', 'Đã vô hiệu hoá tài xế.');
    }

    public function uploadPhotos(Request $request, DriverProfile $driverProfile)
    {
        if (! $this->canManageDriver($driverProfile)) {
            abort(403);
        }

        try {
            $this->photoService->syncPhotos($driverProfile, $request);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['photos' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('operator.drivers.edit', $driverProfile)
            ->with('success', 'Đã cập nhật ảnh tài xế.');
    }

    private function operatorsForForm()
    {
        if (Auth::user()->role !== 'admin') {
            return collect();
        }

        return User::query()
            ->where('role', 'operator')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function canManageDriver(DriverProfile $driverProfile): bool
    {
        if (Auth::user()->role === 'admin') {
            return true;
        }

        return Auth::user()->role === 'operator' && $driverProfile->operator_id === Auth::id();
    }
}
