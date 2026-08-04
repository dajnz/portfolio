<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('experience_timeline_items', function (Blueprint $table) {
            $table->id();
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->integer('sort_order')->default(0);
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->boolean('include_in_resume')->default(true);
            $table->string('external_project_name', 255)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        DB::statement('alter table experience_timeline_items add constraint experience_timeline_items_dates_check check (ended_at is null or ended_at >= started_at)');
        DB::statement('alter table experience_timeline_items add constraint experience_timeline_items_project_reference_check check (project_id is null or external_project_name is null)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experience_timeline_items');
    }
};
