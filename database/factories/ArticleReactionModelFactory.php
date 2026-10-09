<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleReactionModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `article_reactions` (ADR-0029).
 */
final class ArticleReactionModelFactory extends Factory
{
    protected $model = ArticleReactionModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => ArticleModel::factory(),
            'fingerprint' => $this->faker->sha256(),
            'reaction' => $this->faker->randomElement(['like', 'dislike']),
        ];
    }
}
