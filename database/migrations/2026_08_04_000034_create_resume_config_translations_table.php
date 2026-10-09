<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('resume_config_translations', function (Blueprint $table) {
            $table->foreignId('resume_config_id')->constrained('resume_config')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('about_text')->nullable();

            $table->primary(['resume_config_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_config_translations');
    }
};
