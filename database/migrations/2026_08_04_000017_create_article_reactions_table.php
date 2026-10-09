<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('article_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('blog_articles')->cascadeOnDelete();
            $table->string('fingerprint', 255);
            $table->string('reaction', 10);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['article_id', 'fingerprint']);
        });

        DB::statement('alter table article_reactions add constraint article_reactions_reaction_check check (reaction in (\'like\', \'dislike\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('article_reactions');
    }
};
