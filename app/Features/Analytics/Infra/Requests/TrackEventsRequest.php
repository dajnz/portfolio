<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * The `/api/analytics/track` batch validator: an array of 1-100 events,
 * each capped per the documented limits — 100-char event type,
 * 255-char session id, 500-char `page_url`, 2 KB `metadata.referrer`,
 * `seconds` 0-86400, `percentage_completed` 0-100, and the 50 KB total
 * payload cap (any invalid item rejects the entire batch with a 422).
 */
final class TrackEventsRequest extends FormRequest
{
    private const MAX_BATCH_SIZE = 100;

    private const MAX_PAYLOAD_BYTES = 51200;

    public function rules(): array
    {
        $batch = $this->all();

        if ($batch === []) {
            throw ValidationException::withMessages([
                'batch' => [__('validation.analytics_batch_empty')],
            ]);
        }

        // A non-list body (e.g. an object) would pass the wildcard item
        // rules and crash the use case's event mapping.
        if (! array_is_list($batch)) {
            throw ValidationException::withMessages([
                'batch' => [__('validation.analytics_batch_shape')],
            ]);
        }

        if (count($batch) > self::MAX_BATCH_SIZE) {
            throw ValidationException::withMessages([
                'batch' => [__('validation.analytics_batch_too_large')],
            ]);
        }

        if (strlen($this->getContent()) > self::MAX_PAYLOAD_BYTES) {
            throw ValidationException::withMessages([
                'batch' => [__('validation.analytics_payload_too_large')],
            ]);
        }

        return [
            '*' => ['required', 'array'],
            '*.event_type' => ['required', 'string', 'max:100'],
            '*.session_id' => ['required', 'string', 'max:255'],
            // The page context: the page type the frontend knows (capped
            // — the enum whitelist lives in the domain parser) and the
            // entity id of the entity pages.
            '*.page_type' => ['nullable', 'string', 'max:50'],
            '*.page_entity_id' => ['nullable', 'integer', 'min:1'],
            '*.page_url' => ['nullable', 'string', 'max:500'],
            '*.metadata' => ['nullable', 'array'],
            '*.metadata.referrer' => ['nullable', 'string', 'max:2048'],
            '*.metadata.seconds' => ['nullable', 'integer', 'between:0,86400'],
            '*.metadata.percentage_completed' => ['nullable', 'integer', 'between:0,100'],
        ];
    }
}
