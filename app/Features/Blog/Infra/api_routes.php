<?php

declare(strict_types=1);

use App\Features\Blog\Infra\Controllers\AdminTagController;
use App\Features\Blog\Infra\Controllers\PublicBlogController;
use Illuminate\Support\Facades\Route;

/*
 * Blog API routes: the inline tag creation (`POST /api/tags` — auth
 * required: the session stack (`encrypt_cookies` + `start_session`)
 * plus the auth chain) and the public endpoints — the article-tags
 * autocomplete, the unified tag/skill autocomplete and the
 * feature-flagged reaction toggle.
 *
 * `locale` is applied per route (the `api` group itself omits it) so
 * the tag autocompletes resolve the visitor's language: the
 * locale-resolved tag names and the localized-name matching depend on
 * the request locale.
 */

Route::post('/tags', [AdminTagController::class, 'storeApi'])
    ->middleware(['encrypt_cookies', 'start_session', 'auth', 'auth.session_version', 'auth.fingerprint']);

Route::get('/tags/search', [AdminTagController::class, 'search'])
    ->middleware('locale');
Route::get('/blog-tags/search', [AdminTagController::class, 'searchUnified'])
    ->middleware('locale');
Route::post('/blog/{article}/reaction', [PublicBlogController::class, 'reaction'])
    ->middleware('feature:social_reactions');
