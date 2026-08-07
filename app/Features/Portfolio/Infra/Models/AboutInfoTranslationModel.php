<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `about_info_translations` (database.md:89-99).
 * Composite primary key `(about_info_id, locale)` — no auto-increment,
 * no timestamps.
 *
 * @property int $about_info_id
 * @property string $locale
 * @property string $name
 * @property string $title
 * @property string $short_pitch
 * @property string|null $location
 */
final class AboutInfoTranslationModel extends Model
{
    protected $table = 'about_info_translations';

    protected $fillable = [
        'about_info_id',
        'locale',
        'name',
        'title',
        'short_pitch',
        'location',
    ];

    protected $primaryKey = ['about_info_id', 'locale'];

    public $incrementing = false;

    public $timestamps = false;
}
