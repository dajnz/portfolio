<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Shared\Infra\Models\SkillGroupModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillGroupModel>
 */
final class SkillGroupModelFactory extends Factory
{
    protected $model = SkillGroupModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sort_order' => 0,
            'icon' => $this->faker->randomElement(['heroicons:folder', 'heroicons:server', 'heroicons:device-phone-mobile', null]),
        ];
    }
}
