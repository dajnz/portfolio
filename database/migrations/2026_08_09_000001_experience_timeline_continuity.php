<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Timeline continuity (ADR-0024, user request 2026-08-09):
     * - `position` becomes the second REQUIRED column of
     *   `experience_timeline_item_translations` (after the ADR-0002
     *   `short_description` — the position is mandatory per locale,
     *   company name + country stay optional but all-or-none);
     * - the `dates_check` constraint tightens from
     *   `ended_at >= started_at` to `ended_at > started_at` — an item
     *   must not end on or before the day it started.
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
