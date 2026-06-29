<?php

use App\Models\DriverProfile;
use App\Models\DriverWallet;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DriverProfile::query()
            ->whereDoesntHave('wallet')
            ->pluck('id')
            ->each(fn (int $id) => DriverWallet::query()->create([
                'driver_profile_id' => $id,
                'balance'           => 0,
            ]));
    }

    public function down(): void
    {
        // Không xóa ví đã tạo.
    }
};
