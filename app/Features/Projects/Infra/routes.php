<?php

declare(strict_types=1);

use App\Features\Projects\Infra\Controllers\AdminProjectController;
use App\Features\Projects\Infra\Controllers\PreviewController;
use App\Features\Projects\Infra\Controllers\PublicProjectController;
use Illuminate\Support\Facades\Route;

/*
 * Projects routes. The public index (with its locale-prefixed variant)
 * and detail are public — the `{project}` parameter carries the RAW
 * slug (the old-slug 301 lookup happens in the controller, implicit
 * model binding cannot do the fallback); the admin section is gated by
 * the Auth middleware chain (`auth` + `auth.session_version` +
 * `admin`); the preview route is excluded from auth and relies on the
 * signed URL. The `{locale}` route parameter is constrained to the
 * supported locales so `SetLocaleFromPathMiddleware` never sees an
 * invalid value.
 */

Route::get('/projects', [PublicProjectController::class, 'index'])
    ->middleware('validate_api_spec');
Route::get('/projects/{project}', [PublicProjectController::class, 'show']);
Route::get('/{locale}/projects', [PublicProjectController::class, 'index'])
    ->whereIn('locale', config('portfolio.supported_locales', ['en', 'ru']));
Route::get('/{locale}/projects/{project}', [PublicProjectController::class, 'show'])
    ->whereIn('locale', config('portfolio.supported_locales', ['en', 'ru']));

Route::middleware(['auth', 'auth.session_version', 'admin'])->group(function (): void {
    Route::get('/admin/projects', [AdminProjectController::class, 'index']);
    Route::get('/admin/projects/create', [AdminProjectController::class, 'create']);
    Route::post('/admin/projects', [AdminProjectController::class, 'store']);
    Route::get('/admin/projects/{project}/edit', [AdminProjectController::class, 'edit']);
    Route::put('/admin/projects/{project}', [AdminProjectController::class, 'update']);
    Route::delete('/admin/projects/{project}', [AdminProjectController::class, 'destroy']);
    Route::patch('/admin/projects/{project}/restore', [AdminProjectController::class, 'restore']);
    Route::delete('/admin/projects/{project}/force', [AdminProjectController::class, 'forceDestroy']);
    Route::post('/admin/projects/reorder-featured', [AdminProjectController::class, 'reorderFeatured']);
    Route::patch('/admin/projects/{project}/feature', [AdminProjectController::class, 'feature']);
    Route::patch('/admin/projects/{project}/unfeature', [AdminProjectController::class, 'unfeature']);
    Route::patch('/admin/projects/{project}/tech-writeup', [AdminProjectController::class, 'updateTechWriteup']);
    Route::post('/admin/projects/{project}/tags', [AdminProjectController::class, 'attachTag']);
    Route::delete('/admin/projects/{project}/tags/{skill}', [AdminProjectController::class, 'detachTag']);
    Route::patch('/admin/projects/{project}/publish', [AdminProjectController::class, 'publish']);
    Route::patch('/admin/projects/{project}/unpublish', [AdminProjectController::class, 'unpublish']);
    Route::post('/admin/projects/bulk-publish', [AdminProjectController::class, 'bulkPublish']);
    Route::post('/admin/projects/bulk-unpublish', [AdminProjectController::class, 'bulkUnpublish']);
    Route::post('/admin/projects/bulk-delete', [AdminProjectController::class, 'bulkDestroy']);
    Route::post('/admin/projects/bulk-restore', [AdminProjectController::class, 'bulkRestore']);
    Route::post('/admin/projects/bulk-force-delete', [AdminProjectController::class, 'bulkForceDestroy']);
});

Route::get('/admin/preview/projects/{project}', [PreviewController::class, 'project'])
    ->name('projects.preview');
