<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Models;

use Database\Factories\SkillModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `skills` table (cross-feature read access).
 *
 * Write operations are owned by the TechSkills feature; this model is the
 * shared read-side contract (see `EloquentTechSkillReadRepository`).
 *
 * @property int $id
 * @property int $skill_group_id
 * @property string $name
 * @property string|null $icon
 * @property int $proficiency
 * @property bool $include_in_resume
 * @property int $sort_order
 */
final class SkillModel extends Model
{
    use HasFactory;

    protected $table = 'skills';

    protected $fillable = [
        'skill_group_id',
        'name',
        'icon',
        'proficiency',
        'include_in_resume',
        'sort_order',
    ];

    protected static function newFactory(): Factory
    {
        return SkillModelFactory::new();
    }
}
