<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Portfolio\Infra\Models\ContactModel;
use App\Features\Portfolio\Infra\Models\ContactTranslationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `contact_translations` (ADR-0029, both locales covered
 * via the `forLocale()` state).
 */
final class ContactTranslationModelFactory extends Factory
{
    protected $model = ContactTranslationModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => ContactModel::factory(),
            'locale' => $this->faker->randomElement(['en', 'ru']),
            'name' => $this->faker->unique()->words(2, true),
        ];
    }

    public function forLocale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }
}
