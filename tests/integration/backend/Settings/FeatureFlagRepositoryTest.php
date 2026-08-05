<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Settings;

use App\Features\Settings\App\UseCases\ToggleFeatureFlagUseCase;
use App\Features\Settings\App\UseCases\UpdateFeatureFlagsUseCase;
use App\Features\Settings\Domain\Entities\FeatureFlag;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;
use App\Features\Settings\Infra\Models\FeatureFlagModel;
use Illuminate\Support\Facades\Cache;

describe('FeatureFlagRepository', function (): void {
    it('returns all flags ordered by key', function (): void {
        $this->seed(\Database\Seeders\FeatureFlagSeeder::class);

        $flags = app(FeatureFlagRepositoryInterface::class)->findAll();

        expect(array_map(
            static fn (FeatureFlag $flag): string => $flag->toName(),
            $flags,
        ))->toBe(['console_greeting', 'github_oauth', 'social_reactions']);
    });

    it('reports a missing flag as disabled', function (): void {
        expect(app(FeatureFlagRepositoryInterface::class)->isEnabled(FeatureFlagName::SocialReactions))->toBeFalse();
    });

    it('caches the enabled state and serves it from the cache', function (): void {
        FeatureFlagModel::query()->create(['key' => 'social_reactions', 'enabled' => true]);

        $repository = app(FeatureFlagRepositoryInterface::class);

        expect($repository->isEnabled(FeatureFlagName::SocialReactions))->toBeTrue();

        // A raw DB write behind the cache's back must not be observed.
        FeatureFlagModel::query()->whereKey('social_reactions')->update(['enabled' => false]);

        expect(Cache::has('feature_flag:social_reactions'))->toBeTrue()
            ->and($repository->isEnabled(FeatureFlagName::SocialReactions))->toBeTrue();
    });

    it('invalidates the cache on update', function (): void {
        FeatureFlagModel::query()->create(['key' => 'social_reactions', 'enabled' => false]);

        $repository = app(FeatureFlagRepositoryInterface::class);

        $repository->isEnabled(FeatureFlagName::SocialReactions);
        expect(Cache::has('feature_flag:social_reactions'))->toBeTrue();

        $repository->update(FeatureFlagName::SocialReactions, true);

        expect(Cache::has('feature_flag:social_reactions'))->toBeFalse()
            ->and($repository->isEnabled(FeatureFlagName::SocialReactions))->toBeTrue();
    });

    it('creates a missing row for a valid name on update', function (): void {
        $repository = app(FeatureFlagRepositoryInterface::class);

        $repository->update(FeatureFlagName::SocialReactions, true);

        expect(FeatureFlagModel::query()->find('social_reactions')->enabled)->toBeTrue()
            ->and($repository->isEnabled(FeatureFlagName::SocialReactions))->toBeTrue();
    });

    it('toggles a single flag through the use case', function (): void {
        $this->seed(\Database\Seeders\FeatureFlagSeeder::class);

        app(ToggleFeatureFlagUseCase::class)->toggle(FeatureFlagName::GithubOauth, true);

        expect(FeatureFlagModel::query()->find('github_oauth')->enabled)->toBeTrue();
    });

    it('applies a batch of flags through the use case', function (): void {
        $this->seed(\Database\Seeders\FeatureFlagSeeder::class);

        app(UpdateFeatureFlagsUseCase::class)
            ->update(['github_oauth' => true, 'social_reactions' => true]);

        expect(FeatureFlagModel::query()->find('github_oauth')->enabled)->toBeTrue()
            ->and(FeatureFlagModel::query()->find('social_reactions')->enabled)->toBeTrue()
            ->and(FeatureFlagModel::query()->find('console_greeting')->enabled)->toBeFalse();
    });
});
