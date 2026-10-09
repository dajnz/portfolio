<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Models;

use Database\Factories\ContactModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for the `contacts` table.
 *
 * @property int $id
 * @property string $value
 * @property string|null $icon
 * @property bool $include_in_resume
 * @property string|null $url
 * @property int $sort_order
 * @property \Illuminate\Support\Collection<int, ContactTranslationModel> $translations
 */
final class ContactModel extends Model
{
    use HasFactory;

    protected $table = 'contacts';

    protected $fillable = [
        'value',
        'icon',
        'include_in_resume',
        'url',
        'sort_order',
    ];

    /** @return HasMany<ContactTranslationModel, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ContactTranslationModel::class, 'contact_id');
    }

    protected static function newFactory(): Factory
    {
        return ContactModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'include_in_resume' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
