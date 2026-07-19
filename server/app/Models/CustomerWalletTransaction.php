<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CustomerWalletTransaction extends Model
{
    protected $fillable = [
        'customer_wallet_id',
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
        return $this->belongsTo(CustomerWallet::class, 'customer_wallet_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
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

    public function depositReference(): string
    {
        return 'CK' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
