<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_reasons', function (Blueprint $table): void {
            $table->id();
            $table->string('label', 200);
            $table->enum('audience', ['customer', 'driver', 'both'])->default('both');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'cancellation_reason_id')) {
                $table->foreignId('cancellation_reason_id')->nullable()->after('cancelled_by')->constrained('cancellation_reasons')->nullOnDelete();
            }
            if (! Schema::hasColumn('bookings', 'cancellation_reason_label')) {
                $table->string('cancellation_reason_label', 200)->nullable()->after('cancellation_reason_id');
            }
        });

        DB::table('cancellation_reasons')->insert([
            ['label' => 'Đổi lịch / đổi chuyến', 'audience' => 'both', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Không còn nhu cầu đi', 'audience' => 'customer', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Khách không đến điểm đón', 'audience' => 'driver', 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Xe hỏng / sự cố', 'audience' => 'driver', 'sort_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Lý do khác', 'audience' => 'both', 'sort_order' => 99, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'cancellation_reason_id')) {
                $table->dropConstrainedForeignId('cancellation_reason_id');
            }
            if (Schema::hasColumn('bookings', 'cancellation_reason_label')) {
                $table->dropColumn('cancellation_reason_label');
            }
        });

        Schema::dropIfExists('cancellation_reasons');
    }
};
