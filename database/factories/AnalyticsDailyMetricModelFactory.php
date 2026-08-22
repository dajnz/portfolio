<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Analytics\Infra\Models\AnalyticsDailyMetricModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `analytics_daily_metrics` (B21). The default
 * dimension is NULL (a global metric row) — pass an explicit
 * dimension for entity-keyed rows.
 */
final class AnalyticsDailyMetricModelFactory extends Factory
{
    protected $model = AnalyticsDailyMetricModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'metric' => 'page_views',
            'date' => $this->faker->date(),
            'dimension' => null,
            'value' => $this->faker->randomFloat(2, 0, 1000),
            'samples' => $this->faker->numberBetween(1, 100),
        ];
    }
}
