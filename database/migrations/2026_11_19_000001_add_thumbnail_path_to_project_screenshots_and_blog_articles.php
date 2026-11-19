<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('project_screenshots', function (Blueprint $table): void {
            $table->string('thumbnail_path', 255)->nullable()->after('image_path');
        });

        Schema::table('blog_articles', function (Blueprint $table): void {
            $table->string('thumbnail_path', 255)->nullable()->after('lead_image');
        });
    }

    public function down(): void
    {
        Schema::table('project_screenshots', function (Blueprint $table): void {
            $table->dropColumn('thumbnail_path');
        });

        Schema::table('blog_articles', function (Blueprint $table): void {
            $table->dropColumn('thumbnail_path');
        });
    }
};
