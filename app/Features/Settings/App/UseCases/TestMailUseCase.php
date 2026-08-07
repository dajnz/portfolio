<?php

declare(strict_types=1);

namespace App\Features\Settings\App\UseCases;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The B20/B125 "Test Connection" probe: checks that the 3rd-party mail
 * API is reachable and accepts the token — without sending any email.
 *
 * The submitted Mail-tab values override the stored settings (so the
 * probe works on unsaved form state); an empty token submission falls
 * back to the stored token (B49). The probe is a `GET {endpoint}` with
 * the bearer token (10 s timeout):
 *  - 401/403  -> `unauthorized` (token rejected);
 *  - network/timeout errors -> `unreachable`;
 *  - any other response -> `ok` (endpoint reachable, credentials
 *    accepted — 404/405/429 etc. still prove connectivity).
 */
final class TestMailUseCase
{
    public function __construct(
        private readonly SettingRepositoryInterface $repository,
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
            return ['ok' => false, 'status' => 'unreachable'];
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->acceptJson()
                ->get($endpoint);
        } catch (ConnectionException) {
            return ['ok' => false, 'status' => 'unreachable'];
        } catch (Throwable) {
            return ['ok' => false, 'status' => 'unreachable'];
        }

        if ($response->unauthorized() || $response->forbidden()) {
            return ['ok' => false, 'status' => 'unauthorized'];
        }

        return ['ok' => true, 'status' => 'ok'];
    }

    private function storedToken(): string
    {
        $raw = (string) $this->repository->value(SettingName::MailApiToken, '');

        if ($raw === '') {
            return '';
        }

        try {
            return (string) Crypt::decryptString($raw);
        } catch (Throwable) {
            return $raw;
        }
    }
}
