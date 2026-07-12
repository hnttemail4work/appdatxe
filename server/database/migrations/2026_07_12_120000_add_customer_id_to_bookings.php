<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('contact_phone')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index(['customer_id', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });
    }
};
