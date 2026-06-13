<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('tax_code')->nullable()->index();
            $table->string('business_license')->nullable();
            $table->enum('kyc_status', ['pending', 'approved', 'suspended', 'rejected'])->default('pending')->index();
            $table->json('documents')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['kyc_status', 'approved_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_profiles');
    }
};
