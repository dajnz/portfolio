<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Models;

use App\Features\Shared\Infra\Models\SkillModel;
use App\Features\Shared\Infra\Services\SitemapService;
use Database\Factories\ProjectModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent model for `projects` (database.md:194-216) — DB-table code
 * only: the fillable columns, the relations, the casts and the sitemap
 * invalidation events. The `sitemap:xml` cache is invalidated whenever
 * a published project is saved or deleted (seo.md:219 — wired here per
 * ADR-0022): a save that leaves the project published, or flips
 * `is_published` (an unpublish must drop the sitemap entry too), and a
 * delete of a published project. The `deleted_at` timestamp is
 * soft-delete bookkeeping — the featured projects read model (Shared)
 * uses the same column.
 *
 * The RustFS asset deletion (preview image + screenshots) is NOT a
 * model concern — the force-delete flows orchestrate it in the use
 * cases through the shared asset cleaner.
 *
 * @property int $id
 * @property string $slug
 * @property string|null $preview_image
 * @property array<string, string>|null $preview_image_alt
 * @property string|null $github_url
 * @property string|null $live_demo_url
 * @property int|null $tech_writeup_article_id
 * @property bool $is_featured
 * @property int|null $featured_sort_order
 * @property bool $is_published
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int $tech_tags_count
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Collection<int, ProjectTranslationModel> $translations
 * @property \Illuminate\Support\Collection<int, ProjectScreenshotModel> $screenshots
 * @property \Illuminate\Support\Collection<int, ProjectDetailItemModel> $detailItems
 * @property \Illuminate\Support\Collection<int, SkillModel> $techTags
 */
final class ProjectModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'projects';

    protected $fillable = [
        'slug',
        'preview_image',
        'preview_image_alt',
        'github_url',
        'live_demo_url',
        'tech_writeup_article_id',
        'is_featured',
        'featured_sort_order',
        'is_published',
        'published_at',
        'tech_tags_count',
    ];

    /** @return HasMany<ProjectTranslationModel, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ProjectTranslationModel::class, 'project_id');
    }

    /** @return HasMany<ProjectScreenshotModel, $this> */
    public function screenshots(): HasMany
    {
        return $this->hasMany(ProjectScreenshotModel::class, 'project_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** @return HasMany<ProjectDetailItemModel, $this> */
    public function detailItems(): HasMany
    {
        return $this->hasMany(ProjectDetailItemModel::class, 'project_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** @return BelongsToMany<SkillModel, $this> */
    public function techTags(): BelongsToMany
    {
        return $this->belongsToMany(SkillModel::class, 'project_tech_tags', 'project_id', 'skill_id');
    }

    /**
     * @param  Builder<ProjectModel>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    protected static function newFactory(): Factory
    {
        return ProjectModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'preview_image_alt' => 'array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'tech_tags_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $model): void {
            if ($model->is_published || $model->wasChanged('is_published')) {
                app(SitemapService::class)->invalidate();
            }
        });

        static::deleted(function (self $model): void {
            if ($model->is_published) {
                app(SitemapService::class)->invalidate();
            }
        });
    }
}
