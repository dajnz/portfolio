<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Portfolio;

use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\ValueObjects\AboutInfoTranslation;
use App\Features\Portfolio\Domain\ValueObjects\AvailabilityStatus;
use App\Features\Portfolio\Domain\ValueObjects\WorkType;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;

function makeAbout(array $overrides = []): AboutInfo
{
    return new AboutInfo(
        id: $overrides['id'] ?? 1,
        photo: $overrides['photo'] ?? new StoredPicture(
            url: 'about/photo.jpg',
            altLabels: ['en' => 'Portrait', 'ru' => 'Портрет'],
        ),
        showLocation: $overrides['show_location'] ?? true,
        timezone: $overrides['timezone'] ?? 'Europe/Berlin',
        relocationReadiness: $overrides['relocation_readiness'] ?? false,
        availabilityStatus: $overrides['availability_status'] ?? AvailabilityStatus::Available,
        workType: $overrides['work_type'] ?? WorkType::Remote,
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
            ->and($about->toPhoto()->url)->toBe('about/photo.jpg')
            ->and($about->toPhoto()->altLabelsMap())->toBe(['en' => 'Portrait', 'ru' => 'Портрет'])
            ->and($about->toPhoto()->altLabel('ru'))->toBe('Портрет')
            ->and($about->toShowLocation())->toBeTrue()
            ->and($about->toTimezone())->toBe('Europe/Berlin')
            ->and($about->toRelocationReadiness())->toBeFalse()
            ->and($about->toAvailabilityStatus())->toBe(AvailabilityStatus::Available)
            ->and($about->toWorkType())->toBe(WorkType::Remote)
            ->and($about->toTranslations())->toHaveCount(2)
            ->and($about->translationsByLocale())->toHaveKeys(['en', 'ru']);
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
