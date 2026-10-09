<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Analytics\Infra\Models\AnalyticsEventModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The `ip_address` is an opaque keyed HMAC digest — tests pass explicit
 * values where the anonymity invariants matter.
 */
final class AnalyticsEventModelFactory extends Factory
{
    protected $model = AnalyticsEventModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_type' => $this->faker->randomElement([
                'page_view',
                'time_on_page',
                'cta_click_cv',
                'article_read',
            ]),
            'session_id' => $this->faker->uuid(),
            'page_type' => 'landing',
            'page_entity_id' => null,
            'page_url' => $this->faker->url(),
            'metadata' => [
                'referrer' => null,
                'title' => $this->faker->sentence(4),
            ],
            'ip_address' => str_repeat('ab', 32),
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/126.0',
        ];
    }
}
