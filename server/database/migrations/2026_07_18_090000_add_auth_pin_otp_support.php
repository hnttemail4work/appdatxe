<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'login_fail_count')) {
                $table->unsignedTinyInteger('login_fail_count')->default(0)->after('must_change_password');
            }
            if (! Schema::hasColumn('users', 'login_locked_until')) {
                $table->timestamp('login_locked_until')->nullable()->after('login_fail_count');
            }
        });

        if (! Schema::hasTable('auth_verification_codes')) {
            Schema::create('auth_verification_codes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('phone', 30)->index();
                $table->string('purpose', 40)->index();
                $table->string('code_hash');
                $table->timestamp('expires_at')->index();
                $table->timestamp('consumed_at')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->string('status', 20)->default('active')->index();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['phone', 'purpose', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_verification_codes');

        Schema::table('users', function (Blueprint $table): void {
            foreach (['login_fail_count', 'login_locked_until'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
