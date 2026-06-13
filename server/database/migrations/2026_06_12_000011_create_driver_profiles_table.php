<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->string('license_number')->unique()->comment('Bằng lái xe');
            $table->enum('license_class', ['B1', 'B2', 'C', 'D', 'E', 'F'])->default('B2');
            $table->date('license_expiry');
            $table->integer('experience_years')->default(0);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};
