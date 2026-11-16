<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectScreenshotModel;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ProjectScreenshotModelFactory extends Factory
{
    protected $model = ProjectScreenshotModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => ProjectModel::factory(),
            'image_path' => 'projects/'.$this->faker->unique()->md5().'.png',
            'title' => null,
            'description' => null,
            'sort_order' => 0,
        ];
    }

    public function withLocaleMaps(string $locale, string $title, string $description): static
    {
        return $this->state(function (array $attributes) use ($locale, $title, $description): array {
            $titleMap = (array) ($attributes['title'] ?? []);
            $descriptionMap = (array) ($attributes['description'] ?? []);

            $titleMap[$locale] = $title;
            $descriptionMap[$locale] = $description;

            return [
                'title' => $titleMap,
                'description' => $descriptionMap,
            ];
        });
    }
}
