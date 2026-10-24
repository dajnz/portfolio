import { usePage } from '@inertiajs/vue3';
import { resolveTimeZone } from '@shared-cross/Utils/dateTime';

/**
 * The public surface's display zone for publication dates — the owner's
 * `about_timezone` shared prop, validated with a `UTC` fallback. Passing
 * the zone explicitly to `Intl.DateTimeFormat` keeps the SSR output and
 * the hydrated output identical.
 */
export function useAboutTimeZone(): string {
    const page = usePage<{ about_timezone?: string | null }>();

    return resolveTimeZone(page.props.about_timezone);
}
