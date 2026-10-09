<!-- FeedbackForm (public-section-components.md:818-852, public-section-ui.md:423-435; success state reworked 2026-08-14) — the landing contact form. Five local states (no global store): idle / submitting / success / error_validation / error_network. Submits JSON to `/api/feedback` (public, no CSRF — api_routes.php); a 422 is parsed directly from the response body (error-handling.md:147) into inline field errors; network/5xx failures show the persistent error toast. The honeypot field (authentication.md:333-351) carries the session-derived randomized name and is always submitted empty. Success replaces the whole form (heading included) with a centered success icon + the confirmation message and fires `cta_click_feedback`. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useAnalytics } from '@shared-cross/Composables/useAnalytics';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useToast } from '@shared-cross/Composables/useToast';
import PublicBaseButton from '@shared-public/PublicBaseButton.vue';
import PublicBaseInput from '@shared-public/PublicBaseInput.vue';
import PublicBaseTextarea from '@shared-public/PublicBaseTextarea.vue';
import { computed, nextTick, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        action_url: string;
        sender_name_label: string;
        sender_email_label: string;
        message_label: string;
        submit_label: string;
        success_title: string;
        honeypot_name?: string | null;
        test_id?: string;
    }>(),
    {
        honeypot_name: null,
        test_id: 'landing-feedback-form',
    },
);

const emit = defineEmits<{ submitted: [payload: { sender_name: string; sender_email: string }] }>();

const { t } = useI18n();
const analytics = useAnalytics();
const toast = useToast();

const senderName = ref('');
const senderEmail = ref('');
const message = ref('');
const honeypot = ref('');
const submitting = ref(false);
const succeeded = ref(false);
const serverErrors = ref<Record<string, string>>({});
const successEl = ref<HTMLElement | null>(null);
const formEl = ref<HTMLFormElement | null>(null);
const summaryEl = ref<HTMLElement | null>(null);

// The validation summary (accessibility.md:116,138): shown above the
// form on a 422, announcing the error count; focus moves to it when
// several fields failed, or directly to the single invalid field.
const errorMessages = computed(() =>
    Object.values(serverErrors.value).filter((message) => message !== ''),
);

// The success template arrives localized with the `{sender_name}`
// placeholder (public-section-components.md:828) — interpolated here.
const successMessage = computed(() =>
    props.success_title.replace('{sender_name}', senderName.value),
);

function fieldError(field: string): string | null {
    return serverErrors.value[field] ?? null;
}

