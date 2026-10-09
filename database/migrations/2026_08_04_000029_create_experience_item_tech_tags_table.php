<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('experience_item_tech_tags', function (Blueprint $table) {
            $table->foreignId('experience_item_id')->constrained('experience_timeline_items')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();

            $table->primary(['experience_item_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_item_tech_tags');
    }
};
