<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Blog\Infra\Models\ArticleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ArticleModelFactory extends Factory
{
    protected $model = ArticleModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(3),
            'lead_image' => null,
            'lead_image_alt' => null,
            'is_published' => false,
            'published_at' => null,
        ];
    }

    public function published(bool $published = true): static
    {
        return $this->state(fn (): array => [
            'is_published' => $published,
            'published_at' => $published ? now() : null,
        ]);
    }

    public function trashed(): static
    {
        return $this->state(['deleted_at' => now()]);
    }
}
