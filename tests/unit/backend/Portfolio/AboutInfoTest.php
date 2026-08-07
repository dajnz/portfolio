<?php

declare(strict_types=1);

use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\Entities\AboutInfoTranslation;
use App\Features\Portfolio\Domain\ValueObjects\AvailabilityStatus;

function makeAbout(array $overrides = []): AboutInfo
{
    return new AboutInfo(
        id: $overrides['id'] ?? 1,
        photo: $overrides['photo'] ?? 'about/photo.jpg',
        photoAlt: $overrides['photo_alt'] ?? ['en' => 'Portrait', 'ru' => 'Портрет'],
        showLocation: $overrides['show_location'] ?? true,
        timezone: $overrides['timezone'] ?? 'Europe/Berlin',
        relocationReadiness: $overrides['relocation_readiness'] ?? false,
        availabilityStatus: $overrides['availability_status'] ?? AvailabilityStatus::Available,
        workType: $overrides['work_type'] ?? 'remote',
        translations: $overrides['translations'] ?? [
            new AboutInfoTranslation(
                locale: 'en',
                name: 'Alex',
                title: 'Full-Stack Developer',
                shortPitch: 'I build fast web applications.',
                location: 'Berlin',
            ),
            new AboutInfoTranslation(
                locale: 'ru',
                name: 'Алекс',
                title: 'Фулстек-разработчик',
                shortPitch: 'Я создаю быстрые веб-приложения.',
                location: 'Берлин',
            ),
        ],
    );
}

describe('AboutInfo', function (): void {
    it('exposes its state via toXXX getters', function (): void {
        $about = makeAbout();

        expect($about->toId())->toBe(1)
            ->and($about->toPhoto())->toBe('about/photo.jpg')
            ->and($about->toPhotoAlt())->toBe(['en' => 'Portrait', 'ru' => 'Портрет'])
            ->and($about->toShowLocation())->toBeTrue()
            ->and($about->toTimezone())->toBe('Europe/Berlin')
            ->and($about->toRelocationReadiness())->toBeFalse()
            ->and($about->toAvailabilityStatus())->toBe(AvailabilityStatus::Available)
            ->and($about->toWorkType())->toBe('remote')
            ->and($about->toTranslations())->toHaveCount(2);
    });

    it('resolves the translation per locale with an en fallback', function (): void {
        $about = makeAbout();

        expect($about->translationFor('ru')?->toName())->toBe('Алекс')
            ->and($about->translationFor('en')?->toTitle())->toBe('Full-Stack Developer')
            ->and($about->translationFor('de')?->toName())->toBe('Alex');
    });

    it('returns null when no translation exists at all', function (): void {
        $about = makeAbout(['translations' => []]);

        expect($about->translationFor('en'))->toBeNull();
    });
});

describe('AboutInfoTranslation', function (): void {
    it('exposes its fields', function (): void {
        $translation = new AboutInfoTranslation(
            locale: 'en',
            name: 'Alex',
            title: 'Developer',
            shortPitch: 'Pitch.',
            location: null,
        );

        expect($translation->toLocale())->toBe('en')
            ->and($translation->toName())->toBe('Alex')
            ->and($translation->toTitle())->toBe('Developer')
            ->and($translation->toShortPitch())->toBe('Pitch.')
            ->and($translation->toLocation())->toBeNull();
    });

    it('rejects an empty name or title', function (): void {
        new AboutInfoTranslation(locale: 'en', name: '', title: 'Dev', shortPitch: 'P.', location: null);
    })->throws(InvalidArgumentException::class);
});
