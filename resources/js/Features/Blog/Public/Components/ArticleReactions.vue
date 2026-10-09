<!-- ArticleReactions — the like/dislike widget of the article page, rendered only when the `social_reactions` feature flag is on (the page's `reactions` prop is null otherwise). The widget owns its POST: clicking a button posts `{reaction: "like"|"dislike"}` to `/api/blog/{article}/reaction` (the JSON client's XSRF header) and updates its counts + highlighted state from the response — submitting the SAME reaction again undoes it (the response's `reaction: null`); while the request is in flight both buttons disable; a failure shows the error toast and keeps the counts. `aria-pressed` reflects the visitor's current reaction; the `react` emit carries the `{reaction}` shape for the page (the analytics `article_reaction` event fires here). -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useAnalytics } from '@shared-cross/Composables/useAnalytics';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { ref } from 'vue';

type ReactionValue = 'like' | 'dislike';

interface ReactionResponse {
    data?: {
        reaction?: string | null;
        likes?: number;
        dislikes?: number;
    };
}

const props = withDefaults(
    defineProps<{
        article_id?: number;
        user_reaction?: ReactionValue | null;
        likes?: number;
        dislikes?: number;
        test_id?: string;
    }>(),
    {
        article_id: 0,
        user_reaction: null,
        likes: 0,
        dislikes: 0,
        test_id: 'article-reactions',
    },
);

const emit = defineEmits<{ react: [payload: { reaction: ReactionValue }] }>();

const { t } = useI18n();
const analytics = useAnalytics();
const toast = useToast();
const { post } = useJsonApi();

// The widget's local state — initialized from the SSR props and
// replaced by the POST responses (the wire props are not re-sent on
// JSON navigation).
const current = ref<ReactionValue | null>(props.user_reaction ?? null);
const likes = ref(props.likes ?? 0);
const dislikes = ref(props.dislikes ?? 0);
const isSubmitting = ref(false);

async function react(reaction: ReactionValue): Promise<void> {
    if (isSubmitting.value || props.article_id === 0) {
        return;
    }

    // The undo rule: clicking the visitor's current reaction removes it.
    const next = current.value === reaction ? null : reaction;

    isSubmitting.value = true;

    try {
        const response =
            (await post<ReactionResponse>(`/api/blog/${props.article_id}/reaction`, {
                json: { reaction },
            })) ?? {};

        current.value = (response.data?.reaction as ReactionValue | null | undefined) ?? null;
        likes.value = response.data?.likes ?? likes.value;
        dislikes.value = response.data?.dislikes ?? dislikes.value;

        if (next !== null) {
            analytics.track('article_reaction', {
                article_id: props.article_id,
                reaction: next,
            });
        }
    } catch {
        // Counts unchanged; the toast carries the failure.
        toast.show(t('blog.reaction_error'), { variant: 'error' });
    } finally {
        isSubmitting.value = false;
    }

    emit('react', { reaction });
}

function buttonState(reaction: ReactionValue): { pressed: boolean; count: number } {
    return {
        pressed: current.value === reaction,
        count: reaction === 'like' ? likes.value : dislikes.value,
    };
}
</script>

<template>
  <div
    class="reactions"
    :data-testid="props.test_id"
  >
    <button
      class="reactions__button"
      :class="{ 'is-active': buttonState('like').pressed }"
      type="button"
      :aria-pressed="buttonState('like').pressed ? 'true' : 'false'"
      :aria-label="t('blog.like')"
      :disabled="isSubmitting"
      :data-testid="`${props.test_id}-like`"
      @click="react('like')"
    >
      <Icon
        icon="heroicons:hand-thumb-up"
        aria-hidden="true"
      />
      <span class="reactions__count">{{ buttonState('like').count }}</span>
    </button>
    <button
      class="reactions__button"
      :class="{ 'is-active': buttonState('dislike').pressed }"
      type="button"
      :aria-pressed="buttonState('dislike').pressed ? 'true' : 'false'"
      :aria-label="t('blog.dislike')"
      :disabled="isSubmitting"
      :data-testid="`${props.test_id}-dislike`"
      @click="react('dislike')"
    >
      <Icon
        icon="heroicons:hand-thumb-down"
        aria-hidden="true"
      />
      <span class="reactions__count">{{ buttonState('dislike').count }}</span>
    </button>
  </div>
</template>

<style scoped>
.reactions {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.reactions__button {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    padding: var(--space-xs) var(--space-md);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-full);
    background: var(--color-surface);
    color: var(--color-text-secondary);
    cursor: pointer;
    font-size: var(--font-size-sm);
    transition: all var(--transition-fast);
}

.reactions__button:hover:not(:disabled) {
    border-color: var(--color-accent);
    color: var(--color-accent);
}

.reactions__button:disabled {
    opacity: 0.6;
    cursor: default;
}

/* The visitor's current reaction is highlighted;
   aria-pressed reflects it. */
.reactions__button.is-active {
    background: var(--color-accent-muted);
    border-color: var(--color-accent);
    color: var(--color-accent);
}

.reactions__count {
    font-weight: var(--font-semibold);
}
</style>
