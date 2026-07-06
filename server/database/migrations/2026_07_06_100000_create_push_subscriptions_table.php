<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('audience', 16);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('browser_id', 128)->nullable()->index();
            $table->string('contact_phone', 30)->nullable()->index();
            $table->string('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('public_key', 255);
            $table->string('auth_token', 64);
            $table->string('content_encoding', 16)->default('aesgcm');
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['audience', 'user_id']);
        });

        Schema::create('push_notification_dedup', function (Blueprint $table): void {
            $table->id();
            $table->string('dedup_key', 191)->unique();
            $table->timestamp('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_dedup');
        Schema::dropIfExists('push_subscriptions');
    }
};
