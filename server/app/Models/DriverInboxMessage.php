<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverInboxMessage extends Model
{
    public const CATEGORY_INFO = 'info';

    public const CATEGORY_NOTICE = 'notice';

    protected $fillable = [
        'user_id',
        'category',
        'title',
        'body',
        'meta',
        'read_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'meta'    => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
