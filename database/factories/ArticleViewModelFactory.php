<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleViewModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `article_views` (ADR-0029 — the counter table).
 */
final class ArticleViewModelFactory extends Factory
{
    protected $model = ArticleViewModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => ArticleModel::factory(),
            'views' => $this->faker->numberBetween(0, 1000),
        ];
    }
}
