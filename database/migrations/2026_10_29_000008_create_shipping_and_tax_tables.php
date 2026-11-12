<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('countries');
            $table->json('states')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30)->default('flat_rate');
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('per_kg', 12, 2)->nullable();
            $table->decimal('free_threshold', 12, 2)->nullable();
            $table->decimal('min_weight', 10, 3)->nullable();
            $table->decimal('max_weight', 10, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('country', 2);
            $table->string('state')->nullable();
            $table->decimal('rate', 7, 4);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zones');
    }
};
