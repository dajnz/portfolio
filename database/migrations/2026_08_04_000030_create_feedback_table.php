<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->string('sender_email', 255);
            $table->string('sender_name', 255);
            $table->text('message');
            $table->string('ip_address', 64);
            $table->string('fingerprint', 255);
            $table->boolean('is_read')->default(false);
            $table->boolean('is_forwarded')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['is_read', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
