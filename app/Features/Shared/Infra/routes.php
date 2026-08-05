<?php

declare(strict_types=1);

use App\Features\Shared\Infra\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
 * Shared web routes (routes.md:218-222): the static/feed endpoints owned
 * by the Shared feature. The health endpoints (`/up`, `/up/health`) are
 * registered in bootstrap/app.php outside any middleware group
 * (devops.md:815-824).
 */

Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/robots.txt', [SitemapController::class, 'robots']);
