<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->foreignId('customer_id')->nullable()->change();
            $table->foreign('customer_id')->references('id')->on('users')->nullOnDelete();
            $table->string('guest_name', 255)->nullable()->after('customer_id');
            $table->string('guest_phone', 30)->nullable()->after('guest_name');
        });

        Schema::table('driver_trip_requests', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->foreignId('customer_id')->nullable()->change();
            $table->foreign('customer_id')->references('id')->on('users')->nullOnDelete();
            $table->string('guest_name', 255)->nullable()->after('customer_id');
            $table->string('guest_phone', 30)->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('driver_trip_requests', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['guest_name', 'guest_phone']);
            $table->foreignId('customer_id')->nullable(false)->change();
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['guest_name', 'guest_phone']);
            $table->foreignId('customer_id')->nullable(false)->change();
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
