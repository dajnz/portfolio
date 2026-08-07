<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Mappers;

use App\Features\Portfolio\Domain\Entities\Contact;
use App\Features\Portfolio\Domain\Entities\ContactTranslation;
use App\Features\Portfolio\Infra\Models\ContactModel;

/**
 * Converts between `contacts` rows (with their translation rows
 * pre-loaded via the `translations` relation) and {@see Contact}
 * entities, and back to the row data.
 */
final class ContactMapper
{
    public function __construct(
        private readonly ContactTranslationMapper $translationMapper,
    ) {
    }

    public function toEntity(ContactModel $model): Contact
    {
        $translations = $model->relationLoaded('translations')
            ? $model->translations
            : $model->translations()->get();

        return new Contact(
            id: $model->id,
            value: $model->value,
            icon: $model->icon,
            includeInResume: $model->include_in_resume,
            url: $model->url,
            sortOrder: $model->sort_order,
            translations: $translations
                ->map(fn (mixed $row): ContactTranslation => $this->translationMapper->toEntity($row))
                ->all(),
        );
    }

    /**
     * @return array{value: string, icon: string|null, include_in_resume: bool, url: string|null, sort_order: int}
     */
    public function toRow(Contact $contact): array
    {
        return [
            'value' => $contact->toValue(),
            'icon' => $contact->toIcon(),
            'include_in_resume' => $contact->toIncludeInResume(),
            'url' => $contact->toUrl(),
            'sort_order' => $contact->toSortOrder(),
        ];
    }
}
