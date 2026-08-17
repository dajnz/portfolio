<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for `resume_config` (database.md:104-116) — the
 * single-row resume generation configuration (id = 1, enforced at the
 * application level). `layout` is the jsonb template + sections map
 * (content-management.md:335-347).
 *
 * @property int $id
 * @property string $mode
 * @property array<string, mixed> $layout
 * @property string|null $custom_resume_file
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<int, ResumeConfigTranslationModel> $translations
 */
final class ResumeConfigModel extends Model
{
    protected $table = 'resume_config';

    protected $fillable = [
        'id',
        'mode',
        'layout',
        'custom_resume_file',
    ];

    /** @return HasMany<ResumeConfigTranslationModel, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ResumeConfigTranslationModel::class, 'resume_config_id');
    }

    protected function casts(): array
    {
        return [
            'layout' => 'array',
        ];
    }
}
