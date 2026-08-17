<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `resume_config_translations` (database.md:117-126).
 * Composite primary key `(resume_config_id, locale)` — no auto-increment,
 * no timestamps. `about_text` is the custom "About Me" for the
 * auto-generated resume (falls back to the about short pitch when null).
 *
 * @property int $resume_config_id
 * @property string $locale
 * @property string|null $about_text
 */
final class ResumeConfigTranslationModel extends Model
{
    protected $table = 'resume_config_translations';

    protected $fillable = [
        'resume_config_id',
        'locale',
        'about_text',
    ];

    protected $primaryKey = ['resume_config_id', 'locale'];

    public $incrementing = false;

    public $timestamps = false;
}
