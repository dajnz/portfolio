<?php

declare(strict_types=1);

namespace App\Features\Settings\App\UseCases;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Persists a batch of settings (`{ key: value, ... }`) atomically; the
 * repository invalidates the cache for every written key.
 *
 * `mail_password` is the only secret in the table — it is encrypted at
 * rest here (the admin form is the plaintext entry point) and decrypted
 * by `SettingsMailConfigProvider` when read. An empty submission keeps
 * the stored value (B49).
 */
final class UpdateSettingsUseCase
{
    public function __construct(
        private readonly SettingRepositoryInterface $repository,
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function update(array $settings): void
    {
        DB::transaction(function () use ($settings): void {
            foreach ($settings as $key => $value) {
                $name = SettingName::from((string) $key);

                if ($name === SettingName::MailPassword && ($value === null || $value === '')) {
                    continue;
                }

                $this->repository->saveValue($name, $this->prepareValue($name, $value));
            }
        });
    }

    private function prepareValue(SettingName $name, mixed $value): mixed
    {
        if ($name !== SettingName::MailPassword || $value === null || $value === '') {
            return $value;
        }

        return Crypt::encryptString((string) $value);
    }
}
