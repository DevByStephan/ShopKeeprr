<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('discount_type', 20)->default('percentage');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('product_bundles')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['bundle_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_bundles');
    }
};
