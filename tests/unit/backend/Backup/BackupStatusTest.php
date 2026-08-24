<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Backup;

use App\Features\Backup\Domain\Enums\BackupStatus;

/**
 * The backup lifecycle states (backup.md:151, database.md:580): the
 * enum must carry exactly the four spec'd values — `pending`,
 * `processing`, `completed`, `failed`.
 */
describe('BackupStatus', function (): void {
    it('covers the four lifecycle states from the spec', function (): void {
        expect(array_column(BackupStatus::cases(), 'value'))->toBe([
            'pending',
            'processing',
            'completed',
            'failed',
        ]);
    });

    it('exposes the documented value strings', function (): void {
        expect(BackupStatus::Pending->value)->toBe('pending')
            ->and(BackupStatus::Processing->value)->toBe('processing')
            ->and(BackupStatus::Completed->value)->toBe('completed')
            ->and(BackupStatus::Failed->value)->toBe('failed');
    });
});
