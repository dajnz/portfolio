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
        Schema::create('skill_group_translations', function (Blueprint $table) {
            $table->foreignId('skill_group_id')->constrained('skill_groups')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 100);

            $table->primary(['skill_group_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_group_translations');
    }
};
