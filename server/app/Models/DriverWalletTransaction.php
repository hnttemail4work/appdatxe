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

    public static function historyLabelFor(?string $status, ?string $transferRef = null): string
    {
        if (is_string($transferRef) && str_starts_with($transferRef, 'WTRIP-')) {
            return 'Thu nhập cuốc trừ ví';
        }

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

    public function isWalletTripEarning(): bool
    {
        return str_starts_with((string) ($this->transfer_ref ?? ''), 'WTRIP-');
    }

    public function proofImageUrl(): ?string
    {
        $path = trim((string) $this->proof_image_path);

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function depositReference(): string
    {
        return 'NV' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
