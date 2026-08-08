<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Experience\Infra\Models\TimelineItemModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `experience_timeline_items` (B21).
 */
final class TimelineItemModelFactory extends Factory
{
    protected $model = TimelineItemModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'started_at' => $this->faker->dateTimeBetween('-10 years', '-1 year')->format('Y-m-d'),
            'ended_at' => null,
            'is_published' => true,
            'sort_order' => 0,
            'project_id' => null,
            'include_in_resume' => true,
            'external_project_name' => null,
        ];
    }

    public function published(bool $published = true): static
    {
        return $this->state(['is_published' => $published]);
    }

    public function current(): static
    {
        return $this->state(['ended_at' => null]);
    }

    public function trashed(): static
    {
        return $this->state(['deleted_at' => now()]);
    }
}
