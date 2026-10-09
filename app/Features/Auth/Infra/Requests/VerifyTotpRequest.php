<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Requests;

use App\Features\Auth\Infra\Rules\TotpOrRecoveryCodeRule;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyTotpRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', new TotpOrRecoveryCodeRule()],
        ];
    }
}
