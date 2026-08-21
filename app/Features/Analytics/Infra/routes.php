<?php

declare(strict_types=1);

use App\Features\Analytics\Infra\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
 * Analytics admin routes (routes.md:136-139): the dashboard Inertia
 * page, the chart-series and ranking JSON endpoints and the "Generate
 * Now" full-rebuild dispatch — all gated by the Auth middleware chain
 * (`auth` + `auth.session_version` + `admin`). The public event
 * ingestion lives in `api_routes.php`.
 */

Route::middleware(['auth', 'auth.session_version', 'admin'])->group(function (): void {
    Route::get('/admin/analytics', [AnalyticsController::class, 'index']);
    Route::get('/admin/analytics/series', [AnalyticsController::class, 'series']);
    Route::get('/admin/analytics/rankings', [AnalyticsController::class, 'rankings']);
    Route::post('/admin/analytics/generate-reports', [AnalyticsController::class, 'generate']);
});
