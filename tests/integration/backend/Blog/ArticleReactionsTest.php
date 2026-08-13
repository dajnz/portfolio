<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Blog;

use App\Features\Blog\Infra\Models\ArticleReactionModel;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Infra\Models\FeatureFlagModel;
use App\Features\Settings\Infra\Models\SettingModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\SeedsBlogData;

uses(SeedsBlogData::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');

    // 2 items per page makes the pagination assertions cheap.
    SettingModel::query()->updateOrCreate(
        ['key' => 'articles_per_page'],
        ['value' => '2', 'type' => 'integer'],
    );
});

describe('article reactions', function (): void {
    beforeEach(function (): void {
        FeatureFlagModel::query()->updateOrCreate(
            ['key' => FeatureFlagName::SocialReactions->value],
            ['enabled' => true, 'description' => ''],
        );
    });

    it('404s when the feature flag is off', function (): void {
        FeatureFlagModel::query()->where('key', FeatureFlagName::SocialReactions->value)->update(['enabled' => false]);
        $article = $this->publicBlogArticle();

        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'like'])
            ->assertNotFound();
    });

    it('422s on an invalid reaction type', function (): void {
        $article = $this->publicBlogArticle();

        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'meh'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reaction');
    });

    it('404s for unknown or unpublished articles', function (): void {
        $this->postJson('/api/blog/999/reaction', ['reaction' => 'like'])->assertNotFound();

        $draft = $this->publicBlogArticle(['is_published' => false, 'published_at' => null]);
        $this->postJson("/api/blog/{$draft->id}/reaction", ['reaction' => 'like'])->assertNotFound();
    });

    it('inserts, toggles off on repeat and switches on the opposite reaction', function (): void {
        $article = $this->publicBlogArticle();

        $like = $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'like'], ['User-Agent' => 'BlogVisitor/1']);
        $like->assertOk()
            ->assertJsonPath('data.reaction', 'like')
            ->assertJsonPath('data.likes', 1)
            ->assertJsonPath('data.dislikes', 0);

        // The same visitor undoes the reaction.
        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'like'], ['User-Agent' => 'BlogVisitor/1'])
            ->assertJsonPath('data.reaction', null)
            ->assertJsonPath('data.likes', 0);

        // Switching to dislike keeps a single row.
        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'dislike'], ['User-Agent' => 'BlogVisitor/1'])
            ->assertJsonPath('data.reaction', 'dislike')
            ->assertJsonPath('data.dislikes', 1)
            ->assertJsonPath('data.likes', 0);

        $this->assertSame(1, ArticleReactionModel::query()->where('article_id', $article->id)->count());
    });

    it('keeps reactions per fingerprint', function (): void {
        $article = $this->publicBlogArticle();

        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'like'], ['User-Agent' => 'VisitorA/1']);
        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'like'], ['User-Agent' => 'VisitorB/1']);
        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'dislike'], ['User-Agent' => 'VisitorC/1']);

        $this->assertSame(3, ArticleReactionModel::query()->where('article_id', $article->id)->count());

        $this->withHeader('User-Agent', 'VisitorA/1')->get('/blog/'.$article->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('reactions.user_reaction', 'like')
                ->where('reactions.likes', 2)
                ->where('reactions.dislikes', 1));
    });

    it('reports null reactions while the flag is off', function (): void {
        FeatureFlagModel::query()->where('key', FeatureFlagName::SocialReactions->value)->update(['enabled' => false]);
        $article = $this->publicBlogArticle();

        $this->get('/blog/'.$article->slug)
            ->assertInertia(fn (Assert $page) => $page->where('reactions', null));
    });
});
