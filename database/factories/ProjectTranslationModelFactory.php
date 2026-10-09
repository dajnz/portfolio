<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectTranslationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ProjectTranslationModelFactory extends Factory
{
    protected $model = ProjectTranslationModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => ProjectModel::factory(),
            'locale' => $this->faker->randomElement(['en', 'ru']),
            'title' => $this->faker->sentence(4),
            'brief_description' => $this->faker->paragraph(),
            'full_description' => $this->faker->paragraphs(2, true),
            'problem' => $this->faker->paragraph(),
            'solution' => $this->faker->paragraph(),
        ];
    }

    public function forLocale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }
}
