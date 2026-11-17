<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_migrations', function (Blueprint $table) {
            $table->string('source', 20)->default('files')->index();
            $table->foreignId('connection_id')->nullable()->constrained('woocommerce_connections')->nullOnDelete();
            $table->json('remote_state')->nullable();
            $table->json('progress')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('woocommerce_migrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('connection_id');
            $table->dropColumn(['source', 'remote_state', 'progress']);
        });
    }
};
