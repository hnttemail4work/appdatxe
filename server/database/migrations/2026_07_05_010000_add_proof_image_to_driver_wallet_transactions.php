<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_wallet_transactions')) {
            return;
        }

        if (Schema::hasColumn('driver_wallet_transactions', 'proof_image_path')) {
            return;
        }

        Schema::table('driver_wallet_transactions', function (Blueprint $table): void {
            $table->string('proof_image_path', 255)->nullable()->after('transfer_ref');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('driver_wallet_transactions')) {
            return;
        }

        if (! Schema::hasColumn('driver_wallet_transactions', 'proof_image_path')) {
            return;
        }

        Schema::table('driver_wallet_transactions', function (Blueprint $table): void {
            $table->dropColumn('proof_image_path');
        });
    }
};
