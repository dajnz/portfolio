<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Backup;

use App\Features\Backup\Domain\ValueObjects\BackupContentSummary;
use InvalidArgumentException;

/**
 * The archive content stamp VO (database.md:573-579): nullable fields
 * validated when present (non-negative sizes/counts, per-table record
 * counts) and the all-or-nothing invariant — a summary is either
 * fully empty (the pending row) or fully populated (stamped by
 * `Backup::complete()`).
 */
describe('BackupContentSummary', function (): void {
    it('exposes the populated fields and reports completeness', function (): void {
        $summary = new BackupContentSummary(
            sizeBytes: 52428800,
            assetsCount: 2,
            assetsSizeBytes: 512000,
            recordCounts: ['about_info' => 1, 'settings' => 14],
        );

        expect($summary->toSizeBytes())->toBe(52428800)
            ->and($summary->toAssetsCount())->toBe(2)
            ->and($summary->toAssetsSizeBytes())->toBe(512000)
            ->and($summary->toRecordCounts())->toBe(['about_info' => 1, 'settings' => 14])
            ->and($summary->isComplete())->toBeTrue();
    });

    it('treats an all-null summary as the pending shape', function (): void {
        $summary = new BackupContentSummary(null, null, null, null);

        expect($summary->toSizeBytes())->toBeNull()
            ->and($summary->toAssetsCount())->toBeNull()
            ->and($summary->toAssetsSizeBytes())->toBeNull()
            ->and($summary->toRecordCounts())->toBeNull()
            ->and($summary->isComplete())->toBeFalse();
    });

    it('rejects negative sizes and counts', function (): void {
        expect(fn () => new BackupContentSummary(
            sizeBytes: -1,
            assetsCount: null,
            assetsSizeBytes: null,
            recordCounts: null,
        ))->toThrow(InvalidArgumentException::class, 'Backup size must not be negative.');

        expect(fn () => new BackupContentSummary(
            sizeBytes: 1,
            assetsCount: -1,
            assetsSizeBytes: null,
            recordCounts: null,
        ))->toThrow(InvalidArgumentException::class, 'Backup assets count must not be negative.');

        expect(fn () => new BackupContentSummary(
            sizeBytes: 1,
            assetsCount: 0,
            assetsSizeBytes: -1,
            recordCounts: null,
        ))->toThrow(InvalidArgumentException::class, 'Backup assets size must not be negative.');
    });

    it('rejects malformed record counts', function (): void {
        expect(fn () => new BackupContentSummary(
            sizeBytes: 1,
            assetsCount: 0,
            assetsSizeBytes: 0,
            recordCounts: ['about_info' => -1],
        ))->toThrow(InvalidArgumentException::class, 'Backup record counts must be non-negative integers per table.');

        expect(fn () => new BackupContentSummary(
            sizeBytes: 1,
            assetsCount: 0,
            assetsSizeBytes: 0,
            recordCounts: ['' => 1],
        ))->toThrow(InvalidArgumentException::class, 'Backup record counts must be non-negative integers per table.');
    });

    it('rejects partially populated summaries', function (): void {
        expect(fn () => new BackupContentSummary(
            sizeBytes: 52428800,
            assetsCount: null,
            assetsSizeBytes: null,
            recordCounts: null,
        ))->toThrow(InvalidArgumentException::class, 'Backup content summary must be either empty or fully populated.');

        expect(fn () => new BackupContentSummary(
            sizeBytes: 52428800,
            assetsCount: 2,
            assetsSizeBytes: 512000,
            recordCounts: null,
        ))->toThrow(InvalidArgumentException::class, 'Backup content summary must be either empty or fully populated.');
    });
});
