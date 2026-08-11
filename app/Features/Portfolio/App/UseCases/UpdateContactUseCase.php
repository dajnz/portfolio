<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\Entities\Contact;
use App\Features\Portfolio\Domain\ValueObjects\ContactTranslation;
use App\Features\Portfolio\Domain\RepositoryInterfaces\ContactRepositoryInterface;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use App\Features\Shared\Infra\Services\AdminCursorPaginator;
use Illuminate\Support\Facades\DB;

/**
 * Updates a contact: the non-translatable columns plus the per-locale
 * names (upsert; rows for locales absent from the submission are
 * preserved, per content-management.md:419).
 */
final class UpdateContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
        private readonly ActiveLocaleService $locales,
    ) {
    }

    /**
     * @param  array<string, string|null>  $names  Locale => localized name.
     */
    public function update(
        int $id,
        string $value,
        ?string $icon,
        bool $includeInResume,
        ?string $url,
        int $sortOrder,
        array $names,
    ): void {
        $translations = $this->translations($names);

        DB::transaction(function () use ($id, $value, $icon, $includeInResume, $url, $sortOrder, $translations): void {
            $this->repository->save(new Contact(
                id: $id,
                value: $value,
                icon: $icon,
                includeInResume: $includeInResume,
                url: $url,
                sortOrder: $sortOrder,
                translations: $translations,
            ));
        });
        AdminCursorPaginator::invalidateCountCache('contacts');

    }

    /**
     * @param  array<string, string|null>  $names
     * @return ContactTranslation[]
     */
    private function translations(array $names): array
    {
        $translations = [];
        foreach ($this->locales->activeLocales() as $locale) {
            $name = $names[$locale] ?? null;

            if ($name === null || $name === '') {
                continue;
            }

            $translations[] = new ContactTranslation(locale: $locale, name: $name);
        }

        return $translations;
    }
}
