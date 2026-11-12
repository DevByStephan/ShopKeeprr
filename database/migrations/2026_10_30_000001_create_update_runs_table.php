<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_runs', function (Blueprint $table) {
            $table->id();
            $table->string('from_version', 30);
            $table->string('to_version', 30);
            $table->string('status', 40)->default('pending')->index();
            $table->string('step', 60)->nullable();
            $table->string('channel', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('package_url')->nullable();
            $table->string('package_sha256', 64)->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->longText('notes')->nullable();
            $table->string('backup_path')->nullable();
            $table->string('database_backup_path')->nullable();
            $table->string('database_backup_format', 30)->nullable();
            $table->json('backup_meta')->nullable();
            $table->unsignedInteger('files_changed')->default(0);
            $table->longText('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_runs');
    }
};
