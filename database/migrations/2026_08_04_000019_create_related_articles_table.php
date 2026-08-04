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
        Schema::create('related_articles', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('blog_articles')->cascadeOnDelete();
            $table->foreignId('related_article_id')->constrained('blog_articles')->cascadeOnDelete();

            $table->primary(['article_id', 'related_article_id']);
        });

        DB::statement('alter table related_articles add constraint related_articles_pair_distinct_check check (article_id <> related_article_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('related_articles');
    }
};
