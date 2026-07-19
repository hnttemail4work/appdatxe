@php
    /** @var \App\Models\User $user */
    $readonly = (bool) ($readonly ?? false);
@endphp
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="customer-name">Họ tên</label>
        <input type="text" name="name" id="customer-name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $user->name) }}"
               maxlength="255"
               @disabled($readonly)
               @required(! $readonly)>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="customer-phone">Số điện thoại</label>
        <input type="text" id="customer-phone" class="form-control" value="{{ $user->phone }}" disabled>
        <div class="form-text">SĐT đăng ký — không đổi tại đây.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="customer-email">Email</label>
        <input type="email" name="email" id="customer-email"
               class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $user->emailForForm()) }}"
               maxlength="255"
               @disabled($readonly)>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="customer-id-number">Số CCCD</label>
        <input type="text" name="id_number" id="customer-id-number"
               class="form-control @error('id_number') is-invalid @enderror"
               value="{{ old('id_number', $user->id_number) }}"
               maxlength="20"
               @disabled($readonly)>
        @error('id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="customer-dob">Ngày sinh</label>
        <input type="date" name="date_of_birth" id="customer-dob"
               class="form-control @error('date_of_birth') is-invalid @enderror"
               value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d')) }}"
               @disabled($readonly)>
        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="customer-gender">Giới tính</label>
        <select name="gender" id="customer-gender"
                class="form-select @error('gender') is-invalid @enderror"
                @disabled($readonly)>
            <option value="">—</option>
            <option value="male" @selected(old('gender', $user->gender) === 'male')>Nam</option>
            <option value="female" @selected(old('gender', $user->gender) === 'female')>Nữ</option>
        </select>
        @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="customer-address">Địa chỉ</label>
        <textarea name="address" id="customer-address" rows="2"
                  class="form-control @error('address') is-invalid @enderror"
                  maxlength="500"
                  placeholder="Số nhà, đường, phường/xã, tỉnh/thành…"
                  @disabled($readonly)>{{ old('address', $user->address) }}</textarea>
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
