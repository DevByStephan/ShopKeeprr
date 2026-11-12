<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_hub_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('status', 20)->default('subscribed');
            $table->string('source', 80)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('provider_contact_id', 190)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'provider']);
        });

        Schema::create('email_hub_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190);
            $table->string('action', 40);
            $table->string('status', 20);
            $table->string('provider', 40)->nullable();
            $table->string('message', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_hub_sync_logs');
        Schema::dropIfExists('email_hub_contacts');
    }
};
