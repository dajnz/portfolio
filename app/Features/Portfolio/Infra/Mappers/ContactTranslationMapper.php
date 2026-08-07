<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Mappers;

use App\Features\Portfolio\Domain\Entities\ContactTranslation;
use App\Features\Portfolio\Infra\Models\ContactTranslationModel;

/**
 * Converts between `contact_translations` rows and
 * {@see ContactTranslation} entities.
 */
final class ContactTranslationMapper
{
    public function toEntity(ContactTranslationModel $model): ContactTranslation
    {
        return new ContactTranslation(
            locale: $model->locale,
            name: $model->name,
        );
    }

    /**
     * @return array{contact_id: int, locale: string, name: string}
     */
    public function toRow(ContactTranslation $translation, int $contactId): array
    {
        return [
            'contact_id' => $contactId,
            'locale' => $translation->toLocale(),
            'name' => $translation->toName(),
        ];
    }
}
