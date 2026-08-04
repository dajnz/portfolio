<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Models;

use Database\Factories\SkillGroupTranslationModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `skill_group_translations` table.
 *
 * Composite primary key `(skill_group_id, locale)` — no auto-increment,
 * no timestamps.
 *
 * @property int $skill_group_id
 * @property string $locale
 * @property string $name
 */
final class SkillGroupTranslationModel extends Model
{
    use HasFactory;

    protected $table = 'skill_group_translations';

    protected $fillable = [
        'skill_group_id',
        'locale',
        'name',
    ];

    protected $primaryKey = ['skill_group_id', 'locale'];

    public $incrementing = false;

    public $timestamps = false;

    protected static function newFactory(): Factory
    {
        return SkillGroupTranslationModelFactory::new();
    }
}
