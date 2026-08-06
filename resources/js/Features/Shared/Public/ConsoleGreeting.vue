<script lang="ts">
// Side-effect only — renders no visible DOM (public-section-components.md:
// 588). `render: () => null` avoids the dev-mode "missing template or
// render function" warning that a template-less SFC emits.
export default {
    render: () => null,
};
</script>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import { onMounted } from 'vue';

export interface ConsoleGreetingData {
    greeting: string;
    name: string;
    position: string;
    contacts: Array<{ label: string; value: string }>;
}

const props = withDefaults(
    defineProps<{
        greeting?: string;
        name?: string;
        position?: string;
        contacts?: Array<{ label: string; value: string }>;
    }>(),
    {
        greeting: undefined,
        name: undefined,
        position: undefined,
        contacts: () => [],
    },
);

interface PageProps extends InertiaPageProps {
    cspNonce?: string;
    featureFlags?: Record<string, boolean>;
}

// Active in development, or in production when the `console_greeting`
// feature flag is enabled (public-section-components.md:578-590). The
// flag comes from the shared props (feature_flags table, default off).
function isActive(): boolean {
    const flags = usePage<PageProps>().props.featureFlags ?? {};

    if (flags.console_greeting === true) {
        return true;
    }

    return import.meta.env.DEV;
}

function buildGreetingScript(nonce: string): HTMLScriptElement {
    const contactLines = props.contacts
        .map((contact) => `${contact.label}: ${contact.value}`)
        .join('\n');

    const lines = [
        props.greeting ?? '',
        `${props.name ?? ''} — ${props.position ?? ''}`,
        contactLines,
    ].filter((line) => line !== '');

    const script = document.createElement('script');
    script.nonce = nonce;
    script.textContent = `console.info(${JSON.stringify(lines.join('\n'))});`;

    return script;
}

// Side-effect only — the nonced inline script is injected into <head>
// after mount.
onMounted(() => {
    if (!isActive()) {
        return;
    }

    const nonce = usePage<PageProps>().props.cspNonce ?? '';

    document.head.appendChild(buildGreetingScript(nonce));
});
</script>
