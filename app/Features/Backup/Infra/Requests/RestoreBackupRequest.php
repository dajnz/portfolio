<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Requests;

use App\Features\Backup\Infra\Rules\CurrentPasswordRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The restore-submit payload (content-management.md:740-742,
 * backup.md:590): the backup to restore plus the admin's current
 * password — the destructive action's confirmation. The encryption
 * key is resolved server-side from the backup record.
 */
final class RestoreBackupRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'backup_id' => ['required', 'integer', 'exists:backups,id'],
            'password' => ['required', 'string', new CurrentPasswordRule()],
        ];
    }
}
