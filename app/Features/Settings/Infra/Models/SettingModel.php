<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `settings` key-value table. `value` is stored as
 * text; type-aware casting happens in `SettingMapper`.
 *
 * @property string $key
 * @property string|null $value
 * @property string $type
 */
final class SettingModel extends Model
{
    protected $table = 'settings';

    public $timestamps = false;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'type',
    ];
}
