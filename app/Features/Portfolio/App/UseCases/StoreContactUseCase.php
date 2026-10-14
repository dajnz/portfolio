<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\Entities\Contact;
use App\Features\Portfolio\Domain\RepositoryInterfaces\ContactRepositoryInterface;
use App\Features\Portfolio\Domain\ValueObjects\ContactTranslation;
use App\Features\Shared\App\Contracts\ActiveLocaleInterface;
use App\Features\Shared\App\Contracts\TransactionManagerInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Creates a contact with its per-locale names (only the locales active
 * in the `supported_locales` setting are persisted — disabled locales'
 * rows are preserved, not deleted).
 */
final class StoreContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
        private readonly ActiveLocaleInterface $locales,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly CountCacheInvalidatorInterface $countCacheInvalidator,
    ) {
    }

    /**
     * @param  array<string, string|null>  $names  Locale => localized name.
     */
    public function store(
        string $value,
        ?string $icon,
        bool $includeInResume,
        ?string $url,
        int $sortOrder,
        array $names,
    ): void {
        $translations = $this->translations($names);

        $this->transactionManager->transaction(function () use ($value, $icon, $includeInResume, $url, $sortOrder, $translations): void {
            $this->repository->save(new Contact(
                id: 0,
                value: $value,
                icon: $icon,
                includeInResume: $includeInResume,
                url: $url,
                sortOrder: $sortOrder,
                translations: $translations,
            ));
        });

        $this->countCacheInvalidator->invalidate('contacts');
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
