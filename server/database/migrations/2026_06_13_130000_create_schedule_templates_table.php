<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('driver_name')->default('Chưa phân công');
            $table->time('departure_time');
            $table->unsignedSmallInteger('duration_minutes')->default(720);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
        });

        Schema::table('schedules', function (Blueprint $table): void {
            $table->foreignId('template_id')->nullable()->after('id')->constrained('schedule_templates')->nullOnDelete();
            $table->date('service_date')->nullable()->after('departure_time')->index();
            $table->unique(['template_id', 'service_date']);
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropUnique(['template_id', 'service_date']);
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn('service_date');
        });

        Schema::dropIfExists('schedule_templates');
    }
};
