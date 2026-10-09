<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Analytics\Infra\Models\AnalyticsReportModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The default is a whole-time global block row (`span = 'all'`,
 * dimension NULL) — pass explicit values for series/ranking rows.
 */
final class AnalyticsReportModelFactory extends Factory
{
    protected $model = AnalyticsReportModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'metric' => 'page_views',
            'span' => 'all',
            'dimension' => null,
            'period_start' => $this->faker->date(),
            'period_end' => $this->faker->date(),
            'value' => $this->faker->randomFloat(2, 0, 1000),
        ];
    }
}
