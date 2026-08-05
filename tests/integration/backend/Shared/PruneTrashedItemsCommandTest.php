<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Settings\Infra\Models\SettingModel;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    SettingModel::query()->updateOrCreate(
        ['key' => 'trash_retention_days'],
        ['value' => '30', 'type' => 'integer'],
    );
});

/**
 * @return array{old: int, recent: int, live: int}
 */
function seedTrashedItems(): array
{
    $id = fn (string $slug, ?string $deletedAt): int => DB::table('blog_articles')->insertGetId([
        'slug' => $slug,
        'is_published' => false,
        'deleted_at' => $deletedAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'old' => $id('old-article', now()->subDays(40)->toDateTimeString()),
        'recent' => $id('recent-article', now()->subDays(5)->toDateTimeString()),
        'live' => $id('live-article', null),
    ];
}

describe('PruneTrashedItemsCommand', function (): void {
    it('hard-deletes items trashed beyond the retention period across all three tables', function (): void {
        seedTrashedItems();

        DB::table('projects')->insert([
            'slug' => 'old-project', 'is_published' => false, 'deleted_at' => now()->subDays(40)->toDateTimeString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('experience_timeline_items')->insert([
            'started_at' => now()->subYear()->toDateString(), 'is_published' => false,
            'deleted_at' => now()->subDays(40)->toDateTimeString(), 'sort_order' => 1,
        ]);

        $this->artisan('app:prune-trashed-items')->assertSuccessful();

        expect(DB::table('blog_articles')->where('slug', 'old-article')->exists())->toBeFalse()
            ->and(DB::table('blog_articles')->where('slug', 'recent-article')->exists())->toBeTrue()
            ->and(DB::table('blog_articles')->where('slug', 'live-article')->exists())->toBeTrue()
            ->and(DB::table('projects')->where('slug', 'old-project')->exists())->toBeFalse()
            ->and(DB::table('experience_timeline_items')->count())->toBe(0);
    });

    it('uses the trash_retention_days setting as the cutoff', function (): void {
        seedTrashedItems();
        SettingModel::query()->where('key', 'trash_retention_days')->update(['value' => '3']);

        $this->artisan('app:prune-trashed-items')->assertSuccessful();

        expect(DB::table('blog_articles')->where('slug', 'recent-article')->exists())->toBeFalse()
            ->and(DB::table('blog_articles')->where('slug', 'live-article')->exists())->toBeTrue();
    });

    it('does nothing when trash_retention_days is 0 (pruning disabled)', function (): void {
        seedTrashedItems();
        SettingModel::query()->where('key', 'trash_retention_days')->update(['value' => '0']);

        $this->artisan('app:prune-trashed-items')
            ->expectsOutputToContain('Trash pruning is disabled')
            ->assertSuccessful();

        expect(DB::table('blog_articles')->count())->toBe(3);
    });
});
