<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Models;

use Database\Factories\AnalyticsDailyMetricModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `analytics_daily_metrics`. The per-day aggregate
 * store is append-only-ish — `created_at` only, no `updated_at`; the
 * `dimension` column holds the entity key (NULL for global metrics),
 * `value` + `samples` the weighted sum/samples pair (never averages).
 *
 * @property int $id
 * @property string $metric
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $dimension
 * @property float $value
 * @property int $samples
 * @property \Illuminate\Support\Carbon $created_at
 */
final class AnalyticsDailyMetricModel extends Model
{
    use HasFactory;

    protected $table = 'analytics_daily_metrics';

    public $timestamps = false;

    protected $fillable = [
        'metric',
        'date',
        'dimension',
        'value',
        'samples',
    ];

    protected static function newFactory(): Factory
    {
        return AnalyticsDailyMetricModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'value' => 'float',
            'samples' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
