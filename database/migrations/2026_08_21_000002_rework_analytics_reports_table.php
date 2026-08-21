<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The `analytics_reports` rework (database.md:531-548,
     * analytics.md:186-202, B290): the old per-report-type jsonb rows
     * (`report_type`/`data`/`generated_at` — the B288 schema, dropped
     * with the periodic report pipeline in 11.3.1) are replaced by
     * per-metric aggregates pre-computed for every span. One row per
     * `(metric, span, dimension, period_start, period_end)` — NULL
     * dimension = block/chart series row, otherwise the entity key
     * (rankings). The CHECK constraint is created via raw SQL per C11
     * (Laravel 13 has no `Blueprint::check()`); the unique key is
     * `NULLS NOT DISTINCT` like the daily metrics (NULL dimension
     * rows must conflict on re-aggregation).
     */
    public function up(): void
    {
        Schema::dropIfExists('analytics_reports');

        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 100);
            $table->string('span', 20);
            $table->string('dimension', 500)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->double('value');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['metric', 'span', 'dimension', 'period_start', 'period_end'])->nullsNotDistinct();
            $table->index(['span', 'period_start']);
        });

        DB::statement("alter table analytics_reports add constraint analytics_reports_span_check check (span in ('day', 'week', 'month', 'year', 'all'))");
    }

    /**
     * Reverse the migrations. The superseded B288 schema is gone with
     * the old report pipeline (11.3.1) and is intentionally not
     * restored here.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_reports');
    }
};
