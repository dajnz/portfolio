<?php

declare(strict_types=1);

namespace App\Features\Experience\Infra\Models;

use App\Features\Shared\Infra\Models\SkillModel;
use App\Features\Shared\Infra\Services\SitemapService;
use Database\Factories\TimelineItemModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

/**
 * Eloquent model for `experience_timeline_items`.
 *
 * The `sitemap:xml` cache is invalidated whenever a published item is
 * saved or deleted: a save that leaves the item published, or flips
 * `is_published` (an unpublish must drop the sitemap entry too), and a
 * delete of a published item.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property bool $is_published
 * @property int $sort_order
 * @property int|null $project_id
 * @property bool $include_in_resume
 * @property string|null $external_project_name
 * @property string|null $external_project_url
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Collection<int, TimelineItemTranslationModel> $translations
 * @property \Illuminate\Support\Collection<int, SkillModel> $techTags
 */
final class TimelineItemModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'experience_timeline_items';

    protected $fillable = [
        'started_at',
        'ended_at',
        'is_published',
        'sort_order',
        'project_id',
        'include_in_resume',
        'external_project_name',
        'external_project_url',
    ];

    /** @return HasMany<TimelineItemTranslationModel, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(TimelineItemTranslationModel::class, 'item_id');
    }

    /** @return BelongsToMany<SkillModel, $this> */
    public function techTags(): BelongsToMany
    {
        return $this->belongsToMany(SkillModel::class, 'experience_item_tech_tags', 'experience_item_id', 'skill_id');
    }

    /**
     * @param  Builder<TimelineItemModel>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    protected static function newFactory(): Factory
    {
        return TimelineItemModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
            'include_in_resume' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $model): void {
            if ($model->is_published || $model->wasChanged('is_published')) {
                App::make(SitemapService::class)->invalidate();
            }
        });

        static::deleted(function (self $model): void {
            if ($model->is_published) {
                App::make(SitemapService::class)->invalidate();
            }
        });
    }
}
