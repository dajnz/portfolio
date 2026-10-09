<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('contact_translations', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 100);

            $table->primary(['contact_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_translations');
    }
};
