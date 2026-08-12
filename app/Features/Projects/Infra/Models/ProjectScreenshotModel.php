<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Models;

use Database\Factories\ProjectScreenshotModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `project_screenshots` (database.md:261-273): the
 * RustFS image path plus the per-locale jsonb `alt_text` / `title` /
 * `description` maps and the gallery `sort_order`.
 *
 * @property int $id
 * @property int $project_id
 * @property string $image_path
 * @property array<string, string>|null $alt_text
 * @property array<string, string>|null $title
 * @property array<string, string>|null $description
 * @property int $sort_order
 */
final class ProjectScreenshotModel extends Model
{
    use HasFactory;

    protected $table = 'project_screenshots';

    protected $fillable = [
        'project_id',
        'image_path',
        'alt_text',
        'title',
        'description',
        'sort_order',
    ];

    protected static function newFactory(): Factory
    {
        return ProjectScreenshotModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'alt_text' => 'array',
            'title' => 'array',
            'description' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
