<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\DriverProfileChangeRequest;
use App\Models\User;
use App\Support\DriverVehicleOptions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DriverProfileChangeService
{
    private const PHOTO_FIELDS = [
        'photo_portrait',
        'photo_id_card',
        'photo_id_card_back',
        'photo_license_front',
        'photo_license_back',
    ];

    private const PAYLOAD_FIELDS = [
        'vehicle_license_plate',
        'vehicle_type',
        'bank_name',
        'bank_account',
    ];

    public function pendingFor(DriverProfile $profile): ?DriverProfileChangeRequest
    {
        return DriverProfileChangeRequest::query()
            ->where('driver_profile_id', $profile->id)
            ->where('status', DriverProfileChangeRequest::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    public function submit(DriverProfile $profile, Request $request): DriverProfileChangeRequest
    {
        $validated = Validator::make($request->all(), $this->submitRules())->validate();

        $payloadPreview = $this->changedPayload($profile, $validated);
        $hasPhotos = $this->requestHasPhotos($request);

        if ($payloadPreview === [] && ! $hasPhotos) {
            throw ValidationException::withMessages([
                'documents' => 'Chưa có thay đổi.',
            ]);
        }

        return DB::transaction(function () use ($profile, $request, $validated) {
            $existing = $this->pendingFor($profile);
            $previousPhotos = $existing?->photos ?? [];
            $incomingPhotos = $this->storePendingPhotos($profile, $request);
            $photos = $this->mergePendingPhotos($previousPhotos, $incomingPhotos);
            $this->deleteOrphanPendingPhotos($previousPhotos, $photos);

            if ($existing) {
                $existing->delete();
            }

            $payload = $this->changedPayload($profile, $validated);

            $change = DriverProfileChangeRequest::query()->create([
                'driver_profile_id' => $profile->id,
                'status'            => DriverProfileChangeRequest::STATUS_PENDING,
                'payload'           => $payload ?: null,
                'photos'            => $photos ?: null,
            ]);

            app(AdminOperatorAlertService::class)->recordDriverProfileChangePending($change);

            return $change;
        });
    }

    public function approve(DriverProfileChangeRequest $change, User $admin): void
    {
        if (! $change->isPending()) {
            return;
        }

        $profile = $change->profile()->firstOrFail();

        DB::transaction(function () use ($change, $profile, $admin): void {
            $payload = $change->payload ?? [];
            if ($payload !== []) {
                $profile->fill(collect($payload)->only(self::PAYLOAD_FIELDS)->all());
            }

            $photos = $change->photos ?? [];
            $dir = 'drivers/' . $profile->id;
            Storage::disk('public')->makeDirectory($dir);
            $updates = [];

            foreach (self::PHOTO_FIELDS as $field) {
                $path = $photos[$field] ?? null;
                if (! is_string($path) || $path === '') {
                    continue;
                }
                if ($profile->{$field}) {
                    Storage::disk('public')->delete($profile->{$field});
                }
                $updates[$field] = $this->promotePendingPath($path, $dir, $field);
            }

            if (! empty($photos['photo_vehicles']) && is_array($photos['photo_vehicles'])) {
                $newVehicles = [];
                foreach ($photos['photo_vehicles'] as $idx => $path) {
                    if (! is_string($path) || $path === '') {
                        continue;
                    }
                    $newVehicles[] = $this->promotePendingPath($path, $dir, 'vehicle_' . $idx);
                }
                if ($newVehicles !== []) {
                    foreach ($profile->photo_vehicles ?? [] as $old) {
                        if (is_string($old) && $old !== '') {
                            Storage::disk('public')->delete($old);
                        }
                    }
                    $updates['photo_vehicles'] = $newVehicles;
                    $updates['catalog_vehicle_photo_index'] = 0;
                }
            }

            if ($updates !== []) {
                $profile->fill($updates);
            }

            $profile->save();

            $change->update([
                'status'      => DriverProfileChangeRequest::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'photos'      => null,
            ]);

            app(DriverCatalogService::class)->syncCatalogForDriver($profile->fresh());
            app(DriverInboxService::class)->notifyProfileChangeApproved($profile->fresh());
        });
    }

    public function reject(DriverProfileChangeRequest $change, User $admin, ?string $note = null): void
    {
        if (! $change->isPending()) {
            return;
        }

        DB::transaction(function () use ($change, $admin, $note): void {
            $fieldsLabel = $this->changedFieldsLabel($change);
            $this->deleteStoredPhotos($change->photos ?? []);
            $change->update([
                'status'      => DriverProfileChangeRequest::STATUS_REJECTED,
                'admin_note'  => filled($note) ? trim($note) : null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'photos'      => null,
            ]);

            $profile = $change->profile()->first();
            if ($profile) {
                app(DriverInboxService::class)->notifyProfileChangeRejected($profile, $fieldsLabel);
            }
        });
    }

    public function changedFieldsLabel(DriverProfileChangeRequest $change): string
    {
        $labels = [
            'vehicle_license_plate' => 'Biển số',
            'vehicle_type'          => 'Loại xe',
            'bank_name'             => 'Ngân hàng',
            'bank_account'          => 'Số tài khoản',
            'photo_portrait'        => 'Ảnh chân dung',
            'photo_id_card'         => 'Ảnh CCCD trước',
            'photo_id_card_back'    => 'Ảnh CCCD sau',
            'photo_license_front'   => 'Ảnh GPLX trước',
            'photo_license_back'    => 'Ảnh GPLX sau',
            'photo_vehicles'        => 'Ảnh xe',
        ];

        $names = [];
        foreach ($change->payload ?? [] as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $names[] = $labels[$key] ?? $key;
        }

        foreach (self::PHOTO_FIELDS as $field) {
            $path = $change->photos[$field] ?? null;
            if (is_string($path) && $path !== '') {
                $names[] = $labels[$field] ?? $field;
            }
        }

        $vehicles = $change->photos['photo_vehicles'] ?? null;
        if (is_array($vehicles) && $vehicles !== []) {
            $names[] = $labels['photo_vehicles'];
        }

        return implode(', ', array_values(array_unique($names)));
    }

    /** @return array<string, mixed> */
    private function submitRules(): array
    {
        return [
            'vehicle_license_plate' => ['nullable', 'string', 'max:30'],
            'vehicle_type'          => ['nullable', 'string', Rule::in(DriverVehicleOptions::allowedKeys())],
            'bank_name'             => ['nullable', 'string', 'max:120'],
            'bank_account'          => ['nullable', 'string', 'max:60'],
            'photo_portrait'        => ['nullable', 'image', 'max:5120'],
            'photo_id_card'         => ['nullable', 'image', 'max:5120'],
            'photo_id_card_back'    => ['nullable', 'image', 'max:5120'],
            'photo_license_front'   => ['nullable', 'image', 'max:5120'],
            'photo_license_back'    => ['nullable', 'image', 'max:5120'],
            'photo_vehicles'        => ['nullable', 'array', 'max:6'],
            'photo_vehicles.*'      => ['image', 'max:5120'],
        ];
    }

    /**
     * Chỉ đưa vào payload các field thực sự khác hồ sơ hiện tại.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function changedPayload(DriverProfile $profile, array $validated): array
    {
        $payload = [];

        foreach (self::PAYLOAD_FIELDS as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $value = $validated[$key];
            if ($value === null || $value === '') {
                continue;
            }

            $current = $profile->{$key};
            if ((string) ($current ?? '') === (string) $value) {
                continue;
            }

            $payload[$key] = $value;
        }

        return $payload;
    }

    /**
     * Ảnh mới ghi đè từng field; field không upload lại giữ ảnh chờ duyệt cũ.
     *
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergePendingPhotos(array $previous, array $incoming): array
    {
        $merged = $previous;

        foreach (self::PHOTO_FIELDS as $field) {
            if (isset($incoming[$field])) {
                $merged[$field] = $incoming[$field];
            }
        }

        if (isset($incoming['photo_vehicles'])) {
            $merged['photo_vehicles'] = $incoming['photo_vehicles'];
        }

        return array_filter(
            $merged,
            fn ($value) => $value !== null && $value !== '' && $value !== []
        );
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $kept
     */
    private function deleteOrphanPendingPhotos(array $previous, array $kept): void
    {
        $keptPaths = collect(self::PHOTO_FIELDS)
            ->map(fn (string $field) => $kept[$field] ?? null)
            ->merge($kept['photo_vehicles'] ?? [])
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values()
            ->all();

        foreach (self::PHOTO_FIELDS as $field) {
            $path = $previous[$field] ?? null;
            if (is_string($path) && $path !== '' && ! in_array($path, $keptPaths, true)) {
                Storage::disk('public')->delete($path);
            }
        }

        foreach ($previous['photo_vehicles'] ?? [] as $path) {
            if (is_string($path) && $path !== '' && ! in_array($path, $keptPaths, true)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function requestHasPhotos(Request $request): bool
    {
        foreach (self::PHOTO_FIELDS as $field) {
            if ($request->hasFile($field)) {
                return true;
            }
        }

        return $request->hasFile('photo_vehicles');
    }

    /** @return array<string, mixed> */
    private function storePendingPhotos(DriverProfile $profile, Request $request): array
    {
        $dir = 'drivers/' . $profile->id . '/pending';
        Storage::disk('public')->makeDirectory($dir);
        $stored = [];

        foreach (self::PHOTO_FIELDS as $field) {
            $file = $request->file($field);
            if ($file instanceof UploadedFile) {
                $stored[$field] = $file->store($dir, 'public');
            }
        }

        $vehicles = $request->file('photo_vehicles');
        if (is_array($vehicles) && $vehicles !== []) {
            $paths = [];
            foreach ($vehicles as $file) {
                if ($file instanceof UploadedFile) {
                    $paths[] = $file->store($dir, 'public');
                }
            }
            if ($paths !== []) {
                $stored['photo_vehicles'] = $paths;
            }
        }

        return $stored;
    }

    private function promotePendingPath(string $path, string $liveDir, string $suffix): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $dest = $liveDir . '/' . $suffix . '_' . uniqid('', true) . '.' . $ext;
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->move($path, $dest);

            return $dest;
        }

        return $path;
    }

    /** @param  array<string, mixed>  $photos */
    private function deleteStoredPhotos(array $photos): void
    {
        foreach (self::PHOTO_FIELDS as $field) {
            $path = $photos[$field] ?? null;
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }

        foreach ($photos['photo_vehicles'] ?? [] as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
