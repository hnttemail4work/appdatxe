@php
    $compact = $compact ?? false;
    $rejectFormId = 'customer-reject-form-' . $user->id;
    $frontUrl = $user->idCardPhotoUrl('photo_id_card');
@endphp

@if($user->isCustomerApprovalPending() && auth()->user()->role === 'admin')

<div class="driver-approval-actions {{ $compact ? 'driver-approval-actions--compact' : '' }}">
    <form method="POST" action="{{ route('admin.users.activate', $user) }}" class="admin-approve-form">
        @csrf
        @include('partials.admin-identity-scan', [
            'prefix' => 'customer-approve-'.$user->id,
            'user' => $user,
            'frontUrl' => $frontUrl,
        ])
        <div class="d-flex flex-wrap gap-2 mt-2">
            <button class="btn btn-sm btn-primary">Duyệt</button>
            <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    data-driver-reject-toggle
                    data-target="{{ $rejectFormId }}"
                    aria-expanded="false">
                Từ chối
            </button>
        </div>
    </form>

    <form method="POST"
          action="{{ route('admin.users.reject', $user) }}"
          id="{{ $rejectFormId }}"
          class="driver-reject-form d-none {{ $compact ? '' : 'mt-3' }}"
          data-driver-reject-form>
        @csrf
        <label class="form-label small mb-1" for="rejection_reason_{{ $user->id }}">Lý do từ chối <span class="text-danger">*</span></label>
        <textarea name="rejection_reason"
                  id="rejection_reason_{{ $user->id }}"
                  class="form-control form-control-sm @error('rejection_reason') is-invalid @enderror"
                  rows="{{ $compact ? 2 : 3 }}"
                  minlength="5"
                  maxlength="1000"
                  required
                  placeholder="Ví dụ: Ảnh CCCD không rõ, thông tin không khớp…">{{ old('rejection_reason') }}</textarea>
        @error('rejection_reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="d-flex flex-wrap gap-2 mt-2">
            <button type="submit" class="btn btn-sm btn-danger">Xác nhận từ chối</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-driver-reject-cancel data-target="{{ $rejectFormId }}">Hủy</button>
        </div>
    </form>
</div>

@endif
