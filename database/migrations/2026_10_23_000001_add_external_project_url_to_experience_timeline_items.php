<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('experience_timeline_items', function (Blueprint $table) {
            $table->string('external_project_url', 500)->nullable();
        });

        DB::statement('alter table experience_timeline_items add constraint experience_timeline_items_external_project_url_check check (external_project_url is null or external_project_name is not null)');
    }

    public function down(): void
    {
        DB::statement('alter table experience_timeline_items drop constraint if exists experience_timeline_items_external_project_url_check');

        Schema::table('experience_timeline_items', function (Blueprint $table) {
            $table->dropColumn('external_project_url');
        });
    }
};
