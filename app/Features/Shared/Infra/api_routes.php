<?php

declare(strict_types=1);

use App\Features\Shared\Infra\Controllers\SkillSearchController;
use App\Features\Shared\Infra\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/skills/search', [SkillSearchController::class, 'index']);

/*
 * Auth-required upload endpoints. The session middleware stack
 * (`encrypt_cookies`, `start_session`, `auth`) is applied here per-route so the
 * cookieAuth session guard can authenticate the request; `auth.session_version`
 * force-logs-out when the session version is bumped.
 */

Route::middleware(['encrypt_cookies', 'start_session', 'auth', 'auth.session_version', 'auth.fingerprint'])->group(function (): void {
    Route::post('/upload', [UploadController::class, 'store']);
    Route::delete('/upload', [UploadController::class, 'destroy']);
});
