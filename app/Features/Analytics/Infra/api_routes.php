<?php

declare(strict_types=1);

use App\Features\Analytics\Infra\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
 * Analytics API routes. `POST /api/analytics/track` is public — no
 * session/CSRF middleware — and rate-limited via `throttle:analytics`
 * (60/min/IP). Accepts an array of events and returns 204 No Content
 * (bot-UA batches are discarded with the same 204).
 */

Route::post('/analytics/track', [AnalyticsController::class, 'track'])
    ->middleware('throttle:analytics');
