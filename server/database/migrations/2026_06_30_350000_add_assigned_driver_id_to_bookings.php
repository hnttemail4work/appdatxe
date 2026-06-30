<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $table->foreignId('assigned_driver_id')
                    ->nullable()
                    ->after('schedule_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasColumn('bookings', 'assigned_driver_id')) {
            return;
        }

        Booking::query()
            ->with('schedule:id,driver_id')
            ->whereNull('assigned_driver_id')
            ->whereHas('schedule', fn ($q) => $q->whereNotNull('driver_id'))
            ->terminalForDriverHistory()
            ->chunkById(200, function ($bookings): void {
                foreach ($bookings as $booking) {
                    $driverId = $booking->schedule?->driver_id;
                    if ($driverId) {
                        $booking->updateQuietly(['assigned_driver_id' => $driverId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $table->dropConstrainedForeignId('assigned_driver_id');
            }
        });
    }
};
