<?php

declare(strict_types=1);

use App\Features\Backup\Infra\Controllers\BackupController;
use App\Features\Backup\Infra\Controllers\EncryptionKeyController;
use Illuminate\Support\Facades\Route;

/*
 * Admin backup routes (routes.md:143-151,173), gated by the Auth
 * middleware chain. The restore/upload flows dispatch the async
 * `RestoreBackupJob` (ADR-0018) — the UI polls the status endpoint.
 */

Route::middleware(['auth', 'auth.session_version', 'admin'])->group(function (): void {
    Route::get('/admin/backups', [BackupController::class, 'index']);
    Route::post('/admin/backups', [BackupController::class, 'create']);
    Route::delete('/admin/backups/{backup}', [BackupController::class, 'destroy']);
    Route::post('/admin/backups/restore', [BackupController::class, 'restore']);
    Route::post('/admin/backups/upload', [BackupController::class, 'uploadRestore']);
    Route::get('/admin/backups/{backup}/download', [BackupController::class, 'download']);
    Route::get('/admin/backups/{backup}/status', [BackupController::class, 'status']);

    Route::get('/admin/backups/encryption-keys', [EncryptionKeyController::class, 'index']);
    Route::post('/admin/backups/encryption-keys', [EncryptionKeyController::class, 'store']);
    Route::delete('/admin/backups/encryption-keys/{key}', [EncryptionKeyController::class, 'destroy']);
});
