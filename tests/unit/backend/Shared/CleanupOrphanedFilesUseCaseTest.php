<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Shared;

use App\Features\Shared\App\UseCases\CleanupOrphanedFilesUseCase;
use Tests\Support\Fakes\FakeAssetPathProvider;
use Tests\Support\Fakes\FakeAssetStorage;

describe('CleanupOrphanedFilesUseCase', function (): void {
    it('deletes every unreferenced object', function (): void {
        $storage = new FakeAssetStorage(['keep.jpg', 'orphan-1.jpg', 'orphan-2.jpg']);
        $provider = new FakeAssetPathProvider(['keep.jpg']);

        $result = (new CleanupOrphanedFilesUseCase($storage, [$provider]))->cleanup(false);

        expect($result->dryRun)->toBeFalse()
            ->and($result->orphanPaths)->toBe(['orphan-1.jpg', 'orphan-2.jpg'])
            ->and($storage->deletedBatches)->toBe([['orphan-1.jpg', 'orphan-2.jpg']]);
    });

    it('reports the orphans without deleting on a dry run', function (): void {
        $storage = new FakeAssetStorage(['keep.jpg', 'orphan.jpg']);
        $provider = new FakeAssetPathProvider(['keep.jpg']);

        $result = (new CleanupOrphanedFilesUseCase($storage, [$provider]))->cleanup(true);

        expect($result->dryRun)->toBeTrue()
            ->and($result->orphanPaths)->toBe(['orphan.jpg'])
            ->and($storage->deletedBatches)->toBe([]);
    });

    it('unions the referenced paths of every provider', function (): void {
        $storage = new FakeAssetStorage(['a.jpg', 'b.jpg', 'c.jpg']);
        $providers = [
            new FakeAssetPathProvider(['a.jpg']),
            new FakeAssetPathProvider(['b.jpg', 'a.jpg']),
        ];

        $result = (new CleanupOrphanedFilesUseCase($storage, $providers))->cleanup(true);

        expect($result->orphanPaths)->toBe(['c.jpg']);
    });
});
