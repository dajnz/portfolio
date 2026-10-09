<?php

declare(strict_types=1);

use App\Features\Shared\Infra\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
 * Shared web routes: the static/feed endpoints owned by the Shared feature.
 * The health endpoints (`/up`, `/up/health`) are registered in bootstrap/app.php
 * outside any middleware group.
 */

Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/robots.txt', [SitemapController::class, 'robots']);
