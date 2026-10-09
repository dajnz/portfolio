<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Models;

use Database\Factories\BackupEncryptionKeyModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `backup_encryption_keys`. `key_value` holds the
 * **encrypted** raw key (Crypt, under APP_KEY) — the mapper encrypts on
 * write and decrypts on read, so the entity always carries the raw
 * value.
 *
 * @property int $id
 * @property string $name
 * @property string $key_value
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class BackupEncryptionKeyModel extends Model
{
    use HasFactory;

    protected $table = 'backup_encryption_keys';

    protected $fillable = [
        'name',
        'key_value',
        'is_active',
    ];

    protected static function newFactory(): Factory
    {
        return BackupEncryptionKeyModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
