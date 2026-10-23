<?php

declare(strict_types=1);

use App\Features\Auth\Infra\Services\LaravelSessionFingerprint;

describe('LaravelSessionFingerprint', function (): void {
    it('keeps addresses inside the same IPv4 /24 on the same fingerprint', function (): void {
        $fingerprint = new LaravelSessionFingerprint('test-key');

        expect($fingerprint->for('192.0.2.10', 'browser'))
            ->toBe($fingerprint->for('192.0.2.200', 'browser'));
    });

    it('separates different IPv4 /24 networks', function (): void {
        $fingerprint = new LaravelSessionFingerprint('test-key');

        expect($fingerprint->for('192.0.2.10', 'browser'))
            ->not->toBe($fingerprint->for('192.0.3.10', 'browser'));
    });

    it('keeps addresses inside the same IPv6 /64 on the same fingerprint', function (): void {
        $fingerprint = new LaravelSessionFingerprint('test-key');

        expect($fingerprint->for('2001:db8:abcd:12::1', 'browser'))
            ->toBe($fingerprint->for('2001:db8:abcd:12:ffff::2', 'browser'));
    });

    it('separates different IPv6 /64 networks', function (): void {
        $fingerprint = new LaravelSessionFingerprint('test-key');

        expect($fingerprint->for('2001:db8:abcd:12::1', 'browser'))
            ->not->toBe($fingerprint->for('2001:db8:abcd:13::1', 'browser'));
    });

    it('treats an IPv4-mapped IPv6 address as its IPv4 network', function (): void {
        $fingerprint = new LaravelSessionFingerprint('test-key');

        expect($fingerprint->for('::ffff:192.0.2.10', 'browser'))
            ->toBe($fingerprint->for('192.0.2.55', 'browser'));
    });

    it('changes when the user agent or the key changes', function (): void {
        $fingerprint = new LaravelSessionFingerprint('test-key');

        expect($fingerprint->for('192.0.2.10', 'browser-a'))
            ->not->toBe($fingerprint->for('192.0.2.10', 'browser-b'))
            ->and((new LaravelSessionFingerprint('other-key'))->for('192.0.2.10', 'browser-a'))
            ->not->toBe($fingerprint->for('192.0.2.10', 'browser-a'));
    });

    it('falls back to the raw address when it cannot be parsed', function (): void {
        $fingerprint = new LaravelSessionFingerprint('test-key');

        expect($fingerprint->for('not-an-ip', 'browser'))
            ->not->toBe($fingerprint->for('other-value', 'browser'));
    });
});
