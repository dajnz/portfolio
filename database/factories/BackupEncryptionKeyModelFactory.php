<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Backup\Infra\Models\BackupEncryptionKeyModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * Test factory for `backup_encryption_keys` (B21). `key_value` is
 * stored encrypted at rest (Crypt) — the factory encrypts a fixed
 * 44-char base64 key so the mapper's round-trip works in tests.
 */
final class BackupEncryptionKeyModelFactory extends Factory
{
    protected $model = BackupEncryptionKeyModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // base64 of the 32 ASCII bytes "0123456789abcdef0123456789abcdef" —
        // a deterministic, entity-valid raw key.
        return [
            'name' => 'test-key',
            'key_value' => Crypt::encryptString('MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY='),
            'is_active' => false,
        ];
    }
}
