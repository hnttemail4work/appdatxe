<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('inventory_items');
    }

    public function down(): void
    {
        // Tính năng vật tư đã gỡ — không khôi phục bảng.
    }
};
