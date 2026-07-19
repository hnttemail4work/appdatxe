<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'customer_chat_last_read_id')) {
                $table->unsignedBigInteger('customer_chat_last_read_id')
                    ->nullable()
                    ->after('driver_chat_last_read_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'customer_chat_last_read_id')) {
                $table->dropColumn('customer_chat_last_read_id');
            }
        });
    }
};
