<?php

declare(strict_types=1);

use App\Features\Portfolio\Domain\Entities\Contact;
use App\Features\Portfolio\Domain\Entities\ContactTranslation;

function makeContact(array $overrides = []): Contact
{
    return new Contact(
        id: $overrides['id'] ?? 1,
        value: $overrides['value'] ?? 'octocat',
        icon: $overrides['icon'] ?? 'simple-icons:github',
        includeInResume: $overrides['include_in_resume'] ?? true,
        url: $overrides['url'] ?? 'https://github.com/octocat',
        sortOrder: $overrides['sort_order'] ?? 0,
        translations: $overrides['translations'] ?? [
            new ContactTranslation(locale: 'en', name: 'GitHub'),
            new ContactTranslation(locale: 'ru', name: 'Гитхаб'),
        ],
    );
}

describe('Contact', function (): void {
    it('exposes its state via toXXX getters', function (): void {
        $contact = makeContact();

        expect($contact->toId())->toBe(1)
            ->and($contact->toValue())->toBe('octocat')
            ->and($contact->toIcon())->toBe('simple-icons:github')
            ->and($contact->toIncludeInResume())->toBeTrue()
            ->and($contact->toUrl())->toBe('https://github.com/octocat')
            ->and($contact->toSortOrder())->toBe(0)
            ->and($contact->toTranslations())->toHaveCount(2);
    });

    it('rejects an empty value', function (): void {
        makeContact(['value' => '']);
    })->throws(InvalidArgumentException::class);

    it('rejects a negative sort order', function (): void {
        makeContact(['sort_order' => -1]);
    })->throws(InvalidArgumentException::class);

    it('resolves the name per locale with an en fallback', function (): void {
        $contact = makeContact();

        expect($contact->nameFor('ru'))->toBe('Гитхаб')
            ->and($contact->nameFor('en'))->toBe('GitHub')
            ->and($contact->nameFor('de'))->toBe('GitHub');
    });

    it('returns an empty name when no translation exists at all', function (): void {
        $contact = makeContact(['translations' => []]);

        expect($contact->nameFor('en'))->toBe('');
    });
});

describe('ContactTranslation', function (): void {
    it('exposes locale and name', function (): void {
        $translation = new ContactTranslation(locale: 'en', name: 'GitHub');

        expect($translation->toLocale())->toBe('en')
            ->and($translation->toName())->toBe('GitHub');
    });

    it('rejects an empty name', function (): void {
        new ContactTranslation(locale: 'en', name: '');
    })->throws(InvalidArgumentException::class);
});
