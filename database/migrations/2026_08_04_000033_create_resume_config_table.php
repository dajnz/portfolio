<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('resume_config', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 10)->default('manual');
            $table->jsonb('layout');
            $table->string('custom_resume_file', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        DB::statement('alter table resume_config add constraint resume_config_mode_check check (mode in (\'auto\', \'manual\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_config');
    }
};
