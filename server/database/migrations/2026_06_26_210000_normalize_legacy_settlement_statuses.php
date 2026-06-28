<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('driver_trip_settlements')
            ->whereIn('status', ['pending_operator_fee', 'pending_admin_transfer', 'pending_deposit'])
            ->update(['status' => 'pending_settle']);
    }

    public function down(): void
    {
        // Không khôi phục trạng thái cũ — dữ liệu đã chuẩn hóa.
    }
};
