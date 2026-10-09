<?php

declare(strict_types=1);

use App\Features\Portfolio\Infra\Controllers\AboutController;
use App\Features\Portfolio\Infra\Controllers\ContactController;
use App\Features\Portfolio\Infra\Controllers\DashboardController;
use App\Features\Portfolio\Infra\Controllers\FeedbackController;
use App\Features\Portfolio\Infra\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

/*
 * Portfolio routes. The landing root and the locale-prefixed variant are
 * public; the admin section is gated by the Auth middleware chain (`auth` +
 * `auth.session_version` + `admin`). The `{locale}` route parameter is
 * constrained to the supported locales so `SetLocaleFromPathMiddleware`
 * never sees an invalid value.
 */

Route::get('/', [LandingController::class, 'index']);
Route::get('/{locale}', [LandingController::class, 'index'])
    ->whereIn('locale', config('portfolio.supported_locales', ['en', 'ru']));

Route::middleware(['auth', 'auth.session_version', 'admin'])->group(function (): void {
    Route::get('/admin', [DashboardController::class, 'index']);

    Route::get('/admin/about', [AboutController::class, 'edit']);
    Route::put('/admin/about', [AboutController::class, 'update']);

    Route::get('/admin/contacts', [ContactController::class, 'index']);
    Route::post('/admin/contacts', [ContactController::class, 'store']);
    Route::put('/admin/contacts/{contact}', [ContactController::class, 'update']);
    Route::delete('/admin/contacts/{contact}', [ContactController::class, 'destroy']);
    Route::post('/admin/contacts/reorder', [ContactController::class, 'reorder']);

    Route::get('/admin/feedback', [FeedbackController::class, 'index']);
    Route::get('/admin/feedback/{feedback}', [FeedbackController::class, 'show']);
    Route::patch('/admin/feedback/{feedback}/read', [FeedbackController::class, 'markRead']);
    Route::patch('/admin/feedback/{feedback}/forwarded', [FeedbackController::class, 'markForwarded']);
    Route::delete('/admin/feedback/{feedback}', [FeedbackController::class, 'destroy']);
});
