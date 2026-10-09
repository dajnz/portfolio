<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Models;

use Database\Factories\BackupModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent model for `backups` (database.md:567-587): one encrypted
 * archive row. The NOT NULL `path`/`size_bytes` placeholders of
 * pending rows are `''`/`0` (ADR-0018) — the entity maps them back to
 * null. `deleted_at` soft-deletes the record; the RustFS archive
 * hard-delete is the use case's concern (backup.md:157,435).
 *
 * @property int $id
 * @property string $filename
 * @property int $size_bytes
 * @property string $path
 * @property array<string, int>|null $record_counts
 * @property int|null $assets_count
 * @property int|null $assets_size_bytes
 * @property string $status
 * @property int|null $encryption_key_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class BackupModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'backups';

    protected $fillable = [
        'filename',
        'size_bytes',
        'path',
        'record_counts',
        'assets_count',
        'assets_size_bytes',
        'status',
        'encryption_key_id',
        // `created_at` must be fillable: the mapper serializes the
        // entity's own `created_at` (the row's `completed_at` shares its
        // instant — an import creates a completed row in one go), and an
        // Eloquent auto-stamp could land a second later, violating the
        // `BackupTimings` invariant (completion must not precede
        // creation).
        'created_at',
        'completed_at',
    ];

    protected static function newFactory(): Factory
    {
        return BackupModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'record_counts' => 'array',
            'assets_count' => 'integer',
            'assets_size_bytes' => 'integer',
            'encryption_key_id' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
