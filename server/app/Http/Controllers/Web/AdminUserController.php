<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkDeleteRejectedRegistrationsRequest;
use App\Models\CustomerProfileChangeRequest;
use App\Models\User;
use App\Services\CustomerDocumentService;
use App\Services\CustomerProfileChangeService;
use App\Services\PendingApprovalExpiryService;
use App\Support\AdminIdentityApproval;
use App\Support\DriverDefaultPassword;
use App\Support\PageList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Quản lý tài khoản khách hàng (duyệt đăng ký / chỉnh hồ sơ / duyệt cập nhật). */
class AdminUserController extends Controller
{
    public function __construct(
        private readonly CustomerProfileChangeService $profileChanges,
        private readonly CustomerDocumentService $documents,
        private readonly PendingApprovalExpiryService $pendingExpiry,
    ) {
    }

    public function index(Request $request)
    {
        $this->pendingExpiry->expireStaleCustomers();

        $status = (string) $request->query('status', 'all');
        $q = trim((string) $request->query('q', ''));

        $query = User::query()
            ->where('role', 'customer')
            ->with(['customerProfileChangeRequests' => function ($builder): void {
                $builder->where('status', CustomerProfileChangeRequest::STATUS_PENDING)->latest('id');
            }])
            ->orderByDesc('id');

        if ($status === 'pending') {
            $query->where('approval_status', User::APPROVAL_PENDING);
        } elseif ($status === 'rejected') {
            $query->where('approval_status', User::APPROVAL_REJECTED);
        } elseif ($status === 'active') {
            $query->where('approval_status', User::APPROVAL_APPROVED)
                ->where('status', 'active');
        } elseif ($status === 'suspended') {
            $query->where('approval_status', User::APPROVAL_APPROVED)
                ->whereIn('status', ['suspended', 'inactive']);
        } else {
            // Danh sách chính: chỉ khách đã duyệt (chờ duyệt / từ chối ở tab riêng).
            $query->where('approval_status', User::APPROVAL_APPROVED);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', '%' . $q . '%')
                    ->orWhere('phone', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            });
        }

        $users = $query->paginate(PageList::PER_PAGE)->withQueryString();

        // Tab Chờ duyệt: luôn hiện tổng số đang chờ (không phụ thuộc đã xem hay chưa).
        $pendingCount = (int) User::query()
            ->where('role', 'customer')
            ->where('approval_status', User::APPROVAL_PENDING)
            ->count();

