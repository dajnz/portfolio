<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * `short_description` becomes the only required column of
     * `experience_timeline_item_translations` (the identity trio
     * position/company_name/company_country is all-or-none, but each
     * stays nullable — content-management.md:654-659). PostgreSQL
     * refuses NOT NULL over existing nulls, so they are backfilled
     * first.
     */
    public function up(): void
    {
        DB::table('experience_timeline_item_translations')
            ->whereNull('short_description')
            ->update(['short_description' => '']);

        Schema::table('experience_timeline_item_translations', function (Blueprint $table) {
            $table->text('short_description')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('experience_timeline_item_translations', function (Blueprint $table) {
            $table->text('short_description')->nullable()->change();
        });
    }
};
