<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Portfolio\Infra\Models\AboutInfoModel;
use App\Features\Portfolio\Infra\Models\AboutInfoTranslationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

final class AboutInfoTranslationModelFactory extends Factory
{
    protected $model = AboutInfoTranslationModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'about_info_id' => AboutInfoModel::factory(),
            'locale' => $this->faker->randomElement(['en', 'ru']),
            'name' => $this->faker->name(),
            'title' => $this->faker->jobTitle(),
            'short_pitch' => $this->faker->sentence(12),
            'location' => $this->faker->city().', '.$this->faker->country(),
        ];
    }

    public function forLocale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }
}
