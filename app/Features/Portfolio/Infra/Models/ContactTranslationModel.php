<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Models;

use Database\Factories\ContactTranslationModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `contact_translations`. Composite primary key
 * `(contact_id, locale)` — no auto-increment, no timestamps.
 *
 * @property int $contact_id
 * @property string $locale
 * @property string $name
 */
final class ContactTranslationModel extends Model
{
    use HasFactory;

    protected $table = 'contact_translations';

    protected $fillable = [
        'contact_id',
        'locale',
        'name',
    ];

    protected $primaryKey = ['contact_id', 'locale'];

    public $incrementing = false;

    public $timestamps = false;

    protected static function newFactory(): Factory
    {
        return ContactTranslationModelFactory::new();
    }
}
