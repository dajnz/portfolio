<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Portfolio\Infra\Models\ContactModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for the `contacts` table (B21).
 */
final class ContactModelFactory extends Factory
{
    protected $model = ContactModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'value' => $this->faker->userName(),
            'icon' => 'simple-icons:'.$this->faker->randomElement(['github', 'linkedin', 'telegram', 'twitter']),
            'include_in_resume' => true,
            'url' => $this->faker->url(),
            'sort_order' => 0,
        ];
    }
}
