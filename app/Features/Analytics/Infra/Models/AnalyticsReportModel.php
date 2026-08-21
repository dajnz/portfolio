<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Models;

use Database\Factories\AnalyticsReportModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `analytics_reports` (database.md:507-522). Stored
 * aggregation results — no `created_at`/`updated_at` columns, the
 * `generated_at` timestamp records when the aggregation ran. `data`
 * holds the report-type-specific payload (analytics.md:174-179).
 *
 * @property int $id
 * @property string $report_type
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property array<string, mixed> $data
 * @property \Illuminate\Support\Carbon $generated_at
 */
final class AnalyticsReportModel extends Model
{
    use HasFactory;

    protected $table = 'analytics_reports';

    public $timestamps = false;

    protected $fillable = [
        'report_type',
        'period_start',
        'period_end',
        'data',
        'generated_at',
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
            'data' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
