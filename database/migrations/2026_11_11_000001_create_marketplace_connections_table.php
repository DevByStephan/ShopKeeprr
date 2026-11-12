<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_connections', function (Blueprint $table) {
            $table->id();
            $table->string('store_url', 2048);
            $table->uuid('site_id')->unique();
            $table->string('site_name', 160)->nullable();
            $table->string('account_name', 160)->nullable();
            $table->string('account_email', 190)->nullable();
            $table->text('access_token');
            $table->string('token_last_four', 8)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_connections');
    }
};
