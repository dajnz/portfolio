<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillGroupTranslationModel>
 */
final class SkillGroupTranslationModelFactory extends Factory
{
    protected $model = SkillGroupTranslationModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'skill_group_id' => SkillGroupModel::factory(),
            'locale' => $this->faker->randomElement(['en', 'ru']),
            'name' => $this->faker->unique()->words(2, true),
        ];
    }
}
