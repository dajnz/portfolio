<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Models;

use Database\Factories\ArticleViewModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `article_views`: the per-article counter table
 * (`article_id` is the PRIMARY KEY — a view row is born on the first
 * atomic upsert-increment). No timestamps.
 *
 * @property int $article_id
 * @property int $views
 */
final class ArticleViewModel extends Model
{
    use HasFactory;

    protected $table = 'article_views';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'article_id';

    protected $fillable = [
        'article_id',
        'views',
    ];

    protected static function newFactory(): Factory
    {
        return ArticleViewModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'views' => 'integer',
        ];
    }
}
