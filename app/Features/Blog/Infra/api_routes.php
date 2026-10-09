<?php

declare(strict_types=1);

use App\Features\Blog\Infra\Controllers\AdminTagController;
use App\Features\Blog\Infra\Controllers\PublicBlogController;
use Illuminate\Support\Facades\Route;

/*
 * Blog API routes (routes.md:190-197): the inline tag creation
 * (`POST /api/tags` — features/blog.yaml#createTag; auth required:
 * the session stack (`encrypt_cookies` + `start_session`) plus the
 * auth chain per routes.md:184,288-290 (ADR-0015)) and the step-8.2
 * public endpoints — the article-tags autocomplete, the unified
 * tag/skill autocomplete and the feature-flagged reaction toggle.
 */

Route::post('/tags', [AdminTagController::class, 'storeApi'])
    ->middleware(['encrypt_cookies', 'start_session', 'auth', 'auth.session_version']);

Route::get('/tags/search', [AdminTagController::class, 'search']);
Route::get('/blog-tags/search', [AdminTagController::class, 'searchUnified']);
Route::post('/blog/{article}/reaction', [PublicBlogController::class, 'reaction'])
    ->middleware('feature:social_reactions');
