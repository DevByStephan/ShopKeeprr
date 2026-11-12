<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('interval', 20)->default('month');
            $table->unsignedInteger('interval_count')->default(1);
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 40);
            $table->string('gateway_subscription_id')->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('interval', 20);
            $table->unsignedInteger('interval_count')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('cancel_at_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('failed_payments_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
