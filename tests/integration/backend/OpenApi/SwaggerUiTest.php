<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\OpenApi;

use Tests\Support\Traits\InteractsWithAdmin;

/**
 * GET /docs/api is served by l5-swagger in the local environment only,
 * behind the auth + admin middleware (`swagger.local` alias). The docs JSON
 * it serves is the dereferenced docs/openapi/openapi.yaml written by
 * SwaggerUiService. The local environment is simulated per test via
 * `detectEnvironment()` — the routes are registered unconditionally and the
 * gate runs at request time.
 */

uses(InteractsWithAdmin::class);

describe('in the local environment', function (): void {
    beforeEach(function (): void {
        $this->app->detectEnvironment(fn (): string => 'local');
    });

    it('redirects guests to the login page', function (): void {
        $this->get('/docs/api')->assertRedirect(route('login'));
    });

    it('serves the Swagger UI to an authenticated admin', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->get('/docs/api')
            ->assertOk()
            ->assertSee('Portfolio API');
    });

    it('serves the Swagger UI through a real session login', function (): void {
        $admin = $this->createAdmin();

        $this->get('/admin/login')->assertOk();
        $token = session()->token();

        $this->post('/admin/login', [
            'login' => $admin->login,
            'password' => self::ADMIN_PASSWORD,
            '_token' => $token,
        ])->assertRedirect('/admin');

        $this->get('/docs/api')->assertOk();
    });

    it('serves the dereferenced OpenAPI document to an authenticated admin', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $docsJson = $this->get('/docs')->assertOk()->json();

        expect($docsJson['info']['title'])->toBe('Portfolio API')
            ->and($docsJson['paths'])->toHaveKeys(['/api/upload', '/api/feedback', '/blog', '/projects'])
            ->and($docsJson['components']['schemas'])->toHaveKey('ErrorResponse');
    });

    it('keeps the l5-swagger docs file in sync with docs/openapi/openapi.yaml', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $served = $this->get('/docs')->assertOk()->getContent();

        $this->get('/docs/api')->assertOk();

        expect(json_decode($served, true)['paths'])
            ->toBe(json_decode((string) file_get_contents(storage_path('api-docs/api-docs.json')), true)['paths']);
    });
});

describe('outside the local environment', function (): void {
    it('404s the Swagger UI even for an authenticated admin', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->get('/docs/api')->assertNotFound();
    });
});
