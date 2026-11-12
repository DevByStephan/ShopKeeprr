<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('commission_type', 20)->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->decimal('commission_fixed', 12, 2)->nullable();
            $table->string('payout_method', 20)->default('manual');
            $table->string('paypal_email')->nullable();
            $table->string('stripe_account_id')->nullable()->unique();
            $table->boolean('stripe_charges_enabled')->default(false);
            $table->boolean('stripe_payouts_enabled')->default(false);
            $table->boolean('stripe_details_submitted')->default(false);
            $table->unsignedSmallInteger('payout_hold_days')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->index(['vendor_id', 'is_active']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('variant_id')->constrained()->nullOnDelete();
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('vendor_total', 12, 2)->default(0);
        });

        Schema::create('vendor_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('commission_total', 12, 2)->default(0);
            $table->decimal('payout_total', 12, 2)->default(0);
            $table->decimal('refunded_total', 12, 2)->default(0);
            $table->string('payout_method', 20)->default('manual');
            $table->string('stripe_transfer_id')->nullable()->index();
            $table->decimal('stripe_transfer_reversed', 12, 2)->default(0);
            $table->string('paypal_batch_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'vendor_id']);
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_splits');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['vendor_id', 'commission_amount', 'vendor_total']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropIndex(['vendor_id', 'is_active']);
            $table->dropColumn('vendor_id');
        });

        Schema::dropIfExists('vendors');
    }
};
