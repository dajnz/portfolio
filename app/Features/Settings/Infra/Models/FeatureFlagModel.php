<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `feature_flags` key-value table.
 *
 * @property string $key
 * @property bool $enabled
 * @property string|null $description
 */
final class FeatureFlagModel extends Model
{
    protected $table = 'feature_flags';

    public $timestamps = false;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'enabled',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
