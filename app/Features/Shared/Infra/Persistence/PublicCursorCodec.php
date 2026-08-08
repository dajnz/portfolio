<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Persistence;

use JsonException;

/**
 * The public list pages' opaque cursor codec (public-section-ui.md:
 * 316-327): `cursor = base64url(JSON.stringify({ "id": <item id>, "v":
 * <sort value | null> }))`. The client treats the value as opaque — it
 * receives it as `next_cursor` and echoes it back on "Load More" and in
 * the URL — only the server encodes/decodes. Reused by every public
 * infinite-scroll list (experience 6.1; projects 7.1 and blog 8.2
 * follow).
 *
 * This is a DIFFERENT wire format from the admin lists' structured
 * `?cursor[id]=&cursor[sort_value]=` params ({@see CursorDecoder} +
 * {@see SortSpec}) — a single opaque string vs. sort-aware query
 * params — hence the separate codec. A malformed value decodes to null
 * (the caller treats it as an initial-batch request; the cursor is a
 * UX-state concern, never a 4xx — seo.md:305).
 */
final class PublicCursorCodec
{
    /**
     * @return Cursor|null  Null when absent or malformed.
     */
    public function decode(string $cursor): ?Cursor
    {
        if ($cursor === '') {
            return null;
        }

        $json = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($json === false) {
            return null;
        }

        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data)
            || ! isset($data['id'], $data['v'])
            || ! is_int($data['id'])
            || $data['id'] < 1
            || ! is_string($data['v'])
            || $data['v'] === ''
        ) {
            return null;
        }

        return new Cursor(id: $data['id'], sortValue: $data['v']);
    }

    public function encode(Cursor $cursor): string
    {
        $json = json_encode(
            ['id' => $cursor->id, 'v' => $cursor->sortValue],
            JSON_THROW_ON_ERROR,
        );

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