        return view('admin.users.index', [
            'users'        => $users,
            'status'       => $status,
            'q'            => $q,
            'pendingCount' => $pendingCount,
        ]);
    }

    public function edit(User $user)
    {
        $this->assertManagedCustomer($user);

        $pendingChange = $this->profileChanges->pendingFor($user);

        return view('admin.users.edit', [
            'user'          => $user,
            'pendingChange' => $pendingChange,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->assertManagedCustomer($user);

        if ($user->isCustomerApprovalPending() || $user->approval_status === User::APPROVAL_REJECTED) {
            return back()->withErrors(['user' => 'Hồ sơ đang chờ duyệt hoặc đã bị từ chối — chưa thể chỉnh sửa.']);
        }

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'gender'        => ['nullable', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'address'       => ['nullable', 'string', 'max:500'],
            'id_number'     => ['nullable', 'string', 'max:20'],
        ]);

        $email = trim((string) ($validated['email'] ?? ''));
        $user->fill([
            'name'          => trim((string) $validated['name']),
            'email'         => $email !== '' ? $email : null,
            'gender'        => $validated['gender'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'address'       => filled($validated['address'] ?? null) ? trim((string) $validated['address']) : null,
            'id_number'     => filled($validated['id_number'] ?? null) ? trim((string) $validated['id_number']) : null,
        ])->save();

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'Đã cập nhật thông tin khách hàng.');
    }

    public function uploadPhotos(Request $request, User $user)
    {
        $this->assertManagedCustomer($user);

        if ($user->isCustomerApprovalPending() || $user->approval_status === User::APPROVAL_REJECTED) {
            return back()->withErrors(['user' => 'Hồ sơ đang chờ duyệt hoặc đã bị từ chối — chưa thể cập nhật ảnh.']);
        }

        try {
            $updates = $this->documents->storeIdCardFiles($user, $request, required: false);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if ($updates === []) {
            return back()->withErrors(['photos' => 'Chọn ít nhất một ảnh CCCD để lưu.']);
        }

        $user->forceFill($updates)->save();

        return redirect()
            ->route('admin.users.edit', ['user' => $user, 'tab' => 'photos'])
            ->with('success', 'Đã cập nhật ảnh CCCD.');
    }

    public function deactivate(User $user)
    {
        $this->assertManagedCustomer($user);

        if ($user->approval_status !== User::APPROVAL_APPROVED) {
            return back()->withErrors(['user' => 'Chỉ tạm ngưng khách đã được duyệt.']);
        }

        if (! $user->isAccountRunning()) {
            return back()->with('success', 'Tài khoản đang tạm ngưng.');
        }

        $user->update(['status' => 'suspended']);

        return back()->with('success', 'Đã tạm ngưng tài khoản khách.');
    }

    public function activate(Request $request, User $user)
    {
        $this->assertManagedCustomer($user);

        // Duyệt đăng ký lần đầu: dùng thông tin user đã cập nhật + ảnh admin cắt/xoay.
        if ($user->approval_status === User::APPROVAL_PENDING) {
            AdminIdentityApproval::mergeUserIdentityIntoRequest($request, $user);
            $validated = $request->validate(
                AdminIdentityApproval::rules(),
                AdminIdentityApproval::messages(),
            );
            $photoUpdates = AdminIdentityApproval::storeAdjustedIdCardPhotos(
                $request,
                $user,
                'customers/'.$user->id,
            );
            $user->update(array_merge(
                AdminIdentityApproval::userAttributes($validated),
                $photoUpdates,
                [
                    'status'              => 'active',
                    'approval_status'     => User::APPROVAL_APPROVED,
                    'rejection_reason'    => null,
                    'rejection_reason_at' => null,
                ],
            ));

            $fresh = $user->fresh();
            app(\App\Services\RegistrationService::class)->issueRegisterOtpAfterApproval($fresh);
            app(\App\Services\UserInboxService::class)->notifyRegistrationApproved($fresh);

            return redirect()
                ->route('admin.authCodes')
                ->with('success', \App\Support\AuthOtp::approvedOtpReady());
        }

        if ($user->approval_status !== User::APPROVAL_APPROVED) {
            return back()->withErrors(['user' => 'Chỉ mở lại khách đã được duyệt.']);
        }

        $user->update([
            'status'          => 'active',
            'approval_status' => User::APPROVAL_APPROVED,
        ]);

        return back()->with('success', 'Đã mở lại tài khoản khách.');
    }

    public function reject(Request $request, User $user)
    {
        $this->assertManagedCustomer($user);

        if (! $user->isCustomerApprovalPending()) {
            return back()->withErrors(['user' => 'Khách này không còn ở trạng thái chờ duyệt.']);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $user->update([
            'status'              => 'inactive',
            'approval_status'     => User::APPROVAL_REJECTED,
            'rejection_reason'    => trim($validated['rejection_reason']),
            'rejection_reason_at' => now(),
        ]);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'Đã từ chối hồ sơ đăng ký khách.');
    }

    public function clearRejectionNote(User $user)
    {
        $this->assertManagedCustomer($user);

        if (! $user->hasRejectionNote()) {
            return back()->withErrors(['user' => 'Không có ghi chú từ chối để xóa.']);
        }

        $user->update([
            'rejection_reason'    => null,
            'rejection_reason_at' => null,
        ]);

        return back()->with('success', 'Đã xóa ghi chú từ chối.');
    }

    public function approveChange(CustomerProfileChangeRequest $change)
    {
        $this->assertManagedCustomer($change->user);

        $this->profileChanges->approve($change, Auth::user());

        return redirect()
            ->route('admin.users.edit', $change->user_id)
            ->with('success', 'Đã duyệt cập nhật thông tin khách.');
    }

    public function rejectChange(Request $request, CustomerProfileChangeRequest $change)
    {
        $this->assertManagedCustomer($change->user);

        $note = trim((string) $request->input('admin_note', ''));
        $this->profileChanges->reject($change, Auth::user(), $note !== '' ? $note : null);

        return redirect()
            ->route('admin.users.edit', $change->user_id)
            ->with('success', 'Đã từ chối yêu cầu cập nhật thông tin.');
    }

    public function resetPassword(User $user)
    {
        $this->assertManagedCustomer($user);

        $plain = DriverDefaultPassword::resetToRandom($user);

        return back()
            ->with('success', 'Đã đặt lại PIN cho khách hàng.')
            ->with('customer_password_reset', [
                'password' => $plain,
                'name'     => $user->preferredDisplayName(),
                'phone'    => $user->phone,
            ]);
    }

    /** Xóa nhiều hồ sơ khách đã từ chối (kể cả hết hạn chờ duyệt). */
    public function bulkDestroy(BulkDeleteRejectedRegistrationsRequest $request)
    {
        if (Auth::user()?->role !== 'admin') {
            abort(403);
        }

        $ids = array_map('intval', $request->validated('ids'));
        $deleted = 0;

        User::query()
            ->where('role', 'customer')
            ->whereIn('id', $ids)
            ->where('approval_status', User::APPROVAL_REJECTED)
            ->orderBy('id')
            ->each(function (User $user) use (&$deleted): void {
                if ($this->pendingExpiry->deleteCustomerRegistration($user)) {
                    $deleted++;
                }
            });

        if ($deleted < 1) {
            return back()->withErrors(['ids' => 'Không có hồ sơ từ chối hợp lệ để xóa.']);
        }

        return redirect()
            ->route('admin.users', ['status' => 'rejected'])
            ->with('success', "Đã xóa {$deleted} hồ sơ khách bị từ chối.");
    }

    private function assertManagedCustomer(?User $user): void
    {
        if (Auth::user()?->role !== 'admin' || ! $user || ! $user->isCustomer()) {
            abort(403);
        }

        if ((int) $user->id === (int) Auth::id()) {
            abort(403, 'Không thể tự vô hiệu hóa tài khoản đang đăng nhập.');
        }
    }
}
