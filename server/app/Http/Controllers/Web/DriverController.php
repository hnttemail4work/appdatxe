<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    /** Driver's own dashboard — sees their profile + upcoming schedules */
    public function myDashboard()
    {
        $user    = Auth::user();
        $profile = DriverProfile::query()->where('user_id', $user->id)->with('operator')->first();

        $schedules = Schedule::query()
            ->with(['route', 'vehicle'])
            ->where('driver_id', $user->id)
            ->where('departure_time', '>=', now()->subHours(2))
            ->orderBy('departure_time')
            ->get();

        return view('driver.dashboard', compact('user', 'profile', 'schedules'));
    }

    /** Driver updates their own availability status */
    public function updateAvailability(Request $request)
    {
        $validated = $request->validate([
            'availability_status' => ['required', Rule::in(['available', 'on_trip', 'off_duty'])],
        ]);

        $profile = DriverProfile::query()->where('user_id', Auth::id())->firstOrFail();
        $profile->update($validated);

        return redirect()->route('driver.dashboard')->with('success', 'Đã cập nhật trạng thái hoạt động.');
    }

    /** Operator: list all drivers under this operator (admin sees all) */
    public function index()
    {
        $query = DriverProfile::query()->with('user')->latest();

        if (Auth::user()->role !== 'admin') {
            $query->where('operator_id', Auth::id());
        }

        $drivers = $query->get();

        return view('operator.drivers', compact('drivers'));
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
            'license_number'   => ['required', 'string', 'max:50', 'unique:driver_profiles,license_number'],
            'license_class'    => ['required', Rule::in(['B1', 'B2', 'C', 'D', 'E', 'F'])],
            'license_expiry'   => ['required', 'date', 'after:today'],
            'experience_years' => ['required', 'integer', 'min:0', 'max:50'],
            'notes'            => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated) {
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

            DriverProfile::query()->create([
                'user_id'          => $user->id,
                'operator_id'      => Auth::id(),
                'license_number'   => $validated['license_number'],
                'license_class'    => $validated['license_class'],
                'license_expiry'   => $validated['license_expiry'],
                'experience_years' => $validated['experience_years'],
                'status'           => 'active',
                'notes'            => $validated['notes'] ?? null,
            ]);
        });

        return redirect()->route('operator.drivers')->with('success', 'Đã thêm tài xế thành công.');
    }

    public function update(Request $request, DriverProfile $driverProfile)
    {
        if (Auth::user()->role !== 'admin' && $driverProfile->operator_id !== Auth::id()) {
            abort(403);
        }

        $profileValidated = $request->validate([
            'status'              => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'availability_status' => ['nullable', Rule::in(['available', 'on_trip', 'off_duty'])],
            'license_number'      => ['nullable', 'string', 'max:50'],
            'license_class'       => ['nullable', Rule::in(['B1', 'B2', 'C', 'D', 'E', 'F'])],
            'license_expiry'      => ['nullable', 'date'],
            'experience_years'    => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes'               => ['nullable', 'string'],
        ]);

        // Also update user fields if present
        $userValidated = $request->validate([
            'name'     => ['nullable', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'address'  => ['nullable', 'string', 'max:255'],
            'id_number'=> ['nullable', 'string', 'max:20'],
        ]);

        $profileData = array_filter($profileValidated, fn($v) => $v !== null && $v !== '');
        if ($profileData) {
            $driverProfile->update($profileData);
        }

        $userData = array_filter($userValidated, fn($v) => $v !== null);
        if ($userData) {
            $driverProfile->user->update($userData);
        }

        return redirect()->route('operator.drivers')->with('success', 'Đã cập nhật thông tin tài xế.');
    }

    public function destroy(DriverProfile $driverProfile)
    {
        if (Auth::user()->role !== 'admin' && $driverProfile->operator_id !== Auth::id()) {
            abort(403);
        }

        $driverProfile->update(['status' => 'inactive']);

        return redirect()->route('operator.drivers')->with('success', 'Đã vô hiệu hoá tài xế.');
    }

    /** Operator: upload photos for a driver */
    public function uploadPhotos(Request $request, DriverProfile $driverProfile)
    {
        if (Auth::user()->role !== 'admin' && $driverProfile->operator_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'photo_portrait'      => ['nullable', 'image', 'max:2048'],
            'photo_id_card'       => ['nullable', 'image', 'max:2048'],
            'photo_id_card_back'  => ['nullable', 'image', 'max:2048'],
            'photo_vehicles.*'    => ['nullable', 'image', 'max:2048'],
            'delete_vehicle_idx'  => ['nullable', 'integer'],
        ]);

        $updates = [];
        $dir = 'drivers/' . $driverProfile->id;

        foreach (['photo_portrait', 'photo_id_card', 'photo_id_card_back'] as $field) {
            if ($request->hasFile($field)) {
                if ($driverProfile->{$field}) {
                    Storage::disk('public')->delete($driverProfile->{$field});
                }
                $updates[$field] = $request->file($field)->store($dir, 'public');
            }
        }

        // Handle vehicle photos array — append new ones, optionally delete one
        $existing = $driverProfile->photo_vehicles ?? [];

        if ($request->filled('delete_vehicle_idx')) {
            $idx = (int) $request->input('delete_vehicle_idx');
            if (isset($existing[$idx])) {
                Storage::disk('public')->delete($existing[$idx]);
                array_splice($existing, $idx, 1);
            }
        }

        if ($request->hasFile('photo_vehicles')) {
            foreach ($request->file('photo_vehicles') as $file) {
                $existing[] = $file->store($dir, 'public');
            }
        }

        if ($request->hasFile('photo_vehicles') || $request->filled('delete_vehicle_idx')) {
            $updates['photo_vehicles'] = array_values($existing);
        }

        if ($updates) {
            $driverProfile->update($updates);
        }

        return redirect()->route('operator.drivers')->with('success', 'Đã cập nhật ảnh tài xế.');
    }
}
