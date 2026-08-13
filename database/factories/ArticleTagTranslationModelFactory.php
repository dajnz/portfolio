<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Blog\Infra\Models\ArticleTagModel;
use App\Features\Blog\Infra\Models\ArticleTagTranslationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `article_tag_translations` (B21, both locales
 * covered via the `forLocale()` state).
 */
final class ArticleTagTranslationModelFactory extends Factory
{
    protected $model = ArticleTagTranslationModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_tag_id' => ArticleTagModel::factory(),
            'locale' => $this->faker->randomElement(['en', 'ru']),
            'name' => $this->faker->word(),
        ];
    }

    public function forLocale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }
}
