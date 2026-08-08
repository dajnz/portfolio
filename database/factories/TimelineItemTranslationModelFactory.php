<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Experience\Infra\Models\TimelineItemModel;
use App\Features\Experience\Infra\Models\TimelineItemTranslationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `experience_timeline_item_translations` (B21, both
 * locales covered via the `forLocale()` state).
 */
final class TimelineItemTranslationModelFactory extends Factory
{
    protected $model = TimelineItemTranslationModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => TimelineItemModel::factory(),
            'locale' => $this->faker->randomElement(['en', 'ru']),
            'position' => $this->faker->jobTitle(),
            'company_name' => $this->faker->company(),
            'company_country' => $this->faker->country(),
            'short_description' => $this->faker->paragraph(),
            'achievements' => null,
        ];
    }

    public function forLocale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }
}
