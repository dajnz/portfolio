<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Projects\Infra\Models\ProjectDetailItemModel;
use App\Features\Projects\Infra\Models\ProjectDetailItemTranslationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `project_detail_item_translations` (ADR-0029, both
 * locales covered via the `forLocale()` state).
 */
final class ProjectDetailItemTranslationModelFactory extends Factory
{
    protected $model = ProjectDetailItemTranslationModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'detail_item_id' => ProjectDetailItemModel::factory(),
            'locale' => $this->faker->randomElement(['en', 'ru']),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
        ];
    }

    public function forLocale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }
}
