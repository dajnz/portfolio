<?php

declare(strict_types=1);

namespace App\Features\Experience\Infra\Models;

use Database\Factories\TimelineItemTranslationModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `experience_timeline_item_translations`
 * (database.md:307-318). Composite primary key `(item_id, locale)` — no
 * auto-increment, no timestamps. `achievements` is the per-locale jsonb
 * array of `{description, project_link?}`.
 *
 * @property int $item_id
 * @property string $locale
 * @property string $position
 * @property string $company_name
 * @property string|null $company_country
 * @property string|null $short_description
 * @property array<int, array{description: string, project_link: string|null}>|null $achievements
 */
final class TimelineItemTranslationModel extends Model
{
    use HasFactory;

    protected $table = 'experience_timeline_item_translations';

    protected $fillable = [
        'item_id',
        'locale',
        'position',
        'company_name',
        'company_country',
        'short_description',
        'achievements',
    ];

    protected $primaryKey = ['item_id', 'locale'];

    public $incrementing = false;

    public $timestamps = false;

    protected static function newFactory(): Factory
    {
        return TimelineItemTranslationModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'achievements' => 'array',
        ];
    }
}
