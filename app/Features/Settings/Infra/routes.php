<?php

declare(strict_types=1);

use App\Features\Settings\Infra\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
 * Admin settings routes (routes.md:137-139).
 *
 * NOTE: `auth` + `auth.session_version` + `admin` gating lands with the
 * Auth feature (step 2.3) and the middleware wiring (step 2.4) — the
 * aliases do not exist yet.
 */

Route::get('/admin/settings', [SettingsController::class, 'index']);
Route::put('/admin/settings', [SettingsController::class, 'update']);
Route::patch('/admin/settings/feature-flags', [SettingsController::class, 'updateFeatureFlags']);
