<?php

declare(strict_types=1);

use App\Features\Portfolio\Infra\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

/*
 * `POST /api/feedback` is public — no session/CSRF middleware — and carries
 * the layered feedback protection: `throttle:feedback` (5/min/IP) plus
 * `rate_limit.fingerprint` (3/h per IP+UA key); the honeypot check runs in
 * the controller. Returns 201 with no body on success. Note: the `api` group
 * prefixes these paths with `/api`.
 */

Route::post('/feedback', [FeedbackController::class, 'store'])
    ->middleware(['throttle:feedback', 'rate_limit.fingerprint']);
