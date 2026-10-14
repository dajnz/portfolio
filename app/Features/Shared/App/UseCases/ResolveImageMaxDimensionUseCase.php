<?php

declare(strict_types=1);

namespace App\Features\Shared\App\UseCases;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Shared\App\Contracts\UploadDefaultsInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\SettingsReadRepositoryInterface;

/**
 * The effective largest-side cap for uploaded images: the `settings` row
 * when set, the committed config default otherwise.
 */
final class ResolveImageMaxDimensionUseCase
{
    public function __construct(
        private readonly SettingsReadRepositoryInterface $settings,
        private readonly UploadDefaultsInterface $defaults,
    ) {
    }

    public function resolve(): int
    {
        return (int) $this->settings->value(
            SettingName::ImageMaxDimension->value,
            $this->defaults->imageMaxDimension(),
        );
    }
}
