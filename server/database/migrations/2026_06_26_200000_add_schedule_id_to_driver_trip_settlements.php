<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_trip_settlements', function (Blueprint $table): void {
            $table->foreignId('schedule_id')->nullable()->after('driver_wallet_id')
                ->constrained('schedules')->cascadeOnDelete();
        });

        DB::table('driver_trip_settlements')
            ->orderBy('id')
            ->get()
            ->each(function ($row): void {
                $scheduleId = DB::table('bookings')->where('id', $row->booking_id)->value('schedule_id');
                if ($scheduleId) {
                    DB::table('driver_trip_settlements')->where('id', $row->id)->update(['schedule_id' => $scheduleId]);
                }
            });

        $groups = DB::table('driver_trip_settlements')
            ->whereNotNull('schedule_id')
            ->orderBy('id')
            ->get()
            ->groupBy('schedule_id');

        foreach ($groups as $scheduleId => $rows) {
            if ($rows->count() <= 1) {
                continue;
            }

            $primary = $rows->sortByDesc(fn ($r) => $r->status === 'completed' ? 1 : 0)->first();
            $revenue = $rows->sum('revenue_amount');
            $fee = $rows->sum('platform_fee_amount');

            DB::table('driver_trip_settlements')->where('id', $primary->id)->update([
                'revenue_amount'      => $revenue,
                'platform_fee_amount' => $fee,
            ]);

            $rows->where('id', '!=', $primary->id)->each(function ($dup): void {
                DB::table('driver_trip_settlements')->where('id', $dup->id)->delete();
            });
        }

        Schema::table('driver_trip_settlements', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
            $table->dropUnique(['booking_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE driver_trip_settlements MODIFY booking_id BIGINT UNSIGNED NULL');
        }

        Schema::table('driver_trip_settlements', function (Blueprint $table): void {
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
            $table->unique('schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('driver_trip_settlements', function (Blueprint $table): void {
            $table->dropUnique(['schedule_id']);
            $table->dropForeign(['schedule_id']);
            $table->dropColumn('schedule_id');
        });
    }
};
