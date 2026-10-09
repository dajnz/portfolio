<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Auth\Infra\Models\AdminModel;
use App\Features\Auth\Domain\ValueObjects\RecoveryCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * The password is passed plaintext — the model's `hashed` cast bcrypts
 * it on write.
 *
 * Never used by `DatabaseSeeder` — factories are test-only.
 */
final class AdminFactory extends Factory
{
    protected $model = AdminModel::class;

    public function definition(): array
    {
        return [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'login' => 'admin',
            'password' => 'AdminPassword123!',
            'github_id' => null,
            'github_token' => null,
            'totp_secret' => null,
            'totp_recovery_codes' => null,
            'totp_enabled' => false,
            'totp_failed_attempts' => 0,
            'totp_locked_until' => null,
            'session_version' => 0,
        ];
    }

    /**
     * Provisions TOTP with the given plaintext base32 secret and (optional)
     * display recovery codes.
     *
     * @param  string[]  $recoveryCodes
     */
    public function withTotp(string $secret, array $recoveryCodes = []): static
    {
        return $this->state([
            'totp_enabled' => true,
            'totp_secret' => Crypt::encryptString($secret),
            'totp_recovery_codes' => array_map(
                fn (string $code): array => [
                    'hash' => Hash::make(RecoveryCode::normalize($code)),
                    'used' => false,
                ],
                $recoveryCodes,
            ),
        ]);
    }
}
