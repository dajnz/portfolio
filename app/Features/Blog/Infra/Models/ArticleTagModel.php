<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Models;

use Database\Factories\ArticleTagModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for `article_tags` — DB-table code only.
 *
 * @property int $id
 * @property string $slug
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, ArticleTagTranslationModel> $translations
 */
final class ArticleTagModel extends Model
{
    use HasFactory;

    protected $table = 'article_tags';

    protected $fillable = ['slug'];

    /** @return HasMany<ArticleTagTranslationModel, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ArticleTagTranslationModel::class, 'article_tag_id');
    }

    protected static function newFactory(): Factory
    {
        return ArticleTagModelFactory::new();
    }
}
