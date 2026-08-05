<?php

declare(strict_types=1);

use App\Features\Auth\Infra\Rules\TotpOrRecoveryCodeRule;

function assertTotpCodeValidity(mixed $value, bool $expected): void
{
    $failed = false;
    $rule = new TotpOrRecoveryCodeRule();

    $rule->validate('code', $value, function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBe(! $expected);
}

describe('TotpOrRecoveryCodeRule', function (): void {
    it('accepts a 6-digit TOTP code', function (): void {
        assertTotpCodeValidity('123456', true);
        assertTotpCodeValidity('000000', true);
    });

    it('accepts recovery codes with and without dashes', function (): void {
        assertTotpCodeValidity('ABCD-EFGH-IJKL', true);
        assertTotpCodeValidity('ABCDEFGHIJKL', true);
        assertTotpCodeValidity('abcd-efgh-ijkl', true);
    });

    it('rejects malformed codes', function (): void {
        assertTotpCodeValidity('12345', false);
        assertTotpCodeValidity('1234567', true);
        assertTotpCodeValidity('ABCD EFGH', false);
        assertTotpCodeValidity('!!!', false);
        assertTotpCodeValidity('', false);
        assertTotpCodeValidity(str_repeat('A', 65), false);
        assertTotpCodeValidity(null, false);
    });
});
