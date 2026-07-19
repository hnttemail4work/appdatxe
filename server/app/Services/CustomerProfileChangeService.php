<?php

namespace App\Services;

use App\Models\CustomerProfileChangeRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerProfileChangeService
{
    private const PAYLOAD_FIELDS = [
        'name',
        'email',
        'gender',
        'date_of_birth',
        'address',
        'id_number',
    ];

    public function __construct(private readonly CustomerDocumentService $documents)
    {
    }

    public function pendingFor(User $user): ?CustomerProfileChangeRequest
    {
        return CustomerProfileChangeRequest::query()
            ->where('user_id', $user->id)
            ->where('status', CustomerProfileChangeRequest::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    public function submit(User $user, Request $request): CustomerProfileChangeRequest
    {
        $validated = Validator::make($request->all(), $this->submitRules($user))->validate();

        $payload = $this->changedPayload($user, $validated);
        $hasPhotos = $this->requestHasPhotos($request);

        // Cập nhật từ app khách: bắt buộc có ảnh CCCD mới (số CCCD không còn nhập trên form).
        if (! $hasPhotos) {
            throw ValidationException::withMessages([
                'photos' => 'Vui lòng chọn ảnh CCCD để gửi duyệt.',
            ]);
        }

        return DB::transaction(function () use ($user, $request, $payload) {
            $existing = $this->pendingFor($user);
            $previousPhotos = $existing?->photos ?? [];
            $incomingPhotos = $this->documents->storePendingIdCardPhotos($user, $request);
            $photos = array_merge($previousPhotos, $incomingPhotos);

            foreach ($incomingPhotos as $field => $path) {
                $old = $previousPhotos[$field] ?? null;
                if (is_string($old) && $old !== '' && $old !== $path) {
                    Storage::disk('public')->delete($old);
                }
            }

            if ($existing) {
                $existing->delete();
            }

            return CustomerProfileChangeRequest::query()->create([
                'user_id' => $user->id,
                'status'  => CustomerProfileChangeRequest::STATUS_PENDING,
                'payload' => $payload ?: null,
                'photos'  => $photos ?: null,
            ]);
        });
    }

    public function approve(CustomerProfileChangeRequest $change, User $admin): void
    {
        if (! $change->isPending()) {
            return;
        }

        $user = $change->user()->firstOrFail();

        DB::transaction(function () use ($change, $user, $admin): void {
            $payload = $change->payload ?? [];
            if ($payload !== []) {
                $user->fill(collect($payload)->only(self::PAYLOAD_FIELDS)->all());
            }

            $photos = $change->photos ?? [];
            $dir = 'customers/' . $user->id;
            Storage::disk('public')->makeDirectory($dir);
            $updates = [];

            foreach (CustomerDocumentService::idCardFields() as $field) {
                $path = $photos[$field] ?? null;
                if (! is_string($path) || $path === '') {
                    continue;
                }
                if (is_string($user->{$field}) && $user->{$field} !== '') {
                    Storage::disk('public')->delete($user->{$field});
                }
                $updates[$field] = $this->promotePendingPath($path, $dir, $field);
            }

            if ($updates !== []) {
                $user->fill($updates);
            }

            $user->save();

            $change->update([
                'status'      => CustomerProfileChangeRequest::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
        });
    }

    public function reject(CustomerProfileChangeRequest $change, User $admin, ?string $note = null): void
    {
        if (! $change->isPending()) {
            return;
        }

        DB::transaction(function () use ($change, $admin, $note): void {
            foreach ($change->photos ?? [] as $path) {
                if (is_string($path) && $path !== '') {
                    Storage::disk('public')->delete($path);
                }
            }

            $change->update([
                'status'      => CustomerProfileChangeRequest::STATUS_REJECTED,
                'admin_note'  => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'photos'      => null,
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function submitRules(User $user): array
    {
        $emailUnique = Rule::unique('users', 'email')->ignore($user->id);

        return [
            'name'            => ['nullable', 'string', 'max:255'],
            'email'           => ['nullable', 'email', 'max:255', $emailUnique],
            'gender'          => ['nullable', 'in:male,female'],
            'date_of_birth'   => ['nullable', 'date', 'before:today'],
            'address'         => ['nullable', 'string', 'max:255'],
            'id_number'       => ['nullable', 'string', 'max:20'],
            'photo_id_card'   => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp'],
            'photo_id_card_back' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp'],
        ];
    }

    /** @return array<string, mixed> */
    private function changedPayload(User $user, array $validated): array
    {
        $payload = [];
        foreach (self::PAYLOAD_FIELDS as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }
            $incoming = $validated[$field];
            if ($incoming === null || $incoming === '') {
                continue;
            }
            $current = $user->{$field};
            if ($field === 'date_of_birth') {
                $currentStr = $current ? $current->format('Y-m-d') : null;
                $incomingStr = is_string($incoming) ? $incoming : null;
                if ($incomingStr && $incomingStr !== $currentStr) {
                    $payload[$field] = $incomingStr;
                }
                continue;
            }
            if ($field === 'email') {
                $incoming = trim((string) $incoming);
                $currentEmail = $user->emailForForm();
                if ($incoming !== '' && $incoming !== $currentEmail) {
                    $payload[$field] = $incoming;
                }
                continue;
            }
            if ((string) $incoming !== (string) ($current ?? '')) {
                $payload[$field] = is_string($incoming) ? trim($incoming) : $incoming;
            }
        }

        return $payload;
    }

    private function requestHasPhotos(Request $request): bool
    {
        foreach (CustomerDocumentService::idCardFields() as $field) {
            if ($request->hasFile($field)) {
                return true;
            }
        }

        return false;
    }

    private function promotePendingPath(string $path, string $dir, string $field): string
    {
        if (! str_contains($path, '/pending/')) {
            return $path;
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $dest = $dir . '/' . $field . '_' . uniqid('', true) . '.' . $ext;
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->move($path, $dest);

            return $dest;
        }

        return $path;
    }
}
