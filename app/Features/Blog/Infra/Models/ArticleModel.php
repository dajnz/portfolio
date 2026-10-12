<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Models;

use Database\Factories\ArticleModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent model for `blog_articles` — DB-table code only: the
 * fillable columns, the relations and the casts. The `sitemap:xml`
 * invalidation is NOT a model concern — the write use cases orchestrate
 * it via the shared `SitemapInvalidatorInterface` (the app-layer
 * abstraction).
 *
 * The RustFS asset deletion (the lead image) is NOT a model concern —
 * the force-delete flows orchestrate it in the use cases through the
 * shared asset cleaner.
 *
 * @property int $id
 * @property string $slug
 * @property string|null $lead_image
 * @property array<string, string>|null $lead_image_alt
 * @property bool $is_published
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, ArticleTranslationModel> $translations
 * @property \Illuminate\Support\Collection<int, ArticleTagModel> $articleTags
 * @property \Illuminate\Support\Collection<int, \App\Features\Shared\Infra\Models\SkillModel> $skillTags
 */
final class ArticleModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'blog_articles';

    protected $fillable = [
        'slug',
        'lead_image',
        'lead_image_alt',
        'is_published',
        'published_at',
    ];

    /** @return HasMany<ArticleTranslationModel, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ArticleTranslationModel::class, 'article_id');
    }

    /** @return BelongsToMany<ArticleTagModel, $this> */
    public function articleTags(): BelongsToMany
    {
        return $this->belongsToMany(ArticleTagModel::class, 'article_tag_pivot', 'article_id', 'tag_id');
    }

    /** @return BelongsToMany<\App\Features\Shared\Infra\Models\SkillModel, $this> */
    public function skillTags(): BelongsToMany
    {
        return $this->belongsToMany(\App\Features\Shared\Infra\Models\SkillModel::class, 'article_skill_pivot', 'article_id', 'skill_id');
    }

    /**
     * @param  Builder<ArticleModel>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    protected static function newFactory(): Factory
    {
        return ArticleModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'lead_image_alt' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
