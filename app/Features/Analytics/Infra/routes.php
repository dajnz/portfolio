<?php

declare(strict_types=1);

use App\Features\Analytics\Infra\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
 * Analytics web routes (routes.md:136; B220 gap-fill). The dashboard
 * and the "Generate Now" endpoint sit behind the Auth middleware chain
 * (`auth` + `auth.session_version` + `admin`) like every `/admin/*`
 * route; generate-reports is a JSON 202 (the job runs async, the UI
 * reloads the dashboard on completion).
 */

Route::middleware(['auth', 'auth.session_version', 'admin'])->group(function (): void {
    Route::get('/admin/analytics', [AnalyticsController::class, 'index']);
    Route::post('/admin/analytics/generate-reports', [AnalyticsController::class, 'generate']);
});
