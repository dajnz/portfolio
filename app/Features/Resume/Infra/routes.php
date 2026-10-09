<?php

declare(strict_types=1);

use App\Features\Resume\Infra\Controllers\PublicResumeController;
use App\Features\Resume\Infra\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;

/*
 * Resume routes. The public `/cv` endpoint serves the PDF file directly
 * (no Inertia page); the `{locale}` variant is constrained to the
 * supported locales so `SetLocaleFromPathMiddleware` never sees an invalid
 * value. The admin section is gated by the Auth middleware chain (`auth` +
 * `auth.session_version` + `admin`).
 */

Route::get('/cv', [PublicResumeController::class, 'download']);
Route::get('/{locale}/cv', [PublicResumeController::class, 'download'])
    ->whereIn('locale', config('portfolio.supported_locales', ['en', 'ru']));

Route::middleware(['auth', 'auth.session_version', 'admin'])->group(function (): void {
    Route::get('/admin/resume', [ResumeController::class, 'edit']);
    Route::put('/admin/resume', [ResumeController::class, 'update']);
});
