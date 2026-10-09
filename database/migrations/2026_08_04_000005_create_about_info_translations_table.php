<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('about_info_translations', function (Blueprint $table) {
            $table->foreignId('about_info_id')->constrained('about_info')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 255);
            $table->string('title', 255);
            $table->text('short_pitch');
            $table->string('location', 255)->nullable();

            $table->primary(['about_info_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_info_translations');
    }
};
