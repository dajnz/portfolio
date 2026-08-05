<?php

declare(strict_types=1);

use App\Features\Auth\Domain\ValueObjects\TotpSecret;

describe('TotpSecret', function (): void {
    it('accepts a valid unpadded base32 secret', function (): void {
        $secret = new TotpSecret('JBSWY3DPEHPK3PXP');

        expect($secret->toString())->toBe('JBSWY3DPEHPK3PXP')
            ->and($secret->equals(new TotpSecret('JBSWY3DPEHPK3PXP')))->toBeTrue();
    });

    it('rejects lowercase, padded and malformed secrets', function (): void {
        expect(fn () => new TotpSecret('jbswy3dpehpk3pxp'))->toThrow(InvalidArgumentException::class);
        expect(fn () => new TotpSecret('JBSWY3DPEHPK3PXP='))->toThrow(InvalidArgumentException::class);
        expect(fn () => new TotpSecret('SHORT'))->toThrow(InvalidArgumentException::class);
        expect(fn () => new TotpSecret(''))->toThrow(InvalidArgumentException::class);
        expect(fn () => new TotpSecret('JBSWY3DPEHPK3PXP1'))->toThrow(InvalidArgumentException::class);
    });
});
