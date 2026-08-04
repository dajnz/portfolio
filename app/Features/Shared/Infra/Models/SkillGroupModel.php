<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Models;

use Database\Factories\SkillGroupModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `skill_groups` table (cross-feature read access).
 *
 * Write operations are owned by the TechSkills feature; this model is the
 * shared read-side contract (see `EloquentTechSkillReadRepository`).
 *
 * @property int $id
 * @property int $sort_order
 * @property string|null $icon
 */
final class SkillGroupModel extends Model
{
    use HasFactory;

    protected $table = 'skill_groups';

    protected $fillable = [
        'sort_order',
        'icon',
    ];

    protected static function newFactory(): Factory
    {
        return SkillGroupModelFactory::new();
    }
}
