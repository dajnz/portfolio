<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE project_screenshots
            SET title = alt_text
            WHERE (title IS NULL OR title = '{}'::jsonb)
              AND alt_text IS NOT NULL
              AND alt_text <> '{}'::jsonb
            SQL);

        Schema::table('project_screenshots', function (Blueprint $table) {
            $table->dropColumn('alt_text');
        });
    }

    public function down(): void
    {
        Schema::table('project_screenshots', function (Blueprint $table) {
            $table->jsonb('alt_text')->nullable();
        });
    }
};
