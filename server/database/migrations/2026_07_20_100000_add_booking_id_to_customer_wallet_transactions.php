<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_wallet_transactions')) {
            return;
        }

        Schema::table('customer_wallet_transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_wallet_transactions', 'booking_id')) {
                $table->foreignId('booking_id')
                    ->nullable()
                    ->after('customer_wallet_id')
                    ->constrained('bookings')
                    ->nullOnDelete();
                $table->unique(['booking_id', 'type'], 'customer_wallet_tx_booking_type_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_wallet_transactions')) {
            return;
        }

        Schema::table('customer_wallet_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_wallet_transactions', 'booking_id')) {
                $table->dropUnique('customer_wallet_tx_booking_type_unique');
                $table->dropConstrainedForeignId('booking_id');
            }
        });
    }
};
