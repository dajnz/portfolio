<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Database;

use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTagModel;
use App\Features\Experience\Infra\Models\TimelineItemModel;
use App\Features\Experience\App\Contracts\TimelineItemListQueryRepositoryInterface;
use App\Features\Portfolio\Infra\Models\ContactModel;
use App\Features\Projects\Infra\Models\ProjectDetailItemModel;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectScreenshotModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

describe('DemoContentSeeder', function (): void {
    beforeEach(function (): void {
        $this->seed(DemoContentSeeder::class);
    });

    it('seeds the full bilingual content dataset', function (): void {
        expect(ArticleModel::query()->count())->toBe(16)
            ->and(ArticleModel::query()->where('is_published', true)->count())->toBe(15)
            ->and(ProjectModel::query()->count())->toBe(16)
            ->and(ProjectModel::query()->where('is_published', true)->count())->toBe(15)
            ->and(ProjectModel::query()->where('is_featured', true)->count())->toBe(3)
            ->and(TimelineItemModel::query()->count())->toBe(10)
            ->and(TimelineItemModel::query()->whereNull('ended_at')->count())->toBe(1)
            ->and(SkillGroupModel::query()->count())->toBe(6)
            ->and(SkillModel::query()->count())->toBe(29)
            ->and(ArticleTagModel::query()->count())->toBe(9)
            ->and(ContactModel::query()->count())->toBe(5);
    });

    it('translates every content row into both locales', function (): void {
        $published = ArticleModel::query()->where('is_published', true)->count();

        expect($published)->toBe(15)
            ->and(DB::table('blog_article_translations')->count())->toBe(16 * 2)
            ->and(DB::table('project_translations')->count())->toBe(16 * 2)
            ->and(DB::table('experience_timeline_item_translations')->count())->toBe(10 * 2)
            ->and(DB::table('contact_translations')->count())->toBe(5 * 2)
            ->and(DB::table('skill_group_translations')->count())->toBe(6 * 2)
            ->and(DB::table('article_tag_translations')->count())->toBe(9 * 2);
    });

    it('links the five newest articles to related posts', function (): void {
        $newest = [
            'designing-bilingual-content-models',
            'postgres-full-text-search-at-scale',
            'keeping-a-vue-performance-budget',
            'queue-reliability-patterns',
            'testing-domain-logic-without-the-framework',
        ];

        $ids = ArticleModel::query()->whereIn('slug', $newest)->pluck('id', 'slug');

        expect($ids)->toHaveCount(5)
            ->and(DB::table('related_articles')->count())->toBe(10);

        foreach ($newest as $slug) {
            expect(DB::table('related_articles')->where('article_id', $ids[$slug])->count())->toBe(2);
        }
    });

    it('seeds project engineering details and screenshots', function (): void {
        expect(ProjectDetailItemModel::query()->count())->toBe(7)
            ->and(ProjectScreenshotModel::query()->count())->toBe(4);
    });

    it('produces experience items readable through the public repository', function (): void {
        $items = app(TimelineItemListQueryRepositoryInterface::class)->findPublicPage(20, null, null);

        expect($items)->toHaveCount(10);
    });

    it('refuses to run in production', function (): void {
        $this->app['env'] = 'production';

        try {
            (new DemoContentSeeder())->run();
            $this->fail('DemoContentSeeder should refuse to run in production.');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toContain('production');
        } finally {
            $this->app['env'] = 'testing';
        }
    });
});
