<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woocommerce_migrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('disk_path')->nullable();
            $table->string('extracted_path')->nullable();
            $table->string('status', 30)->default('uploaded')->index();
            $table->string('mode', 20)->nullable();
            $table->json('files')->nullable();
            $table->json('options')->nullable();
            $table->json('stats')->nullable();
            $table->string('error_report_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woocommerce_migrations');
    }
};