async function handleSubmit(): Promise<void> {
    if (submitting.value || succeeded.value) {
        return;
    }

    submitting.value = true;
    serverErrors.value = {};

    const payload: Record<string, string> = {
        sender_name: senderName.value,
        sender_email: senderEmail.value,
        message: message.value,
    };

    if (props.honeypot_name) {
        payload[props.honeypot_name] = honeypot.value;
    }

    try {
        // Raw fetch instead of the shared `useJsonApi`: that client is the
        // ADMIN JSON helper — it throws on non-OK responses (discarding
        // the body) and always parses JSON on success. The public
        // feedback endpoint returns 201 with an EMPTY body, and the 422
        // envelope (`{message, errors}`) must be read from the response
        // body to render the inline field errors (error-handling.md:147)
        // — the composable's contract fits neither.
        const response = await fetch(props.action_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (response.ok) {
            succeeded.value = true;
            analytics.track('cta_click_feedback');
            emit('submitted', {
                sender_name: senderName.value,
                sender_email: senderEmail.value,
            });
            // Move focus to the success state so screen readers announce
            // the confirmation (a11y 13.6 #7).
            await nextTick();
            successEl.value?.focus();
            return;
        }

        if (response.status === 422) {
            const body = (await response.json()) as { errors?: Record<string, string | string[]> };
            serverErrors.value = Object.fromEntries(
                Object.entries(body.errors ?? {}).map(([field, error]) => [
                    field,
                    Array.isArray(error) ? (error[0] ?? '') : error,
                ]),
            );

            // Re-enable the fields before moving focus — a disabled input
            // cannot receive it.
            submitting.value = false;
            await nextTick();

            const invalid = formEl.value?.querySelector<HTMLElement>('[aria-invalid="true"]');

            if (errorMessages.value.length === 1 && invalid) {
                invalid.focus();
            } else {
                summaryEl.value?.focus();
            }

            return;
        }

        toast.show(t('landing.feedback_error'), { variant: 'error' });
    } catch {
        toast.show(t('landing.feedback_error'), { variant: 'error' });
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
  <form
    ref="formEl"
    class="feedback-form"
    novalidate
    :data-testid="props.test_id"
    @submit.prevent="handleSubmit"
  >
    <div
      v-if="succeeded"
      ref="successEl"
      class="feedback-form__success"
      role="status"
      aria-live="polite"
      tabindex="-1"
      :data-testid="`${props.test_id}-success`"
    >
      <Icon
        class="feedback-form__success-icon"
        icon="heroicons:check-circle"
        aria-hidden="true"
      />
      <p class="feedback-form__success-message">
        {{ successMessage }}
      </p>
    </div>

    <template v-else>
      <h3 class="feedback-form__title">
        {{ t('landing.send_feedback') }}
      </h3>

      <!-- Validation summary (accessibility.md:116): only when several
           fields failed, so the messages shown inline are not repeated.
           A single failure focuses its field directly instead. -->
      <p
        v-if="errorMessages.length > 1"
        ref="summaryEl"
        class="feedback-form__summary"
        role="alert"
        tabindex="-1"
        :data-testid="`${props.test_id}-summary`"
      >
        {{ t('landing.feedback_errors_summary', { count: errorMessages.length }) }}
      </p>

      <div class="feedback-form__fields">
        <PublicBaseInput
          v-model="senderName"
          :label="props.sender_name_label"
          :error="fieldError('sender_name')"
          :required="true"
          :disabled="submitting"
          name="sender_name"
          autocomplete="name"
          :test_id="`${props.test_id}-name`"
        />
        <PublicBaseInput
          v-model="senderEmail"
          :label="props.sender_email_label"
          type="email"
          :error="fieldError('sender_email')"
          :required="true"
          :disabled="submitting"
          name="sender_email"
          autocomplete="email"
          :test_id="`${props.test_id}-email`"
        />
        <PublicBaseTextarea
          v-model="message"
          :label="props.message_label"
          :error="fieldError('message')"
          :required="true"
          :disabled="submitting"
          name="message"
          :rows="4"
          :test_id="`${props.test_id}-message`"
        />

        <div
          v-if="props.honeypot_name"
          class="visually-hidden"
          aria-hidden="true"
        >
          <PublicBaseInput
            v-model="honeypot"
            :name="props.honeypot_name"
            autocomplete="off"
            :tabindex="-1"
            :test_id="`${props.test_id}-honeypot`"
          />
        </div>

        <PublicBaseButton
          type="submit"
          variant="primary"
          :loading="submitting"
          :test_id="`${props.test_id}-submit`"
        >
          {{ props.submit_label }}
        </PublicBaseButton>
      </div>
    </template>
  </form>
</template>

<style scoped>
.feedback-form {
    max-width: 400px;
    padding: var(--space-xl);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
}

.feedback-form__title {
    font-size: var(--font-size-xl);
    font-weight: var(--font-bold);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-lg);
}

.feedback-form__summary {
    margin: 0 0 var(--space-md);
    padding: var(--space-sm) var(--space-md);
    border: 1px solid var(--color-error-border);
    border-radius: var(--radius-md);
    background: var(--color-error-bg);
    color: var(--color-error);
    font-size: var(--font-size-sm);
    font-weight: var(--font-semibold);
}

.feedback-form__summary:focus-visible {
    outline: var(--focus-ring-width) solid var(--focus-ring-color);
    outline-offset: var(--focus-ring-offset);
}

.feedback-form__fields {
    display: flex;
    flex-direction: column;
}

.feedback-form__fields > .btn {
    width: 100%;
}

.feedback-form__success {
    text-align: center;
    padding: var(--space-xl) 0;
}

.feedback-form__success-icon {
    width: 3rem;
    height: 3rem;
    margin: 0 auto var(--space-md);
    color: var(--color-success);
}

.feedback-form__success-message {
    font-size: var(--font-size-lg);
    font-weight: var(--font-semibold);
    color: var(--color-success);
    margin: 0;
}
</style>
