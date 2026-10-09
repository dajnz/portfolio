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
            'alt_text' => null,
            'title' => null,
            'description' => null,
            'sort_order' => 0,
        ];
    }

    public function withLocaleMaps(string $locale, string $alt, string $title, string $description): static
    {
        return $this->state(function (array $attributes) use ($locale, $alt, $title, $description): array {
            $altMap = (array) ($attributes['alt_text'] ?? []);
            $titleMap = (array) ($attributes['title'] ?? []);
            $descriptionMap = (array) ($attributes['description'] ?? []);

            $altMap[$locale] = $alt;
            $titleMap[$locale] = $title;
            $descriptionMap[$locale] = $description;

            return [
                'alt_text' => $altMap,
                'title' => $titleMap,
                'description' => $descriptionMap,
            ];
        });
    }
}
