<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DriverInboxMessage;
use App\Models\DriverProfile;
use App\Services\DriverInboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AdminDriverInboxController extends Controller
{
    public function __construct(
        private readonly DriverInboxService $inbox,
    ) {}

    public function index()
    {
        $drivers = DriverProfile::query()
            ->with('user')
            ->where('approval_status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('role', 'driver'))
            ->orderByDesc('id')
            ->get()
            ->filter(fn (DriverProfile $p) => $p->user)
            ->values();

        return view('admin.driver-inbox', [
            'drivers' => $drivers,
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'category'   => ['required', 'string', Rule::in([
                DriverInboxMessage::CATEGORY_INFO,
                DriverInboxMessage::CATEGORY_NOTICE,
            ])],
            'title'      => ['required', 'string', 'max:160'],
            'body'       => ['required', 'string', 'max:2000'],
            'audience'   => ['required', 'string', Rule::in(['all', 'selected'])],
            'driver_ids' => ['nullable', 'array'],
            'driver_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $userIds = null;
        if ($validated['audience'] === 'selected') {
            $userIds = array_values(array_unique(array_map('intval', $validated['driver_ids'] ?? [])));
            if ($userIds === []) {
                return back()
                    ->withInput()
                    ->withErrors(['driver_ids' => 'Chọn ít nhất một tài xế hoặc gửi tất cả.']);
            }
        }

        $count = $this->inbox->broadcast(
            $validated['category'],
            trim($validated['title']),
            trim($validated['body']),
            $userIds,
            (int) Auth::id(),
        );

        return redirect()
            ->route('admin.driverInbox')
            ->with('success', 'Đã gửi tới ' . number_format($count) . ' tài xế.');
    }
}
