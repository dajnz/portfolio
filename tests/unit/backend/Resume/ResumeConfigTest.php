<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Resume;

use App\Features\Resume\Domain\Entities\ResumeConfig;
use DateTimeImmutable;
use InvalidArgumentException;

describe('ResumeConfig', function (): void {
    $legacyLayout = fn (array $overrides = []): array => array_merge([
        'template' => 'modern',
        'sections' => [
            'header' => ['visible' => true, 'order' => 0],
            'about_me' => ['visible' => true, 'order' => 1],
            'skills' => ['visible' => true, 'order' => 2],
            'experience' => ['visible' => true, 'order' => 3],
            'contacts' => ['visible' => true, 'order' => 4],
        ],
    ], $overrides);

    $config = fn (array $layout): ResumeConfig => new ResumeConfig(
        id: ResumeConfig::ID,
        mode: 'auto',
        layout: $layout,
        customResumeFile: null,
        translations: [],
        updatedAt: new DateTimeImmutable(),
    );

    it('normalizes a legacy layout that predates the rendering options', function () use ($legacyLayout, $config): void {
        $resumeConfig = $config($legacyLayout());

        expect($resumeConfig->toLayout())->toMatchArray([
            'skills_layout' => 'per_line',
            'contacts_show_heading' => true,
            'contacts_layout' => 'per_line',
            'experience_show_skills' => true,
        ])
            ->and($resumeConfig->toSkillsLayout())->toBe('per_line')
            ->and($resumeConfig->showsContactsHeading())->toBeTrue()
            ->and($resumeConfig->toContactsLayout())->toBe('per_line')
            ->and($resumeConfig->showsExperienceSkills())->toBeTrue();
    });

    it('normalizes the rendering options on update', function () use ($legacyLayout, $config): void {
        $resumeConfig = $config($legacyLayout(['skills_layout' => 'inline']));

        $resumeConfig->updateLayout($legacyLayout(['skills_layout' => 'inline']));

        expect($resumeConfig->toSkillsLayout())->toBe('inline')
            ->and($resumeConfig->toContactsLayout())->toBe('per_line')
            ->and($resumeConfig->showsContactsHeading())->toBeTrue()
            ->and($resumeConfig->showsExperienceSkills())->toBeTrue();
    });

    it('rejects an unknown skills layout', function () use ($legacyLayout, $config): void {
        expect(fn () => $config($legacyLayout(['skills_layout' => 'grid'])))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects an unknown contacts layout', function () use ($legacyLayout, $config): void {
        expect(fn () => $config($legacyLayout(['contacts_layout' => 'grid'])))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects non-boolean contact-heading and experience-skills flags', function () use ($legacyLayout, $config): void {
        expect(fn () => $config($legacyLayout(['contacts_show_heading' => 'yes'])))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn () => $config($legacyLayout(['experience_show_skills' => 1])))
            ->toThrow(InvalidArgumentException::class);
    });
});
