<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DriverWalletTransaction extends Model
{
    protected $fillable = [
        'driver_wallet_id',
        'type',
        'amount',
        'status',
        'transfer_ref',
        'proof_image_path',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function wallet()
    {
        return $this->belongsTo(DriverWallet::class, 'driver_wallet_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusLabel(): string
    {
        return self::statusLabelFor($this->status);
    }

    public static function historyLabelFor(?string $status): string
    {
        return 'Nạp ví';
    }

    public static function statusLabelFor(?string $status): string
    {
        return match ($status) {
            'approved' => 'Đã cộng ví',
            'rejected' => 'Không thành công',
            default    => 'Chờ duyệt',
        };
    }

    public function proofImageUrl(): ?string
    {
        $path = trim((string) $this->proof_image_path);

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'approved' => \App\Support\StatusBadge::SUCCESS,
            'rejected' => \App\Support\StatusBadge::DANGER,
            default    => \App\Support\StatusBadge::PENDING,
        };
    }

    public function depositReference(): string
    {
        return 'NV' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
