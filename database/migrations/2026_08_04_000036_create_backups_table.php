<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255);
            $table->bigInteger('size_bytes');
            $table->string('path', 500);
            $table->jsonb('record_counts')->nullable();
            $table->integer('assets_count')->nullable();
            $table->bigInteger('assets_size_bytes')->nullable();
            $table->string('status', 50);
            $table->foreignId('encryption_key_id')->nullable()->constrained('backup_encryption_keys')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->index(['status', 'created_at']);
        });

        DB::statement('alter table backups add constraint backups_status_check check (status in (\'pending\', \'processing\', \'completed\', \'failed\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
