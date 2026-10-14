<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The encryption-key creation payload: a unique display name (up to 100
 * chars).
 */
final class StoreEncryptionKeyRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('backup_encryption_keys', 'name')],
        ];
    }
}
