<?php

declare(strict_types=1);

use App\Features\Blog\Infra\Controllers\AdminTagController;
use Illuminate\Support\Facades\Route;

/*
 * Blog API routes (routes.md:190-197): the inline tag creation
 * (`POST /api/tags` — features/blog.yaml#createTag). Auth required:
 * the session stack (`encrypt_cookies` + `start_session`) plus the
 * auth chain per routes.md:184,288-290 (B68). The public search
 * endpoints (`/api/tags/search`, `/api/blog-tags/search`) land with
 * step 8.2.
 */

Route::post('/tags', [AdminTagController::class, 'storeApi'])
    ->middleware(['encrypt_cookies', 'start_session', 'auth', 'auth.session_version']);
