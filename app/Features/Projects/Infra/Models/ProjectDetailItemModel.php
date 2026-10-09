<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Models;

use Database\Factories\ProjectDetailItemModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property string|null $icon
 * @property int $sort_order
 * @property \Illuminate\Support\Collection<int, ProjectDetailItemTranslationModel> $translations
 */
final class ProjectDetailItemModel extends Model
{
    use HasFactory;

    protected $table = 'project_detail_items';

    protected $fillable = [
        'project_id',
        'icon',
        'sort_order',
    ];

    /** @return HasMany<ProjectDetailItemTranslationModel, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ProjectDetailItemTranslationModel::class, 'detail_item_id');
    }

    protected static function newFactory(): Factory
    {
        return ProjectDetailItemModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
