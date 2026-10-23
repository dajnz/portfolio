<?php

declare(strict_types=1);

use App\Features\Resume\Infra\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;

/*
 * Resume API routes. `POST /api/resume/regenerate` is auth-required: the
 * session middleware stack (`encrypt_cookies`, `start_session`, `auth`)
 * is applied per-route so the cookieAuth session guard can authenticate
 * the request; `auth.session_version` force-logs-out when the session
 * version is bumped (password change / TOTP toggle).
 */

Route::middleware(['encrypt_cookies', 'start_session', 'auth', 'auth.session_version', 'auth.fingerprint'])->group(function (): void {
    Route::post('/resume/regenerate', [ResumeController::class, 'regenerate']);
});
