<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Shared\Infra\Models\SkillModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillModel>
 */
final class SkillModelFactory extends Factory
{
    protected $model = SkillModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'skill_group_id' => SkillGroupModelFactory::new(),
            'name' => $this->faker->unique()->words(2, true),
            'icon' => $this->faker->randomElement(['devicon:laravel', 'devicon:php', 'devicon:vuejs', 'devicon:typescript', null]),
            'proficiency' => $this->faker->numberBetween(1, 5),
            'include_in_resume' => true,
            'sort_order' => 0,
        ];
    }
}
