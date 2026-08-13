<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Models;

use Database\Factories\ArticleTagTranslationModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `article_tag_translations` (database.md:377-384).
 *
 * @property int $article_tag_id
 * @property string $locale
 * @property string $name
 */
final class ArticleTagTranslationModel extends Model
{
    use HasFactory;

    protected $table = 'article_tag_translations';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['article_tag_id', 'locale'];

    protected $fillable = [
        'article_tag_id',
        'locale',
        'name',
    ];

    protected static function newFactory(): Factory
    {
        return ArticleTagTranslationModelFactory::new();
    }
}
