<?php

declare(strict_types=1);

namespace App\Features\Settings\App\UseCases;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;

/**
 * Flat `key => typed value` map of every setting row, shared to the
 * Settings page (contract: `settings` prop of `SettingsPageProps`).
 * `mail_api_token` is masked (B49, moved to the token per B125).
 */
final class GetSettingsUseCase
{
    public function __construct(
        private readonly SettingRepositoryInterface $repository,
    ) {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $settings = [];

        foreach ($this->repository->findAll() as $setting) {
            $settings[$setting->toName()] = $setting->toName() === SettingName::MailApiToken->value
                ? ''
                : $setting->value();
        }

        return $settings;
    }
}
