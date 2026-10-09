<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Portfolio\Infra\Models\FeedbackModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for the `feedback` table (ADR-0029). The identifiers are
 * opaque keyed HMAC digests (ADR-0017) — tests pass explicit values where the
 * anonymity invariants matter.
 */
final class FeedbackModelFactory extends Factory
{
    protected $model = FeedbackModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_name' => $this->faker->name(),
            'sender_email' => $this->faker->safeEmail(),
            'message' => $this->faker->sentence(20),
            'ip_address' => str_repeat('ab', 32),
            'fingerprint' => str_repeat('cd', 32),
            'is_read' => false,
            'is_forwarded' => false,
        ];
    }
}
