<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('currency_symbol', 8)->default('$');
            $table->string('country', 2)->default('US');
            $table->string('logo')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
