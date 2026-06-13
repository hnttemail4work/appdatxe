<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\MerchantProfile;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard()
    {
        $merchants = MerchantProfile::query()->with('user')->get();
        $commissionSetting = PlatformSetting::getValue('commission_percentage', ['value' => 10]);
        $orderSummary = Booking::query()
            ->with(['customer', 'schedule.route', 'schedule.vehicle'])
            ->latest()->limit(20)->get();
        $drivers = DriverProfile::query()
            ->with(['user', 'operator'])->latest()->get();
        $customers = User::query()
            ->where('role', 'customer')->latest()->get();
        $operators = User::query()
            ->where('role', 'operator')->latest()->get();

        return view('admin.dashboard', compact(
            'merchants', 'commissionSetting', 'orderSummary',
            'drivers', 'customers', 'operators'
        ));
    }

    public function updateCommission(Request $request)
    {
        $validated = $request->validate([
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        PlatformSetting::setValue('commission_percentage', ['value' => $validated['commission_percentage']], 'finance');

        return redirect()->route('admin.dashboard')->with('success', 'Cập nhật tỷ lệ hoa hồng thành công.');
    }

    public function approveMerchant(MerchantProfile $merchantProfile)
    {
        $merchantProfile->update([
            'kyc_status'   => 'approved',
            'approved_by'  => Auth::id(),
            'approved_at'  => now(),
            'suspended_at' => null,
        ]);
        $merchantProfile->user()->update(['status' => 'active']);

        return redirect()->route('admin.dashboard')->with('success', 'Đã duyệt tài khoản quản lý.');
    }

    public function rejectMerchant(MerchantProfile $merchantProfile)
    {
        $merchantProfile->update(['kyc_status' => 'rejected']);
        $merchantProfile->user()->update(['status' => 'inactive']);

        return redirect()->route('admin.dashboard')->with('success', 'Đã từ chối tài khoản quản lý.');
    }

    public function suspendMerchant(MerchantProfile $merchantProfile)
    {
        $merchantProfile->update(['kyc_status' => 'suspended', 'suspended_at' => now()]);
        $merchantProfile->user()->update(['status' => 'suspended']);

        return redirect()->route('admin.dashboard')->with('success', 'Đã tạm ngưng tài khoản quản lý.');
    }

    public function updateUserStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', 'Đã cập nhật trạng thái tài khoản.');
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'email'     => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => ['nullable', 'string', 'min:8'],
            'address'   => ['nullable', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:20'],
        ]);

        $data = [
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'phone'     => $validated['phone'] ?? null,
            'address'   => $validated['address'] ?? null,
            'id_number' => $validated['id_number'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return redirect()->back()->with('success', 'Đã cập nhật thông tin tài khoản.');
    }

    public function bookings()
    {
        $bookings = Booking::query()
            ->with(['customer', 'schedule.route', 'schedule.vehicle'])
            ->latest()->paginate(30);

        $stats = [
            'total'     => Booking::query()->count(),
            'paid'      => Booking::query()->where('payment_status', 'paid')->count(),
            'unpaid'    => Booking::query()->where('payment_status', 'unpaid')->count(),
            'revenue'   => Booking::query()->where('payment_status', 'paid')->sum('total_price'),
            'cancelled' => Booking::query()->where('booking_status', 'cancelled')->count(),
        ];

        return view('admin.bookings', compact('bookings', 'stats'));
    }

    /** Admin: manage a single driver's profile */
    public function updateDriverProfile(Request $request, DriverProfile $driverProfile)
    {
        $validated = $request->validate([
            'license_number'   => ['nullable', 'string', 'max:50'],
            'license_class'    => ['nullable', Rule::in(['B1','B2','C','D','E','F'])],
            'license_expiry'   => ['nullable', 'date'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:50'],
            'status'           => ['nullable', Rule::in(['active','inactive','suspended'])],
            'availability_status' => ['nullable', Rule::in(['available','on_trip','off_duty'])],
            'notes'            => ['nullable', 'string'],
            'operator_id'      => ['nullable', 'exists:users,id'],
        ]);

        $driverProfile->update(array_filter($validated, fn($v) => $v !== null));

        return redirect()->back()->with('success', 'Đã cập nhật hồ sơ tài xế.');
    }
}
