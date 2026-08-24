<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Mappers;

use App\Features\Backup\Domain\Entities\EncryptionKey;
use App\Features\Backup\Infra\Models\BackupEncryptionKeyModel;
use Illuminate\Support\Facades\Crypt;

use function is_string;

/**
 * Converts between `backup_encryption_keys` rows and the `EncryptionKey`
 * entity. The raw key is encrypted at rest via `Crypt` (under APP_KEY,
 * backup.md:495-497): `toRow` encrypts before the row is stored,
 * `toEntity` decrypts so the entity always carries the raw value.
 */
final class BackupEncryptionKeyMapper
{
    /**
     * @return array{
     *     name: string,
     *     key_value: string,
     *     is_active: bool,
     * }
     */
    public function toRow(EncryptionKey $key): array
    {
        return [
            'name' => $key->toName(),
            'key_value' => Crypt::encryptString($key->toKeyValue()),
            'is_active' => $key->isActive(),
        ];
    }

    public function toEntity(BackupEncryptionKeyModel $model): EncryptionKey
    {
        $row = $model->toArray();
        $row['key_value'] = $this->decryptKeyValue($row['key_value']);

        return EncryptionKey::fromRow($row);
    }

    private function decryptKeyValue(mixed $ciphertext): string
    {
        if (! is_string($ciphertext) || $ciphertext === '') {
            return '';
        }

        return Crypt::decryptString($ciphertext);
    }
}
