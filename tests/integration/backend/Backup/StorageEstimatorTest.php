<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Backup;

use App\Features\Backup\Infra\Storage\StorageEstimator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Support\Fakes\FakeAssetPathCollector;
use Tests\Support\Fakes\FakeBatchTableReader;

use function sys_get_temp_dir;

function backupEstimatorFixture(array $paths, array $sizes): StorageEstimator
{
    return new StorageEstimator(
        assetCollector: new FakeAssetPathCollector($paths),
        assetsDisk: fakeAssetsDisk($paths, $sizes),
        stagingPath: sys_get_temp_dir(),
    );
}

function fakeAssetsDisk(array $paths, array $sizes): Filesystem
{
    $disk = Storage::fake('rustfs');

    foreach ($paths as $index => $path) {
        $disk->put($path, str_repeat('x', $sizes[$index]));
    }

    return $disk;
}

describe('StorageEstimator', function (): void {
    it('sums the serialized JSONL size of the streamed rows', function (): void {
        $estimator = backupEstimatorFixture(paths: [], sizes: []);

        $bytes = $estimator->estimateDataSize(['settings'], new FakeBatchTableReader([
            ['a' => 'x'],
            ['b' => 'yy'],
        ]));

        expect($bytes)->toBe(strlen('{"a":"x"}') + 1 + strlen('{"b":"yy"}') + 1);
    });

    it('rejects an empty table list', function (): void {
        $estimator = backupEstimatorFixture(paths: [], sizes: []);

        expect(fn () => $estimator->estimateDataSize([], new FakeBatchTableReader([])))
            ->toThrow(InvalidArgumentException::class, 'estimateDataSize requires at least one table name.');
    });

    it('sums the bucket object sizes', function (): void {
        $estimator = backupEstimatorFixture(
            paths: ['blog/a.png', 'projects/b.png'],
            sizes: [100, 250],
        );

        expect($estimator->getAssetSize('portfolio'))->toBe(350);
    });

    it('applies the 2× rule', function (): void {
        $estimator = backupEstimatorFixture(
            paths: ['blog/a.png'],
            sizes: [40],
        );

        $reader = new FakeBatchTableReader([
            ['name' => 'Portfolio'],
        ]);

        // data = 18 bytes (`{"name":"Portfolio"}` + newline), assets = 40
        // → required = 2 × 58 = 116. The real temp dir free space is
        // far above it, so the pre-flight passes…
        expect($estimator->hasEnoughSpace(['settings'], 'portfolio', $reader))->toBeTrue();

        // …and with an impossible staging path the available space
        // reports 0 → the same 116-byte requirement fails.
        $estimator = new StorageEstimator(
            assetCollector: new FakeAssetPathCollector(['blog/a.png']),
            assetsDisk: fakeAssetsDisk(['blog/a.png'], [40]),
            stagingPath: '/nonexistent-staging-dir',
        );

        expect($estimator->hasEnoughSpace(['settings'], 'portfolio', $reader))->toBeFalse();
    });

    it('reports zero available space for a missing staging path', function (): void {
        $estimator = backupEstimatorFixture(paths: [], sizes: []);

        $missing = new StorageEstimator(
            assetCollector: new FakeAssetPathCollector([]),
            assetsDisk: Storage::fake('rustfs'),
            stagingPath: '/nonexistent-staging-dir',
        );

        expect($estimator->getAvailableSpace())->toBeGreaterThan(0)
            ->and($missing->getAvailableSpace())->toBe(0);
    });
});
