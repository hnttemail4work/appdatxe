<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
            Booking::query()
                ->with('schedule:id,driver_id')
                ->whereNull('assigned_driver_id')
                ->whereIn('cancelled_by', ['customer', 'driver'])
                ->chunkById(200, function ($bookings): void {
                    foreach ($bookings as $booking) {
                        $driverId = $booking->resolveAssignedDriverId();
                        if ($driverId) {
                            $booking->updateQuietly(['assigned_driver_id' => $driverId]);
                        }
                    }
                });
        }

        if (Schema::hasColumn('bookings', 'operator_dismissed_at')) {
            $query = Booking::query()
                ->where('cancelled_by', 'customer')
                ->whereNull('operator_dismissed_at');

            if (Schema::hasColumn('bookings', 'repeat_cancel_flag')) {
                $query->where(function ($q): void {
                    $q->whereNull('repeat_cancel_flag')
                        ->orWhere('repeat_cancel_flag', false);
                });
            }

            $query->update(['operator_dismissed_at' => now()]);
        }
    }

    public function down(): void
    {
        // Không hoàn tác backfill dữ liệu lịch sử.
    }
};
