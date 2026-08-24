<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Backup;

use App\Features\Backup\Infra\Storage\StorageEstimator;
use Illuminate\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;
use Mockery;
use Tests\Support\Fakes\FakeAssetPathCollector;
use Tests\Support\Fakes\FakeBatchTableReader;

use function sys_get_temp_dir;

/**
 * The backup pre-flight estimator (backup.md:242-249, 378): data size
 * = summed serialized JSONL rows, asset size = summed bucket objects,
 * and the 2× rule — `hasEnoughSpace()` is false when the available
 * space is below `2 × (data + assets)` (backup.md:249).
 */

/**
 * @return array{0: StorageEstimator, 1: Filesystem}
 */
function backupEstimatorFixture(array $rows, array $paths, array $sizes): array
{
    $collector = new FakeAssetPathCollector($paths);
    $disk = Mockery::mock(Filesystem::class);

    foreach ($paths as $index => $path) {
        $disk->shouldReceive('size')->with($path)->andReturn($sizes[$index]);
    }

    $estimator = new StorageEstimator(
        assetCollector: $collector,
        assetsDisk: $disk,
        stagingPath: sys_get_temp_dir(),
    );

    return [$estimator, $disk];
}

describe('StorageEstimator', function (): void {
    it('sums the serialized JSONL size of the streamed rows', function (): void {
        [$estimator] = backupEstimatorFixture(
            rows: [['a' => 'x'], ['b' => 'yy']],
            paths: [],
            sizes: [],
        );

        $bytes = $estimator->estimateDataSize(['settings'], new FakeBatchTableReader([
            ['a' => 'x'],
            ['b' => 'yy'],
        ]));

        expect($bytes)->toBe(strlen('{"a":"x"}') + 1 + strlen('{"b":"yy"}') + 1);
    });

    it('rejects an empty table list', function (): void {
        [$estimator] = backupEstimatorFixture([], [], []);

        expect(fn () => $estimator->estimateDataSize([], new FakeBatchTableReader([])))
            ->toThrow(InvalidArgumentException::class, 'estimateDataSize requires at least one table name.');
    });

    it('sums the bucket object sizes', function (): void {
        [$estimator] = backupEstimatorFixture(
            rows: [],
            paths: ['blog/a.png', 'projects/b.png'],
            sizes: [100, 250],
        );

        expect($estimator->getAssetSize('portfolio'))->toBe(350);
    });

    it('applies the 2× rule', function (): void {
        [$estimator] = backupEstimatorFixture(
            rows: [],
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
            assetsDisk: Mockery::mock(Filesystem::class)
                ->shouldReceive('size')->with('blog/a.png')->andReturn(40)->getMock(),
            stagingPath: '/nonexistent-staging-dir',
        );

        expect($estimator->hasEnoughSpace(['settings'], 'portfolio', $reader))->toBeFalse();
    });

    it('reports zero available space for a missing staging path', function (): void {
        [$estimator] = backupEstimatorFixture([], [], []);

        $missing = new StorageEstimator(
            assetCollector: new FakeAssetPathCollector([]),
            assetsDisk: Mockery::mock(Filesystem::class),
            stagingPath: '/nonexistent-staging-dir',
        );

        expect($estimator->getAvailableSpace())->toBeGreaterThan(0)
            ->and($missing->getAvailableSpace())->toBe(0);
    });
});
