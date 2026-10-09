<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Features\Backup\Domain\Enums\BackupStatus;
use App\Features\Backup\Infra\Models\BackupModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test factory for `backups` (ADR-0029). The default is a completed
 * record carrying the full archive stamp (the entity's completed-row
 * shape) — pass `status`/`path`/`size_bytes` explicitly for
 * pending/processing/failed rows.
 */
final class BackupModelFactory extends Factory
{
    protected $model = BackupModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timestamp = $this->faker->dateTimeBetween('-1 year');

        return [
            'filename' => 'backup-'.$timestamp->format('Y-m-d-His').'-k1.zip',
            'size_bytes' => $this->faker->numberBetween(1000, 1000000),
            'path' => 'backup-'.$timestamp->format('Y-m-d-His').'-k1.zip',
            'record_counts' => ['settings' => 16, 'feedback' => 2],
            'assets_count' => 0,
            'assets_size_bytes' => 0,
            'status' => BackupStatus::Completed->value,
            'encryption_key_id' => null,
            'completed_at' => fn (array $attributes): mixed => $attributes['status'] === BackupStatus::Completed->value ? $timestamp : null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }
}
