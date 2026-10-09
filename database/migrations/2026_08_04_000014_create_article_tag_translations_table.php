<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('article_tag_translations', function (Blueprint $table) {
            $table->foreignId('article_tag_id')->constrained('article_tags')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 100);

            $table->primary(['article_tag_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_tag_translations');
    }
};
