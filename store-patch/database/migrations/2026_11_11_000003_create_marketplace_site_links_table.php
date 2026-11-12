<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_site_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('site_id', 36)->index();
            $table->string('site_url', 2048);
            $table->string('site_name', 160)->nullable();
            $table->string('platform_version', 32)->nullable();
            $table->string('device_code_hash', 64)->unique();
            $table->string('user_code', 12)->index();
            $table->timestamp('expires_at');
            $table->timestamp('approved_at')->nullable();
            $table->text('token')->nullable();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_site_links');
    }
};
