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
        Schema::create('about_info', function (Blueprint $table) {
            $table->id();
            $table->string('photo', 255)->nullable();
            $table->jsonb('photo_alt')->nullable();
            $table->boolean('show_location')->default(true);
            $table->string('timezone', 100)->nullable();
            $table->boolean('relocation_readiness')->default(false);
            $table->string('availability_status', 50)->nullable();
            $table->string('work_type', 100)->default('remote');
            $table->string('resume_file', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        DB::statement("alter table about_info add constraint about_info_availability_status_check check (availability_status in ('available', 'limited', 'busy'))");
        DB::statement("alter table about_info add constraint about_info_work_type_check check (work_type in ('remote', 'hybrid', 'office'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('about_info');
    }
};
