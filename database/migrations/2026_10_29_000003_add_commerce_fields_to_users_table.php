<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('tier_id')->nullable()->after('password')->constrained('customer_tiers')->nullOnDelete();
            $table->string('role', 20)->default('customer')->after('email_verified_at');
            $table->string('wholesale_status', 20)->default('none')->after('tier_id');
            $table->string('company_name')->nullable()->after('wholesale_status');
            $table->string('tax_id')->nullable()->after('company_name');
            $table->string('phone', 40)->nullable()->after('tax_id');
            $table->timestamp('wholesale_applied_at')->nullable();
            $table->timestamp('wholesale_approved_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->index(['role', 'wholesale_status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropForeign(['tier_id']);
            $table->dropIndex(['role', 'wholesale_status']);
            $table->dropColumn([
                'store_id', 'tier_id', 'role', 'wholesale_status', 'company_name', 'tax_id',
                'phone', 'wholesale_applied_at', 'wholesale_approved_at', 'admin_notes',
                'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
