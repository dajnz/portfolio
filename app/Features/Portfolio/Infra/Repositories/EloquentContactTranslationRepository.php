<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Repositories;

use App\Features\Portfolio\Domain\Entities\ContactTranslation;
use App\Features\Portfolio\Infra\Mappers\ContactTranslationMapper;
use App\Features\Portfolio\Infra\Models\ContactTranslationModel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Persistence of `contact_translations` rows (database.md:182-189) —
 * read/write side of the `(contact_id, locale)` composite-PK table.
 */
final class EloquentContactTranslationRepository
{
    public function __construct(
        private readonly ContactTranslationMapper $mapper,
    ) {
    }

    /**
     * @return ContactTranslation[]
     */
    public function findForContact(int $contactId): array
    {
        return ContactTranslationModel::query()
            ->where('contact_id', $contactId)
            ->get()
            ->map(fn (ContactTranslationModel $row): ContactTranslation => $this->mapper->toEntity($row))
            ->all();
    }

    /**
     * Upserts the given translations and removes rows of the contact
     * whose locales are absent from the set (A2-style reconciliation).
     *
     * @param  ContactTranslation[]  $translations
     */
    public function syncForContact(int $contactId, array $translations): void
    {
        $localeNames = [];
        foreach ($translations as $translation) {
            $localeNames[$translation->toLocale()] = $translation->toName();
        }

        ContactTranslationModel::query()
            ->where('contact_id', $contactId)
            ->whereNotIn('locale', array_keys($localeNames))
            ->delete();

        foreach ($localeNames as $locale => $name) {
            ContactTranslationModel::query()->updateOrInsert(
                ['contact_id' => $contactId, 'locale' => $locale],
                ['name' => $name],
            );
        }
    }

    public function deleteForContact(int $contactId): void
    {
        ContactTranslationModel::query()
            ->where('contact_id', $contactId)
            ->delete();
    }

    /**
     * Loads the translation rows of multiple contacts (one query for
     * list hydration).
     *
     * @return EloquentCollection<int, ContactTranslationModel>
     */
    public function findForContacts(array $contactIds): EloquentCollection
    {
        return ContactTranslationModel::query()
            ->whereIn('contact_id', $contactIds)
            ->get();
    }
}
