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
            INSERT INTO project_screenshots (project_id, image_path, title, sort_order, created_at, updated_at)
            SELECT p.id, p.preview_image, p.preview_image_alt, 0, now(), now()
            FROM projects p
            WHERE p.preview_image IS NOT NULL
              AND p.preview_image <> ''
              AND NOT EXISTS (
                  SELECT 1 FROM project_screenshots s WHERE s.project_id = p.id
              )
            SQL);

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['preview_image_alt', 'preview_image']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('preview_image', 255)->nullable()->after('slug');
            $table->jsonb('preview_image_alt')->nullable()->after('preview_image');
        });

        DB::statement(<<<'SQL'
            UPDATE projects p
            SET preview_image = s.image_path,
                preview_image_alt = s.title
            FROM (
                SELECT DISTINCT ON (project_id) project_id, image_path, title
                FROM project_screenshots
                ORDER BY project_id, sort_order, id
            ) s
            WHERE s.project_id = p.id
            SQL);
    }
};
