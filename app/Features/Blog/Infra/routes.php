<?php

declare(strict_types=1);

use App\Features\Blog\Infra\Controllers\AdminBlogController;
use App\Features\Blog\Infra\Controllers\AdminTagController;
use App\Features\Blog\Infra\Controllers\FeedController;
use App\Features\Blog\Infra\Controllers\PreviewController;
use App\Features\Blog\Infra\Controllers\PublicBlogController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;

/*
 * Blog routes (routes.md:27-28,55-56,110-128,149-155,218): the public
 * index/detail (with their locale-prefixed variants — the `{locale}`
 * parameter is constrained to the supported locales so
 * `SetLocaleFromPathMiddleware` never sees an invalid value; the
 * `{article}` detail parameter carries the RAW slug — B5: the
 * old-slug 301 lookup happens in the controller, implicit model
 * binding cannot do the fallback), the RSS feed, the admin section
 * (gated by the Auth middleware chain — `auth` + `auth.session_version`
 * + `admin`; the `{article}`/`{tag}` parameters carry RAW ids) and
 * the signed preview route (excluded from auth, routes.md:155).
 */

Route::get('/blog', [PublicBlogController::class, 'index']);
Route::get('/blog/{article}', [PublicBlogController::class, 'show']);
Route::get('/{locale}/blog', [PublicBlogController::class, 'index'])
    ->whereIn('locale', Config::get('portfolio.supported_locales', ['en', 'ru']));
Route::get('/{locale}/blog/{article}', [PublicBlogController::class, 'show'])
    ->whereIn('locale', Config::get('portfolio.supported_locales', ['en', 'ru']));

Route::get('/rss', [FeedController::class, 'rss']);

Route::middleware(['auth', 'auth.session_version', 'admin'])->group(function (): void {
    Route::get('/admin/blog', [AdminBlogController::class, 'index']);
    Route::get('/admin/blog/create', [AdminBlogController::class, 'create']);
    Route::post('/admin/blog', [AdminBlogController::class, 'store']);
    Route::get('/admin/blog/{article}/edit', [AdminBlogController::class, 'edit']);
    Route::put('/admin/blog/{article}', [AdminBlogController::class, 'update']);
    Route::delete('/admin/blog/{article}', [AdminBlogController::class, 'destroy']);
    Route::patch('/admin/blog/{article}/restore', [AdminBlogController::class, 'restore']);
    Route::delete('/admin/blog/{article}/force', [AdminBlogController::class, 'forceDestroy']);
    Route::patch('/admin/blog/{article}/publish', [AdminBlogController::class, 'publish']);
    Route::patch('/admin/blog/{article}/unpublish', [AdminBlogController::class, 'unpublish']);
    Route::post('/admin/blog/{article}/related/{relatedArticle}', [AdminBlogController::class, 'attachRelated']);
    Route::delete('/admin/blog/{article}/related/{relatedArticle}', [AdminBlogController::class, 'detachRelated']);
    Route::post('/admin/blog/{article}/skills/{skill}', [AdminBlogController::class, 'attachSkill']);
    Route::delete('/admin/blog/{article}/skills/{skill}', [AdminBlogController::class, 'detachSkill']);
    Route::post('/admin/blog/bulk-publish', [AdminBlogController::class, 'bulkPublish']);
    Route::post('/admin/blog/bulk-unpublish', [AdminBlogController::class, 'bulkUnpublish']);
    Route::post('/admin/blog/bulk-delete', [AdminBlogController::class, 'bulkDestroy']);
    Route::post('/admin/blog/bulk-restore', [AdminBlogController::class, 'bulkRestore']);
    Route::post('/admin/blog/bulk-force-delete', [AdminBlogController::class, 'bulkForceDestroy']);

    Route::get('/admin/tags', [AdminTagController::class, 'index']);
    Route::get('/admin/tags/create', [AdminTagController::class, 'create']);
    Route::post('/admin/tags', [AdminTagController::class, 'store']);
    Route::get('/admin/tags/{tag}/edit', [AdminTagController::class, 'edit']);
    Route::put('/admin/tags/{tag}', [AdminTagController::class, 'update']);
    Route::delete('/admin/tags/{tag}', [AdminTagController::class, 'destroy']);
});

Route::get('/admin/preview/blog/{article}', [PreviewController::class, 'article'])
    ->name('blog.preview');
