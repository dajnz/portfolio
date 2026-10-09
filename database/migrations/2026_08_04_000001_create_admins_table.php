<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->string('login', 100)->unique();
            $table->string('password', 255);
            $table->string('github_id', 255)->nullable();
            $table->text('github_token')->nullable();
            $table->string('totp_secret', 255)->nullable();
            $table->jsonb('totp_recovery_codes')->nullable();
            $table->boolean('totp_enabled')->default(false);
            $table->integer('totp_failed_attempts')->default(0);
            $table->timestamp('totp_locked_until')->nullable();
            $table->integer('session_version')->default(0);
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
