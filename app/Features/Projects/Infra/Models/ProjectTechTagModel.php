<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent model for the `project_tech_tags` pivot — the sole writer of
 * the persisted `tech_tags_count` column: the `saved` event increments
 * and the `deleted` event decrements the owning project's count
 * atomically ("no additional query needed").
 *
 * The repository performs every pivot mutation through instances of
 * this model (never raw pivot inserts), so the events fire
 * deterministically — including the `sync` inside `save()`.
 *
 * @property int $project_id
 * @property int $skill_id
 */
final class ProjectTechTagModel extends Model
{
    protected $table = 'project_tech_tags';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['project_id', 'skill_id'];

    protected $fillable = [
        'project_id',
        'skill_id',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $model): void {
            if ($model->project_id !== null) {
                DB::table('projects')->where('id', $model->project_id)->increment('tech_tags_count');
            }
        });

        static::deleted(function (self $model): void {
            if ($model->project_id !== null) {
                DB::table('projects')->where('id', $model->project_id)->decrement('tech_tags_count');
            }
        });
    }
}
