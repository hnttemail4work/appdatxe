<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tài xế bỏ lỡ cuốc 2 phút — ẩn lời mời đó đến khi tắt/bật lại Hoạt động. */
class DriverCuocOfferHide extends Model
{
    protected $fillable = [
        'driver_user_id',
        'schedule_id',
        'contact_phone',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
