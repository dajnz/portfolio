<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('article_views', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('blog_articles')->cascadeOnDelete();
            $table->bigInteger('views')->default(0);

            $table->primary('article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_views');
    }
};
