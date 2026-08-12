<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Models;

use Database\Factories\ProjectDetailItemTranslationModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `project_detail_item_translations` (database.md:
 * 251-259): the per-locale title/description of an engineering detail
 * item.
 *
 * @property int $detail_item_id
 * @property string $locale
 * @property string $title
 * @property string $description
 */
final class ProjectDetailItemTranslationModel extends Model
{
    use HasFactory;

    protected $table = 'project_detail_item_translations';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['detail_item_id', 'locale'];

    protected $fillable = [
        'detail_item_id',
        'locale',
        'title',
        'description',
    ];

    protected static function newFactory(): Factory
    {
        return ProjectDetailItemTranslationModelFactory::new();
    }
}
