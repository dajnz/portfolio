<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Projects\Infra\Models\ProjectDetailItemModel;
use App\Features\Projects\Infra\Models\ProjectModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `project_detail_items` (B21).
 */
final class ProjectDetailItemModelFactory extends Factory
{
    protected $model = ProjectDetailItemModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => ProjectModel::factory(),
            'icon' => $this->faker->randomElement([
                'heroicons:bolt',
                'heroicons:server',
                'heroicons:shield-check',
                'heroicons:chart-bar',
            ]),
            'sort_order' => 0,
        ];
    }
}
