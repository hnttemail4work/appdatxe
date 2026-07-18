<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'photo_id_card')) {
                $table->string('photo_id_card')->nullable()->after('gender');
            }
            if (! Schema::hasColumn('users', 'photo_id_card_back')) {
                $table->string('photo_id_card_back')->nullable()->after('photo_id_card');
            }
            if (! Schema::hasColumn('users', 'approval_status')) {
                $table->string('approval_status', 20)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['photo_id_card', 'photo_id_card_back', 'approval_status'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
