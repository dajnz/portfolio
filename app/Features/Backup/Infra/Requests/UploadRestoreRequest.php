<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Requests;

use App\Features\Backup\Infra\Rules\CurrentPasswordRule;
use App\Features\Shared\Domain\RepositoryInterfaces\SettingsReadRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * The disaster-recovery upload payload: the downloaded `.zip`, the
 * offline-saved raw key (44-char base64 of 32 bytes) and the admin's
 * current password. The file size limit comes from the
 * `backup_upload_max_size` setting (KB) — read via the Shared settings
 * surface with `config('backup.upload_max_size_kb')` as the fallback; the
 * server validates the archive against the key after the request rules
 * pass (ImportFromPathUseCase).
 */
final class UploadRestoreRequest extends FormRequest
{
    public function __construct(
        private readonly SettingsReadRepositoryInterface $settings,
    ) {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxKb = (int) $this->settings->value('backup_upload_max_size', Config::get('backup.upload_max_size_kb', 102400));

        return [
            'file' => ['required', 'file', 'mimes:zip', "max:{$maxKb}"],
            'key' => ['required', 'string', 'min:32', 'max:64'],
            'password' => ['required', 'string', new CurrentPasswordRule()],
        ];
    }
}
