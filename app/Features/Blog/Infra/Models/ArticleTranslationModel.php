<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Models;

use App\Features\Blog\Domain\ValueObjects\ReadingTime;
use Database\Factories\ArticleTranslationModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

/**
 * Eloquent model for `blog_article_translations` (database.md:351-364).
 *
 * The `saving` event recomputes `reading_time_minutes` from the
 * content (`ceil(word_count / 200)` — content-management.md:472, via
 * the domain {@see ReadingTime}); the `saved` event rebuilds the
 * locale's `search_vector` from
 * `title || ' ' || short_description || ' ' || content` via the
 * locale's Snowball dictionary (`Config::get('portfolio.fts.locales')`,
 * fallback `english` — database.md:627-645). The `sitemap:xml`
 * invalidation is NOT a model concern — the write use cases
 * orchestrate it via the shared `SitemapInvalidatorInterface`
 * (seo.md:219).
 *
 * @property int $article_id
 * @property string $locale
 * @property string $title
 * @property string $short_description
 * @property string $content
 * @property int $reading_time_minutes
 * @property mixed $search_vector
 */
final class ArticleTranslationModel extends Model
{
    use HasFactory;

    protected $table = 'blog_article_translations';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['article_id', 'locale'];

    protected $fillable = [
        'article_id',
        'locale',
        'title',
        'short_description',
        'content',
    ];

    protected static function newFactory(): Factory
    {
        return ArticleTranslationModelFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->reading_time_minutes = ReadingTime::fromText($model->content)->toMinutes();
        });

        static::saved(function (self $model): void {
            $locales = Config::get('portfolio.fts.locales', []);
            $dictionary = is_array($locales) && isset($locales[$model->locale])
                ? (string) $locales[$model->locale]
                : 'english';

            DB::statement(
                'UPDATE '.$model->getTable().
                ' SET search_vector = to_tsvector(?, ?) WHERE article_id = ? AND locale = ?',
                [
                    $dictionary,
                    implode(' ', [
                        $model->title,
                        $model->short_description ?? '',
                        $model->content ?? '',
                    ]),
                    $model->article_id,
                    $model->locale,
                ]
            );
        });
    }
}
