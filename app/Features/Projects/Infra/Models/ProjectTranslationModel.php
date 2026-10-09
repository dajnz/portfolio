<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Models;

use App\Features\Shared\Infra\Services\SitemapService;
use Database\Factories\ProjectTranslationModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent model for `project_translations`.
 *
 * The `saved` event rebuilds the locale's `search_vector` from
 * `title || ' ' || brief_description || ' ' || full_description` via
 * the locale's Snowball dictionary (`config('portfolio.fts.locales')`,
 * fallback `english`) and invalidates the `sitemap:xml` cache
 * (translation edits change the entity's lastmod).
 *
 * @property int $project_id
 * @property string $locale
 * @property string $title
 * @property string $brief_description
 * @property string|null $full_description
 * @property string|null $problem
 * @property string|null $solution
 * @property mixed $search_vector
 */
final class ProjectTranslationModel extends Model
{
    use HasFactory;

    protected $table = 'project_translations';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['project_id', 'locale'];

    protected $fillable = [
        'project_id',
        'locale',
        'title',
        'brief_description',
        'full_description',
        'problem',
        'solution',
    ];

    protected static function newFactory(): Factory
    {
        return ProjectTranslationModelFactory::new();
    }

    protected static function booted(): void
    {
        static::saved(function (self $model): void {
            $locales = config('portfolio.fts.locales', []);
            $dictionary = is_array($locales) && isset($locales[$model->locale])
                ? (string) $locales[$model->locale]
                : 'english';

            DB::statement(
                'UPDATE '.$model->getTable().
                ' SET search_vector = to_tsvector(?, ?) WHERE project_id = ? AND locale = ?',
                [
                    $dictionary,
                    implode(' ', array_filter([
                        $model->title,
                        $model->brief_description ?? '',
                        $model->full_description ?? '',
                    ])),
                    $model->project_id,
                    $model->locale,
                ]
            );

            app(SitemapService::class)->invalidate();
        });
    }
}
