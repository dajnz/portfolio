<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Models;

use Database\Factories\AnalyticsReportModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `analytics_reports` (database.md:531-548). The
 * reworked per-metric aggregate store — `created_at` only, no
 * `updated_at`; `span` is one of day|week|month|year|all (CHECK
 * constrained), `dimension` NULL for block/chart series rows.
 *
 * @property int $id
 * @property string $metric
 * @property string $span
 * @property string|null $dimension
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property float $value
 * @property \Illuminate\Support\Carbon $created_at
 */
final class AnalyticsReportModel extends Model
{
    use HasFactory;

    protected $table = 'analytics_reports';

    public $timestamps = false;

    protected $fillable = [
        'metric',
        'span',
        'dimension',
        'period_start',
        'period_end',
        'value',
    ];

    protected static function newFactory(): Factory
    {
        return AnalyticsReportModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'value' => 'float',
            'created_at' => 'datetime',
        ];
    }
}
