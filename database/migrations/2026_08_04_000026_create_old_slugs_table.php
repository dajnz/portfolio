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
        Schema::create('old_slugs', function (Blueprint $table) {
            $table->id();
            $table->string('old_slug', 255);
            $table->string('entity_type', 50);
            $table->bigInteger('entity_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['old_slug', 'entity_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('old_slugs');
    }
};
