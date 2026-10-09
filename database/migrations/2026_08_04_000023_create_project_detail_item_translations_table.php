<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('project_detail_item_translations', function (Blueprint $table) {
            $table->foreignId('detail_item_id')->constrained('project_detail_items')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 200);
            $table->text('description');

            $table->primary(['detail_item_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_detail_item_translations');
    }
};
