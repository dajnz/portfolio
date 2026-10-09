<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('blog_article_translations', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('blog_articles')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 255);
            $table->text('short_description');
            $table->text('content');
            $table->integer('reading_time_minutes');
            $table->tsvector('search_vector')->nullable();

            $table->primary(['article_id', 'locale']);
            $table->index('search_vector')->algorithm('gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_article_translations');
    }
};
