<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Projects\Infra\Models\ProjectModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

final class ProjectModelFactory extends Factory
{
    protected $model = ProjectModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(3),
            'preview_image' => null,
            'preview_image_alt' => null,
            'github_url' => null,
            'live_demo_url' => null,
            'tech_writeup_article_id' => null,
            'is_featured' => false,
            'featured_sort_order' => null,
            'is_published' => false,
            'published_at' => null,
            'tech_tags_count' => 0,
        ];
    }

    public function published(bool $published = true): static
    {
        return $this->state(fn (): array => [
            'is_published' => $published,
            'published_at' => $published ? Date::now() : null,
        ]);
    }

    public function featured(bool $featured = true, ?int $sortOrder = null): static
    {
        return $this->state(fn (): array => [
            'is_featured' => $featured,
            'featured_sort_order' => $featured ? ($sortOrder ?? 0) : null,
        ]);
    }

    public function trashed(): static
    {
        return $this->state(['deleted_at' => Date::now()]);
    }
}
