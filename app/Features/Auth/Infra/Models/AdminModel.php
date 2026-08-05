<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Authenticatable model for the `admins` table — the sole credential
 * store (authentication.md:63-96). Extending `Illuminate\Foundation\Auth\User`
 * provides the `Authenticatable` contract plus remember-me support.
 *
 * Encrypted-at-rest columns (`totp_secret`, `github_token`) are decrypted
 * by `AdminMapper` — the model never exposes plaintext secrets.
 */
final class AdminModel extends Authenticatable
{
    use HasFactory;

    protected $table = 'admins';

    protected static function newFactory(): Factory
    {
        return AdminFactory::new();
    }

    protected $fillable = [
        'name',
        'email',
        'login',
        'password',
        'github_id',
        'github_token',
        'totp_secret',
        'totp_recovery_codes',
        'totp_enabled',
        'totp_failed_attempts',
        'totp_locked_until',
        'session_version',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
        'totp_recovery_codes',
        'github_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'totp_enabled' => 'boolean',
            'totp_recovery_codes' => 'array',
            'totp_locked_until' => 'datetime',
            'session_version' => 'integer',
        ];
    }
}
