<?php

use App\Support\RouteDistanceCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (RouteDistanceCatalog::hubRouteRows() as $row) {
            $existing = DB::table('routes')
                ->where('departure', $row['departure'])
                ->where('destination', $row['destination'])
                ->first();

            if ($existing) {
                DB::table('routes')
                    ->where('id', $existing->id)
                    ->update([
                        'distance_km' => $row['distance_km'],
                        'updated_at'  => $now,
                    ]);
            } else {
                DB::table('routes')->insert([
                    'departure'    => $row['departure'],
                    'destination'  => $row['destination'],
                    'base_price'   => 0,
                    'distance_km'  => $row['distance_km'],
                    'is_active'    => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Giữ dữ liệu tuyến — không rollback xóa.
    }
};
