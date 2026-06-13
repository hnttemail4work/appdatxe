<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('name')->comment('Tên vật tư');
            $table->string('unit')->default('cái')->comment('Đơn vị: lít, cái, bộ...');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0)->comment('Giá/đơn vị (VND)');
            $table->enum('type', ['import', 'export'])->default('import')->index()->comment('Nhập/xuất');
            $table->string('category')->default('general')->comment('fuel/tire/spare_part/other');
            $table->text('note')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            $table->index(['operator_id', 'type']);
            $table->index(['vehicle_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
