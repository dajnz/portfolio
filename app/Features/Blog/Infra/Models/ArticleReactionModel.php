<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Models;

use Database\Factories\ArticleReactionModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `article_reactions` (database.md:406-420): the
 * unique `(article_id, fingerprint)` pair and the `like`/`dislike`
 * CHECK constraint. Upsert semantics — only `created_at` exists (the
 * first-reaction timestamp); the same-reaction re-click DELETES the
 * row (undo), a different reaction UPDATES it.
 *
 * @property int $id
 * @property int $article_id
 * @property string $fingerprint
 * @property string $reaction
 * @property \Illuminate\Support\Carbon $created_at
 */
final class ArticleReactionModel extends Model
{
    use HasFactory;

    protected $table = 'article_reactions';

    public const UPDATED_AT = null;

    protected $fillable = [
        'article_id',
        'fingerprint',
        'reaction',
    ];

    protected static function newFactory(): Factory
    {
        return ArticleReactionModelFactory::new();
    }
}
