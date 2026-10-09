<?php

declare(strict_types=1);

use App\Features\Experience\Infra\Controllers\AdminExperienceController;
use App\Features\Experience\Infra\Controllers\PublicExperienceController;
use Illuminate\Support\Facades\Route;

/*
 * Experience routes. The public timeline and its locale-prefixed variant
 * are public; the admin section is gated by the Auth middleware chain
 * (`auth` + `auth.session_version` + `admin`). The `{locale}` route
 * parameter is constrained to the supported locales so
 * `SetLocaleFromPathMiddleware` never sees an invalid value.
 */

Route::get('/experience', [PublicExperienceController::class, 'index']);
Route::get('/{locale}/experience', [PublicExperienceController::class, 'index'])
    ->whereIn('locale', config('portfolio.supported_locales', ['en', 'ru']));

Route::middleware(['auth', 'auth.session_version', 'admin'])->group(function (): void {
    Route::get('/admin/experience', [AdminExperienceController::class, 'index']);
    Route::get('/admin/experience/create', [AdminExperienceController::class, 'create']);
    Route::post('/admin/experience', [AdminExperienceController::class, 'store']);
    Route::get('/admin/experience/{item}/edit', [AdminExperienceController::class, 'edit']);
    Route::put('/admin/experience/{item}', [AdminExperienceController::class, 'update']);
    Route::delete('/admin/experience/{item}', [AdminExperienceController::class, 'destroy']);
    Route::patch('/admin/experience/{item}/restore', [AdminExperienceController::class, 'restore']);
    Route::delete('/admin/experience/{item}/force', [AdminExperienceController::class, 'forceDestroy']);
});
