<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_translations', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 255);
            $table->text('brief_description');
            $table->text('full_description')->nullable();
            $table->text('problem')->nullable();
            $table->text('solution')->nullable();
            $table->tsvector('search_vector')->nullable();

            $table->primary(['project_id', 'locale']);
            $table->index('search_vector')->algorithm('gin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_translations');
    }
};
