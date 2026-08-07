<?php

declare(strict_types=1);

use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use InvalidArgumentException;

describe('StoredPicture', function (): void {
    it('exposes the url, per-locale labels and descriptions', function (): void {
        $picture = new StoredPicture(
            url: 'about/photo.jpg',
            altLabels: ['en' => 'Portrait', 'ru' => 'Портрет'],
            descriptions: ['en' => 'A portrait'],
        );

        expect($picture->url)->toBe('about/photo.jpg')
            ->and($picture->altLabelsMap())->toBe(['en' => 'Portrait', 'ru' => 'Портрет'])
            ->and($picture->descriptionsMap())->toBe(['en' => 'A portrait']);
    });

    it('resolves labels and descriptions per locale with an en fallback', function (): void {
        $picture = new StoredPicture(
            url: 'x.jpg',
            altLabels: ['en' => 'Portrait'],
        );

        expect($picture->altLabel('en'))->toBe('Portrait')
            ->and($picture->altLabel('ru'))->toBe('Portrait')
            ->and($picture->altLabel('de'))->toBe('Portrait')
            ->and($picture->description('en'))->toBeNull();
    });

    it('allows an empty label map (no alt text yet) and an empty description map', function (): void {
        $picture = new StoredPicture(url: 'x.jpg', altLabels: []);

        expect($picture->altLabelsMap())->toBe([]);
    });

    it('rejects an empty url', function (): void {
        new StoredPicture(url: '', altLabels: []);
    })->throws(InvalidArgumentException::class);

    it('rejects blank alt labels', function (): void {
        new StoredPicture(url: 'x.jpg', altLabels: ['en' => '']);
    })->throws(InvalidArgumentException::class);

    it('rejects blank descriptions', function (): void {
        new StoredPicture(url: 'x.jpg', altLabels: ['en' => 'A'], descriptions: ['ru' => '']);
    })->throws(InvalidArgumentException::class);
});
