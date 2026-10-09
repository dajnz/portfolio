<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Blog\Domain\ValueObjects\ReadingTime;
use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The `reading_time_minutes` follows the domain formula.
 */
final class ArticleTranslationModelFactory extends Factory
{
    protected $model = ArticleTranslationModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = $this->faker->paragraphs(3, true);

        return [
            'article_id' => ArticleModel::factory(),
            'locale' => $this->faker->randomElement(['en', 'ru']),
            'title' => $this->faker->sentence(4),
            'short_description' => $this->faker->paragraph(),
            'content' => $content,
            'reading_time_minutes' => ReadingTime::fromText($content)->toMinutes(),
        ];
    }

    public function forLocale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }
}
