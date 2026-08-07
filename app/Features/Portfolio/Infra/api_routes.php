<?php

declare(strict_types=1);

use App\Features\Portfolio\Infra\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

/*
 * Portfolio API routes (routes.md:188,237,244,362). `POST /api/feedback`
 * is public — no session/CSRF middleware (routes.md:184) — and carries
 * the layered feedback protection: `throttle:feedback` (5/min/IP, layer
 * 1) + `rate_limit.fingerprint` (3/h per IP+UA key, layer 2); the
 * honeypot check runs in the controller (layer 3, authentication.md:
 * 333-351). Returns 201 with no body on success. Note: the `api` group
 * prefixes these paths with `/api`.
 */

Route::post('/feedback', [FeedbackController::class, 'store'])
    ->middleware(['throttle:feedback', 'rate_limit.fingerprint']);
