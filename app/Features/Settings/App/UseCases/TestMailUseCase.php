<?php

declare(strict_types=1);

namespace App\Features\Settings\App\UseCases;

use App\Features\Settings\App\Contracts\MailTransportTesterInterface;
use App\Features\Settings\App\Enums\MailTransportProbeStatus;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\EncrypterInterface;
use Throwable;

/**
 * The "Test Connection" probe: checks that the 3rd-party mail API is reachable
 * and accepts the token — without sending any email.
 *
 * The submitted Mail-tab values override the stored settings (so the probe
 * works on unsaved form state); an empty token submission falls back to the
 * stored token.
 */
final class TestMailUseCase
{
    public function __construct(
        private readonly SettingRepositoryInterface $repository,
        private readonly MailTransportTesterInterface $tester,
        private readonly EncrypterInterface $encrypter,
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array{ok: bool, status: 'ok'|'unauthorized'|'unreachable'}
     */
    public function test(array $settings): array
    {
        $endpoint = (string) ($settings[SettingName::MailApiEndpoint->value] ?? '');
        $token = (string) ($settings[SettingName::MailApiToken->value] ?? '');

        if ($endpoint === '') {
            $endpoint = (string) $this->repository->value(SettingName::MailApiEndpoint, '');
        }

        if ($token === '') {
            $token = $this->storedToken();
        }

        if ($endpoint === '') {
            return ['ok' => false, 'status' => MailTransportProbeStatus::Unreachable->value];
        }

        $status = $this->tester->probe($endpoint, $token);

        return ['ok' => $status === MailTransportProbeStatus::Ok, 'status' => $status->value];
    }

    private function storedToken(): string
    {
        $raw = (string) $this->repository->value(SettingName::MailApiToken, '');

        if ($raw === '') {
            return '';
        }

        try {
            return $this->encrypter->decrypt($raw);
        } catch (Throwable) {
            return $raw;
        }
    }
}
