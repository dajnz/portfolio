<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/feedback` (content-management.md:689-696): sender identity +
 * message. The honeypot field name is randomized per page render
 * (authentication.md:345-351) and is NOT part of the OpenAPI request
 * schema — the check happens in the controller (silent 200, no store)
 * and is skipped when the session ID cannot be resolved (this endpoint
 * receives no session middleware).
 */
final class SubmitFeedbackRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sender_name' => ['required', 'string', 'max:100'],
            'sender_email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * The expected honeypot field name for the current session —
     * `hash_hmac('sha256', 'honeypot_'.session()->getId(), app_key)`
     * truncated to the first 32 hex characters (authentication.md:345-351).
     * Null when the session ID cannot be resolved — the check is then
     * skipped (this endpoint receives no session middleware).
     */
    public static function honeypotFieldName(): ?string
    {
        $sessionId = session()->getId();

        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        return substr(
            hash_hmac('sha256', 'honeypot_'.$sessionId, (string) config('app.key')),
            0,
            32,
        );
    }

    /**
     * Whether the honeypot field (if its name could be recomputed) was
     * filled by a bot.
     */
    public function honeypotFilled(): bool
    {
        $fieldName = self::honeypotFieldName();

        if ($fieldName === null) {
            return false;
        }

        $value = $this->input($fieldName);

        return is_string($value) && $value !== '';
    }
}
