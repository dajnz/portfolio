<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Blog\Infra\Models\ArticleTagModel;
use App\Features\Blog\Infra\Models\ArticleTagTranslationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

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
