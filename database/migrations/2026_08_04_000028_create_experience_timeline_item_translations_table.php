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
        Schema::create('experience_timeline_item_translations', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained('experience_timeline_items')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('position', 255)->nullable();
            $table->string('company_name', 255)->nullable();
            $table->string('company_country', 100)->nullable();
            $table->text('short_description')->nullable();
            $table->jsonb('achievements')->nullable();

            $table->primary(['item_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experience_timeline_item_translations');
    }
};
