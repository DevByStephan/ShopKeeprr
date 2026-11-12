<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_mode_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 32);
            $table->boolean('is_visible')->default(true);
            $table->string('origin', 16)->default('system');
            $table->string('reason')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'mode']);
            $table->index(['mode', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_mode_states');
    }
};
