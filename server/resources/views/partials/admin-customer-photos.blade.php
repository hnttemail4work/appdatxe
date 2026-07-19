@php
    /** @var \App\Models\User $user */
    $viewOnly = (bool) ($viewOnly ?? false);
    $frontUrl = $user->idCardPhotoUrl('photo_id_card');
    $backUrl = $user->idCardPhotoUrl('photo_id_card_back');
@endphp

@if($viewOnly)
    @include('partials.photo-upload-slots', [
        'viewOnly' => true,
        'columnsClass' => 'row g-2 row-cols-2',
        'slots' => [
            ['field' => 'photo_id_card', 'label' => 'CCCD mặt trước', 'url' => $frontUrl, 'ratio' => 'landscape'],
            ['field' => 'photo_id_card_back', 'label' => 'CCCD mặt sau', 'url' => $backUrl, 'ratio' => 'landscape'],
        ],
    ])
@else
<form method="POST" action="{{ route('admin.users.photos', $user) }}" enctype="multipart/form-data" class="console-form">
    @csrf
    <p class="small text-muted mb-3">Bấm «Thay ảnh» để chọn ảnh mới, rồi «Lưu ảnh» — ảnh mới ghi đè trên hồ sơ (không qua hàng chờ duyệt).</p>
    @include('partials.photo-upload-slots', [
        'columnsClass' => 'row g-2 row-cols-2',
        'slots' => [
            ['field' => 'photo_id_card', 'label' => 'CCCD mặt trước', 'url' => $frontUrl, 'ratio' => 'landscape'],
            ['field' => 'photo_id_card_back', 'label' => 'CCCD mặt sau', 'url' => $backUrl, 'ratio' => 'landscape'],
        ],
    ])
    @error('photos')<div class="alert alert-danger py-2 small mt-3 mb-0">{{ $message }}</div>@enderror
    <button type="submit" class="btn btn-primary mt-3">Lưu ảnh</button>
</form>
@endif
