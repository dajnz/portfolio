<?php

declare(strict_types=1);

use App\Features\Analytics\Infra\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
 * Analytics API routes (routes.md:184,189,240,364). `POST
 * /api/analytics/track` is public — no session/CSRF middleware
 * (routes.md:184) — and rate-limited via `throttle:analytics`
 * (60/min/IP, analytics.md:52). Accepts an array of events and returns
 * 204 No Content (ADR-0017: bot-UA batches are discarded with the same 204).
 */

Route::post('/analytics/track', [AnalyticsController::class, 'track'])
    ->middleware('throttle:analytics');
