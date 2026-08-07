<?php

declare(strict_types=1);

use App\Features\Settings\Infra\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
 * Admin settings routes (routes.md:137-139), gated by the Auth middleware
 * chain (aliases land in step 2.3 per B50).
 */

Route::middleware(['auth', 'auth.session_version', 'admin'])->group(function (): void {
    Route::get('/admin/settings', [SettingsController::class, 'index']);
    Route::put('/admin/settings', [SettingsController::class, 'update']);
    Route::patch('/admin/settings/feature-flags', [SettingsController::class, 'updateFeatureFlags']);
    Route::post('/admin/settings/test-mail', [SettingsController::class, 'testMail']);
});
