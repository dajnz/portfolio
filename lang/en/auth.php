<?php

declare(strict_types=1);

/*
| Auth backend messages (step 2.3). Static strings flashed to the user
| from the Auth feature — the full catalogs are authored in step 2.6 (B10).
*/

return [
    'login_failed' => 'These credentials do not match our records.',
    'session_expired' => 'Session expired — please log in again.',
    'totp_verification_expired' => 'TOTP verification expired.',
    'totp_locked_minutes' => 'Too many attempts — try again in :minutes minutes.',
    'totp_locked_permanent' => 'Account locked — use `admin:reset-password` to regain access.',
    'invalid_totp_code' => 'The code you entered is invalid.',
    'totp_enrollment_expired' => 'The TOTP enrollment session expired. Start over.',
    'current_password_incorrect' => 'The current password is incorrect.',
    'github_no_account' => 'No admin account linked to this GitHub account.',
    'github_already_linked' => 'GitHub account is already linked.',
    'github_conflict' => 'This GitHub account is already linked to a different admin account.',
    'github_linked' => 'GitHub account linked successfully.',
    'github_unlinked' => 'GitHub account unlinked.',
    'github_invalid_intent' => 'Invalid OAuth intent.',
    'profile_updated' => 'Profile updated successfully.',
    'totp_disabled' => 'TOTP disabled.',
    'code_format_invalid' => 'The :attribute must be a 6-digit code or a recovery code.',
];
