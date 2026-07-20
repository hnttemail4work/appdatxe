<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TripMessage extends Model
{
    protected $fillable = [
        'booking_id',
        'sender_user_id',
        'sender_role',
        'body',
        'image_path',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function imageUrl(): ?string
    {
        $path = trim((string) ($this->image_path ?? ''));
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function previewText(): string
    {
        $body = trim((string) ($this->body ?? ''));
        if ($body !== '') {
            return $body;
        }

        return $this->image_path ? '[Ảnh]' : '';
    }
}
