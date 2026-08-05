<?php

declare(strict_types=1);

use App\Features\Auth\Infra\Controllers\GithubController;
use App\Features\Auth\Infra\Controllers\LoginController;
use App\Features\Auth\Infra\Controllers\ProfileController;
use App\Features\Auth\Infra\Controllers\TotpController;
use Illuminate\Support\Facades\Route;

/*
 * Auth routes (routes.md:29-36,157-167).
 *
 * `GET /admin/login` is the single route named `login` — the `auth`
 * middleware redirects unauthenticated requests to it. The TOTP routes use
 * the half-auth middlewares (`totp.pending` / `totp.pending.github_link`),
 * not `auth` (authentication.md:42,264-271). OAuth routes are gated by the
 * `feature:github_oauth` flag.
 */

Route::get('/admin/login', [LoginController::class, 'create'])->name('login');
Route::post('/admin/login', [LoginController::class, 'store'])->middleware('throttle:login');
Route::post('/admin/logout', [LoginController::class, 'destroy'])->middleware('auth');

Route::get('/admin/login/github', [GithubController::class, 'redirect'])->middleware('feature:github_oauth');
Route::get('/admin/login/github/callback', [GithubController::class, 'callback'])->middleware('feature:github_oauth');

Route::get('/admin/login/totp', [TotpController::class, 'create'])->middleware('totp.pending')->name('login.totp');
Route::post('/admin/login/totp', [TotpController::class, 'verify'])->middleware(['totp.pending', 'throttle:totp']);

Route::middleware(['auth', 'auth.session_version', 'admin'])->group(function (): void {
    Route::get('/admin/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/admin/profile', [ProfileController::class, 'update']);
    Route::post('/admin/profile/totp-enable', [ProfileController::class, 'enableTotp']);
    Route::post('/admin/profile/totp-disable', [ProfileController::class, 'disableTotp']);
    Route::post('/admin/profile/totp-recovery-codes', [ProfileController::class, 'regenerateRecoveryCodes']);
    Route::post('/admin/profile/github-unlink', [ProfileController::class, 'unlinkGithub']);
    Route::get('/admin/profile/github-link', [GithubController::class, 'linkRedirect'])->middleware('feature:github_oauth');
});

Route::get('/admin/profile/github-link/totp', [TotpController::class, 'createGithubLink'])
    ->middleware(['totp.pending.github_link', 'feature:github_oauth'])
    ->name('profile.github-link.totp');
Route::post('/admin/profile/github-link/totp', [TotpController::class, 'verifyGithubLink'])
    ->middleware(['totp.pending.github_link', 'feature:github_oauth', 'throttle:totp']);
