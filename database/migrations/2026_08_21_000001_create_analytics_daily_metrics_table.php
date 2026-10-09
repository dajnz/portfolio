<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The per-day aggregated metrics store: one row per `(metric, date,
     * dimension)` — NULL dimension = global metric, otherwise the
     * entity key (page URL, `article:{id}`, `project:{id}`, CTA
     * target). `value` + `samples` keep the weighted sum/samples
     * pair (never averages), so every report span can compute exact
     * averages; min/max metrics aggregate as plain values. Append-only
     * (upserted per day), never pruned.
     *
     * The unique key is `NULLS NOT DISTINCT`: PostgreSQL treats NULL
     * index values as distinct by default, which would let duplicate
     * global (dimension = NULL) rows for the same (metric, date)
     * slip past the constraint and break the daily upsert.
     */
    public function up(): void
    {
        Schema::create('analytics_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 100);
            $table->date('date');
            $table->string('dimension', 500)->nullable();
            $table->double('value');
            $table->bigInteger('samples');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['metric', 'date', 'dimension'])->nullsNotDistinct();
            $table->index(['metric', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_metrics');
    }
};
