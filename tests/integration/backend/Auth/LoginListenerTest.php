<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Auth;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('LoginListener (remember-me)', function (): void {
    it('repopulates the session version on recaller-based authentication', function (): void {
        $admin = $this->createAdmin(['session_version' => 3]);

        Auth::shouldReceive('viaRemember')->once()->andReturn(true);

        event(new Login('web', $admin, false));

        expect(session('session_version'))->toBe(3);
    });

    it('does nothing for regular logins', function (): void {
        $admin = $this->createAdmin(['session_version' => 3]);

        Auth::shouldReceive('viaRemember')->once()->andReturn(false);

        event(new Login('web', $admin, false));

        expect(session('session_version'))->toBeNull();
    });

    it('is registered as the Login event listener', function (): void {
        expect(app('events')->hasListeners(Login::class))->toBeTrue();
    });
});
