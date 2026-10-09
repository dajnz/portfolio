<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Blog\Infra\Models\ArticleTagModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `article_tags` (ADR-0029).
 */
final class ArticleTagModelFactory extends Factory
{
    protected $model = ArticleTagModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
        ];
    }
}
