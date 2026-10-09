<!-- CodeBlock (public-section-components.md:1343-1357, mockup blog-article.html) — a syntax-highlighted code block with a copy-to-clipboard button. The server-side pipeline already rendered the highlighted tokens (scrivo/highlight.php, ADR-0008) — this component only adds the language header + the copy button around the static HTML (the public page never re-highlights client-side). `code_html` is the pipeline's `<code>…</code>` fragment (the `data-code-block` element's inner HTML); the copy action reads `raw_code` (the attribute's plain text). States: idle ("Copy") / copied ("Copied!" for 2s — `blog.copied`). Emits `copied` after a successful copy. -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import { ref } from 'vue';

const COPIED_RESET_MS = 2000;

const props = withDefaults(
    defineProps<{
        code_html?: string;
        language?: string;
        raw_code?: string;
        test_id?: string;
    }>(),
    {
        code_html: '',
        language: '',
        raw_code: '',
        test_id: 'code-block',
    },
);

const emit = defineEmits<{ copied: [] }>();

const { t } = useI18n();

const copied = ref(false);
let resetTimer: ReturnType<typeof setTimeout> | null = null;

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.raw_code);
    } catch {
        return;
    }

    copied.value = true;
    emit('copied');

    if (resetTimer !== null) {
        clearTimeout(resetTimer);
    }

    resetTimer = setTimeout(() => {
        copied.value = false;
        resetTimer = null;
    }, COPIED_RESET_MS);
}
</script>

<template>
  <div
    class="code-block"
    :data-testid="props.test_id"
  >
    <div class="code-block__header">
      <!-- Language-less fenced blocks carry an empty label — hidden. -->
      <span
        v-if="props.language !== ''"
        class="code-block__language"
      >{{ props.language }}</span>
      <button
        class="code-block__copy"
        :class="{ 'is-copied': copied }"
        :data-testid="`${props.test_id}-copy`"
        type="button"
        @click="copy"
      >
        {{ copied ? t('blog.copied') : t('blog.copy_code') }}
      </button>
      <span
        class="visually-hidden"
        role="status"
        aria-live="polite"
        :data-testid="`${props.test_id}-copy-status`"
      >{{ copied ? t('blog.copied') : '' }}</span>
    </div>
    <!-- eslint-disable vue/no-v-html -->
    <pre v-html="props.code_html" />
    <!-- eslint-enable vue/no-v-html -->
  </div>
</template>

<style scoped>
.code-block {
    position: relative;
    margin: var(--space-lg) 0;
    border: 1px solid var(--color-border);
    background: var(--color-bg-secondary);
}

/* User rework 2026-08-16: the radii live on the header (rounded TOP
   corners) and the pre (square top, rounded BOTTOM corners) — the
   block itself no longer clips (overflow: hidden rounded the pre's
   bottom too). */
.code-block__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-xs) var(--space-md);
    background: var(--color-bg-tertiary);
    border-bottom: 1px solid var(--color-border);
    border-radius: var(--radius-md) var(--radius-md) 0 0;
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: var(--letter-spacing-wide);
    font-family: var(--font-mono);
}

.code-block__copy {
    border: none;
    background: transparent;
    color: var(--color-text-secondary);
    cursor: pointer;
    font-size: var(--font-size-sm);
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    text-transform: none;
    letter-spacing: normal;
    font-family: var(--font-sans);
    border-radius: var(--radius-sm);
    padding: 2px var(--space-sm);
}

.code-block__copy:hover {
    background: var(--color-surface);
    color: var(--color-text-primary);
}

.code-block__copy.is-copied {
    color: var(--color-success);
}

.code-block pre {
    margin: 0;
    padding: var(--space-md) var(--space-lg);
    overflow-x: auto;
    border-radius: 0 0 var(--radius-md) var(--radius-md);

    /* The tokens' global `pre` rule (public-tokens.css) paints
       --color-bg-tertiary + a border — the block's own
       --color-bg-secondary must show through and the outer border
       belongs to the block only (user rework 2026-08-16: the text
       band differed from the block background). */
    background: transparent;
    border: none;
    font-family: var(--font-mono);
    font-size: var(--font-size-sm);
    line-height: var(--line-height-normal);
    color: var(--color-text-primary);

    /* The thin scrollbar look of the admin utility (app.css
       `thin-scrollbar`): 6px thumb, subtle track. */
    scrollbar-width: thin;
    scrollbar-color: var(--color-text-tertiary) transparent;
}

.code-block pre::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.code-block pre::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: var(--color-text-tertiary);
}

.code-block pre::-webkit-scrollbar-track {
    background: transparent;
}
</style>
