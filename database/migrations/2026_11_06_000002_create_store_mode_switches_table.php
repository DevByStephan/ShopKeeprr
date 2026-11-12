<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_mode_switches', function (Blueprint $table) {
            $table->id();
            $table->string('from_mode', 32)->nullable();
            $table->string('to_mode', 32);
            $table->unsignedBigInteger('mode_revision')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('completed');
            $table->text('note')->nullable();
            $table->json('affected')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['to_mode', 'status']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->string('mode', 32)->nullable();
            $table->unsignedBigInteger('mode_revision')->default(0);
            $table->index(['mode_revision']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('mode_revision')->default(0)->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('mode_revision');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['mode_revision']);
            $table->dropColumn(['mode', 'mode_revision']);
        });

        Schema::dropIfExists('store_mode_switches');
    }
};
