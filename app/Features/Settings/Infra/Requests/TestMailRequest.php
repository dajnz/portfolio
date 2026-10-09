<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The Mail tab's "Test Connection" probe: accepts the current Mail-tab
 * values as a grouped `{ settings: { ... } }` payload; only the five mail
 * keys are allowed (the probe never persists anything).
 */
final class TestMailRequest extends FormRequest
{
    private const MAIL_KEYS = [
        'mail_api_endpoint',
        'mail_api_token',
        'mail_from_address',
        'mail_from_name',
        'email_on_feedback',
    ];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach (array_keys($this->input('settings', [])) as $key) {
                if (! in_array($key, self::MAIL_KEYS, true)) {
                    $validator->errors()->add("settings.$key", "The selected settings.$key is invalid.");
                }
            }
        });
    }
}
