<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Settings;

use App\Features\Settings\Domain\Entities\FeatureFlag;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use InvalidArgumentException;

describe('FeatureFlag', function (): void {
    it('accepts only names from the strictly defined set', function (): void {
        expect(FeatureFlag::isValidName('github_oauth'))->toBeTrue()
            ->and(FeatureFlag::isValidName('social_reactions'))->toBeTrue()
            ->and(FeatureFlag::isValidName('console_greeting'))->toBeTrue()
            ->and(FeatureFlag::isValidName('bogus_flag'))->toBeFalse()
            ->and(FeatureFlag::isValidName(''))->toBeFalse();
    });

    it('holds the name, enabled state and optional description', function (): void {
        $flag = new FeatureFlag(FeatureFlagName::GithubOauth, true, 'Enables GitHub login.');

        expect($flag->toName())->toBe('github_oauth')
            ->and($flag->toEnabled())->toBeTrue()
            ->and($flag->toDescription())->toBe('Enables GitHub login.');
    });

    it('limits the description size', function (): void {
        expect(fn () => new FeatureFlag(
            FeatureFlagName::GithubOauth,
            false,
            str_repeat('a', FeatureFlag::MAX_DESCRIPTION_LENGTH + 1),
        ))->toThrow(InvalidArgumentException::class);

        expect(new FeatureFlag(
            FeatureFlagName::GithubOauth,
            false,
            str_repeat('a', FeatureFlag::MAX_DESCRIPTION_LENGTH),
        ))->not->toBeNull();
    });

    it('allows a null description', function (): void {
        expect((new FeatureFlag(FeatureFlagName::GithubOauth, false, null))->toDescription())->toBeNull();
    });
});
