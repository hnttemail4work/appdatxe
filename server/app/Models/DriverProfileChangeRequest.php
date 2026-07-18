<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DriverProfileChangeRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'driver_profile_id',
        'status',
        'payload',
        'photos',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'photos'      => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function photoUrl(string $field): ?string
    {
        $path = $this->photos[$field] ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /** @return list<string> */
    public function vehiclePhotoUrls(): array
    {
        $paths = $this->photos['photo_vehicles'] ?? [];
        if (! is_array($paths)) {
            return [];
        }

        return collect($paths)
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->map(fn (string $p) => Storage::disk('public')->url($p))
            ->values()
            ->all();
    }
}
