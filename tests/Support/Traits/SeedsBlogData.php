<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTagModel;
use App\Features\Blog\Infra\Models\ArticleTagTranslationModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillModel;
use App\Features\Shared\Infra\Persistence\Cursor;
use App\Features\Shared\Infra\Persistence\PublicCursorCodec;

/**
 * Shared fixtures for the public-blog test suites (step 8.2): the
 * published fully-translated article, the translated tag, the seeded
 * skill and the opaque-cursor encoder used by the infinite-scroll
 * assertions (the same codec the controller uses — the per-file
 * helper convention per B216).
 */
trait SeedsBlogData
{
    /**
     * Creates a PUBLISHED fully translated article with the given base
     * attributes.
     */
    protected function publicBlogArticle(array $attributes = [], array $translations = []): ArticleModel
    {
        $article = ArticleModel::factory()->published()->create($attributes);

        $defaults = [
            'en' => [
                'title' => 'Payments Gateway',
                'short_description' => 'A payment gateway article.',
                'content' => '## The problem'.PHP_EOL.'The article body in English with enough words.',
            ],
            'ru' => [
                'title' => 'Платёжный шлюз',
                'short_description' => 'Статья о платёжном шлюзе.',
                'content' => '## Проблема'.PHP_EOL.'Тело статьи на русском с достаточным количеством слов.',
            ],
        ];

        foreach (array_merge($defaults, $translations) as $locale => $fields) {
            ArticleTranslationModel::factory()
                ->forLocale($locale)
                ->create(['article_id' => $article->id] + $fields);
        }

        return $article;
    }

    /**
     * Creates a translated article tag and returns the model.
     */
    protected function publicBlogTag(string $slug = 'php', array $names = []): ArticleTagModel
    {
        $tag = ArticleTagModel::factory()->create(['slug' => $slug]);

        $defaults = ['en' => 'PHP', 'ru' => 'ПХП'];
        foreach (array_merge($defaults, $names) as $locale => $name) {
            ArticleTagTranslationModel::factory()->forLocale($locale)->create([
                'article_tag_id' => $tag->id,
                'name' => $name,
            ]);
        }

        return $tag;
    }

    /**
     * Seeds a skill in a group and returns its id.
     */
    protected function publicBlogSkillId(string $name = 'PHP', ?string $icon = 'devicon:php'): int
    {
        $group = SkillGroupModel::factory()->create();

        return SkillModel::factory()->create([
            'skill_group_id' => $group->id,
            'name' => $name,
            'icon' => $icon,
        ])->id;
    }

    /**
     * Encodes a cursor with the same codec the controller uses.
     */
    protected function encodedBlogCursor(int $id, string $sortValue): string
    {
        return app(PublicCursorCodec::class)->encode(new Cursor(id: $id, sortValue: $sortValue));
    }
}
