<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Models;

use Database\Factories\AboutInfoModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for the single-row `about_info` table.
 *
 * `resume_file` is the legacy column owned by the Resume feature and is
 * deliberately absent from the fillable set.
 *
 * @property int $id
 * @property string|null $photo
 * @property array<string, string|null>|null $photo_alt
 * @property bool $show_location
 * @property string|null $timezone
 * @property bool $relocation_readiness
 * @property string|null $availability_status
 * @property string $work_type
 * @property \Illuminate\Support\Collection<int, AboutInfoTranslationModel> $translations
 */
final class AboutInfoModel extends Model
{
    use HasFactory;

    protected $table = 'about_info';

    protected $fillable = [
        'photo',
        'photo_alt',
        'show_location',
        'timezone',
        'relocation_readiness',
        'availability_status',
        'work_type',
    ];

    /** @return HasMany<AboutInfoTranslationModel, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(AboutInfoTranslationModel::class, 'about_info_id');
    }

    protected static function newFactory(): Factory
    {
        return AboutInfoModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'photo_alt' => 'array',
            'show_location' => 'boolean',
            'relocation_readiness' => 'boolean',
        ];
    }
}
