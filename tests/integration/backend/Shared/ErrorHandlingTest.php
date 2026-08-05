<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Blog\Infra\Models\BlogArticleModel;
use App\Features\Settings\Infra\Models\SettingModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use RuntimeException;

describe('ErrorHandling', function (): void {
    it('renders the Blade 404 view with a noindex meta for non-Inertia requests', function (): void {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee(__('errors.404'))
            ->assertSee(__('errors.back_home'))
            ->assertDontSee('"component"');
    });

    it('returns the ErrorPage Inertia response for X-Inertia 404s', function (): void {
        $response = $this->get('/no-such-page', ['X-Inertia' => 'true']);

        $response->assertStatus(404)->assertHeader('X-Inertia', 'true');

        $page = json_decode($response->getContent(), true);

        expect($page['component'])->toBe('Shared/CrossSurface/Pages/ErrorPage')
            ->and($page['props']['status'])->toBe(404)
            ->and($page['props']['message'])->toBe(__('errors.not_found'))
            ->and($page['props']['request_id'])->toMatch(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/'
            );
    });

    it('returns the JSON envelope for API 404s', function (): void {
        $this->getJson('/api/no-such-endpoint')
            ->assertNotFound()
            ->assertExactJson(['message' => __('errors.not_found')]);
    });

    it('maps ModelNotFoundException to an entity-not-found message', function (): void {
        Route::get('/_test/missing-model', function (): void {
            throw (new ModelNotFoundException())->setModel(BlogArticleModel::class);
        });

        $this->getJson('/_test/missing-model')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Blog Article not found.']);
    });

    it('maps 403/419/422/503 HttpExceptions to the errors group messages', function (): void {
        Route::get('/_test/error-403', fn () => abort(403));
        Route::get('/_test/error-419', fn () => abort(419));
        Route::get('/_test/error-422', fn () => abort(422));
        Route::get('/_test/error-503', fn () => abort(503));
        config(['app.debug' => false]);

        $this->getJson('/_test/error-403')->assertStatus(403)->assertExactJson(['message' => __('errors.unauthorized')]);
        $this->getJson('/_test/error-419')->assertStatus(419)->assertExactJson(['message' => __('errors.page_expired')]);
        $this->getJson('/_test/error-422')->assertStatus(422)->assertExactJson(['message' => __('errors.validation_failed')]);
        $this->getJson('/_test/error-503')->assertStatus(503)->assertExactJson(['message' => __('errors.service_unavailable')]);
    });

    it('renders Blade views for the non-Inertia error family', function (): void {
        Route::get('/_test/error-403', fn () => abort(403));
        Route::get('/_test/error-419', fn () => abort(419));
        Route::get('/_test/error-429', fn () => abort(429));
        Route::get('/_test/error-503', fn () => abort(503));

        $this->get('/_test/error-403')->assertStatus(403)->assertSee(__('errors.403'));
        $this->get('/_test/error-419')->assertStatus(419)->assertSee(__('errors.419'));
        $this->get('/_test/error-429')->assertStatus(429)->assertSee(__('errors.429'));
        $this->get('/_test/error-503')->assertStatus(503)->assertSee(__('errors.503'));
    });

    it('maps ThrottleRequestsException to 429 with the rate-limit message', function (): void {
        Route::get('/_test/throttled', fn () => 'ok')->middleware('throttle:2,1');

        $this->getJson('/_test/throttled')->assertOk();
        $this->getJson('/_test/throttled')->assertOk();
        $this->getJson('/_test/throttled')
            ->assertStatus(429)
            ->assertExactJson(['message' => __('errors.too_many_attempts')]);
    });

    it('maps method-not-allowed to 405', function (): void {
        $this->putJson('/admin/login')
            ->assertStatus(405)
            ->assertExactJson(['message' => __('errors.method_not_allowed')]);
    });

    it('returns 401 for JSON requests and redirects to login otherwise', function (): void {
        $this->getJson('/admin/settings')
            ->assertStatus(401)
            ->assertExactJson(['message' => __('errors.unauthenticated')]);

        $this->get('/admin/settings')->assertRedirect(route('login'));
        $this->get('/admin/settings', ['X-Inertia' => 'true'])->assertRedirect(route('login'));
    });

    it('hides exception details in production for JSON 500s', function (): void {
        Route::get('/_test/boom', function (): void {
            throw new RuntimeException('top-secret-details');
        });
        config(['app.debug' => false]);

        $this->getJson('/_test/boom')
            ->assertStatus(500)
            ->assertExactJson(['message' => __('errors.server_error')])
            ->assertDontSee('top-secret-details');
    });

    it('exposes exception details in debug mode for JSON 500s', function (): void {
        Route::get('/_test/boom', function (): void {
            throw new RuntimeException('top-secret-details');
        });
        config(['app.debug' => true]);

        $this->getJson('/_test/boom')
            ->assertStatus(500)
            ->assertSee('top-secret-details');
    });

    it('renders the Blade 500 view without details when debug is off', function (): void {
        Route::get('/_test/boom', function (): void {
            throw new RuntimeException('top-secret-details');
        });
        config(['app.debug' => false]);

        $this->get('/_test/boom')
            ->assertStatus(500)
            ->assertSee(__('errors.500'))
            ->assertDontSee('top-secret-details');
    });

    it('renders the reference ID on the Blade 500 view', function (): void {
        Route::get('/_test/boom-web', function (): void {
            throw new RuntimeException('boom');
        })->middleware('web');
        config(['app.debug' => false]);

        $this->get('/_test/boom-web')
            ->assertStatus(500)
            ->assertSee('Reference ID: ', false);
    });

    it('returns the ErrorPage for Inertia 500s when debug is off', function (): void {
        Route::get('/_test/boom-inertia', function (): void {
            throw new RuntimeException('boom');
        })->middleware('web');
        config(['app.debug' => false]);

        // The real Inertia client always sends its asset version; without
        // it the Inertia middleware answers 409 (version change). Mirror
        // the middleware's own computation (`Middleware::version()`).
        $manifest = public_path('build/manifest.json');
        $inertiaVersion = file_exists($manifest) ? (string) hash_file('xxh128', $manifest) : '';

        $response = $this->get('/_test/boom-inertia', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $inertiaVersion,
        ]);

        $response->assertStatus(500)->assertHeader('X-Inertia', 'true');

        $page = json_decode($response->getContent(), true);

        expect($page['component'])->toBe('Shared/CrossSurface/Pages/ErrorPage')
            ->and($page['props']['status'])->toBe(500)
            ->and($page['props']['message'])->toBe(__('errors.server_error'))
            ->and($page['props']['request_id'])->toMatch(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/'
            );
    });

    it('returns the ErrorPage for X-Inertia requests during maintenance', function (): void {
        SettingModel::query()->updateOrCreate(
            ['key' => 'site_maintenance_mode'],
            ['value' => '1', 'type' => 'boolean'],
        );
        config(['app.debug' => false]);

        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertStatus(503)->assertHeader('X-Inertia', 'true');

        $page = json_decode($response->getContent(), true);

        expect($page['component'])->toBe('Shared/CrossSurface/Pages/ErrorPage')
            ->and($page['props']['status'])->toBe(503)
            ->and($page['props']['message'])->toBe(__('errors.service_unavailable'));
    });
});
