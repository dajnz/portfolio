<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * `position` becomes the second required column of
     * `experience_timeline_item_translations` (the position is mandatory
     * per locale, company name + country stay optional but all-or-none),
     * and the `dates_check` constraint tightens from
     * `ended_at >= started_at` to `ended_at > started_at` — an item must
     * not end on or before the day it started.
     */
    public function up(): void
    {
        DB::table('experience_timeline_item_translations')
            ->whereNull('position')
            ->update(['position' => '']);

        Schema::table('experience_timeline_item_translations', function (Blueprint $table) {
            $table->string('position', 255)->nullable(false)->change();
        });

        DB::statement(
            'alter table experience_timeline_items drop constraint experience_timeline_items_dates_check'
        );
        DB::statement(
            'alter table experience_timeline_items add constraint experience_timeline_items_dates_check check (ended_at is null or ended_at > started_at)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'alter table experience_timeline_items drop constraint experience_timeline_items_dates_check'
        );
        DB::statement(
            'alter table experience_timeline_items add constraint experience_timeline_items_dates_check check (ended_at is null or ended_at >= started_at)'
        );

        Schema::table('experience_timeline_item_translations', function (Blueprint $table) {
            $table->string('position', 255)->nullable()->change();
        });
    }
};
