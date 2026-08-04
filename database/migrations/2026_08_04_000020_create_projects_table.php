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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255);
            $table->string('preview_image', 255)->nullable();
            $table->jsonb('preview_image_alt')->nullable();
            $table->string('github_url', 500)->nullable();
            $table->string('live_demo_url', 500)->nullable();
            $table->bigInteger('tech_writeup_article_id')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('featured_sort_order')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->integer('tech_tags_count')->default(0);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique('slug');
            $table->index('is_published');
        });

        DB::statement('create index projects_is_featured_index on projects (is_featured) where is_featured = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
