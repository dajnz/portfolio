<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Analytics\Infra\Models\AnalyticsReportModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `analytics_reports` (B21). The default payload
 * matches the `overall` report shape (analytics.md:176).
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
            'report_type' => 'overall',
            'period_start' => $this->faker->date(),
            'period_end' => $this->faker->date(),
            'data' => [
                'total_page_views' => ['value' => $this->faker->numberBetween(0, 1000), 'previous' => null],
                'unique_visitors' => ['value' => $this->faker->numberBetween(0, 500), 'previous' => null],
                'avg_time_on_page' => ['value' => $this->faker->randomFloat(1, 0, 300), 'previous' => null],
                'cta_clicks' => [
                    'columns' => ['target', 'count'],
                    'rows' => [
                        ['target' => 'github', 'count' => 0],
                        ['target' => 'demo', 'count' => 0],
                        ['target' => 'cv', 'count' => 0],
                        ['target' => 'feedback', 'count' => 0],
                        ['target' => 'language', 'count' => 0],
                        ['target' => 'theme', 'count' => 0],
                    ],
                ],
            ],
            'generated_at' => $this->faker->dateTime(),
        ];
    }
}
