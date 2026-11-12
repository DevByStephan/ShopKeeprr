<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 140)->nullable();
            $table->text('body');
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('is_verified')->default(false);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->text('admin_reply')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->unique(['product_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
