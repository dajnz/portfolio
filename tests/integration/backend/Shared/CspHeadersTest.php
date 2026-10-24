<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

describe('CspHeaders', function (): void {
    it('sends a nonce-based CSP on public pages', function (): void {
        $response = $this->get('/')->assertOk();

        $policy = (string) $response->headers->get('Content-Security-Policy');

        expect($policy)->toContain("default-src 'self'")
            ->and($policy)->toContain("object-src 'none'")
            ->and($policy)->toContain("frame-ancestors 'none'")
            ->and($policy)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]{24}'/");
    });

    it('sends the CSP on error pages rendered outside the web group', function (): void {
        $this->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Security-Policy');
    });

    it('does not send the CSP on API responses', function (): void {
        $this->getJson('/api/skills/search?q=php')
            ->assertOk()
            ->assertHeaderMissing('Content-Security-Policy');
    });

    it('does not send the CSP on the health endpoints (ADR-0015)', function (): void {
        $this->get('/up')
            ->assertOk()
            ->assertHeaderMissing('Content-Security-Policy');
    });
});
