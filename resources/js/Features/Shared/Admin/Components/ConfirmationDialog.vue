<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import { ref, watch } from 'vue';

import AdminBaseButton from './AdminBaseButton.vue';
import AdminBaseInput from './AdminBaseInput.vue';
import AdminBaseModal from './AdminBaseModal.vue';

type ConfirmVariant = 'danger' | 'primary';

const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        title?: string;
        message?: string;
        confirm_label?: string;
        abort_label?: string;
        confirm_variant?: ConfirmVariant;
        require_password?: boolean;
        is_loading?: boolean;
        test_id?: string;
    }>(),
    {
        title: undefined,
        message: undefined,
        confirm_label: undefined,
        abort_label: undefined,
        confirm_variant: 'danger',
        require_password: false,
        is_loading: false,
        test_id: 'confirmation-dialog',
    },
);

const emit = defineEmits<{
    confirm: [payload: { password: string | null }];
    abort: [];
    'update:modelValue': [value: boolean];
}>();

const { t } = useI18n();

const password = ref('');

const confirmLabel = () => props.confirm_label ?? t('common.confirm');
const abortLabel = () => props.abort_label ?? t('common.cancel');

const confirmDisabled = () => props.require_password && password.value.trim() === '';

function confirm(): void {
    emit('confirm', { password: props.require_password ? password.value : null });
}

function abort(): void {
    emit('abort');
    emit('update:modelValue', false);
}

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            password.value = '';
        }
    },
);
</script>

<template>
  <AdminBaseModal
    :model-value="props.modelValue"
    :title="props.title"
    size="sm"
    :test_id="props.test_id"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <!-- The whole body block is omitted when there is nothing to show —
         a title-only delete dialog must not render an empty box. -->
    <div
      v-if="props.message || $slots.body || props.require_password"
      class="space-y-4"
    >
      <p
        v-if="!$slots.body && props.message"
        class="text-sm text-slate-600 dark:text-slate-400"
      >
        {{ props.message }}
      </p>
      <slot name="body" />
      <AdminBaseInput
        v-if="props.require_password"
        v-model="password"
        type="password"
        :label="t('admin.profile.enter_password_to_confirm')"
        :disabled="props.is_loading"
        :test_id="`${props.test_id}-password`"
      />
    </div>
    <template #footer>
      <AdminBaseButton
        variant="ghost"
        :disabled="props.is_loading"
        :test_id="`${props.test_id}-abort`"
        @click="abort"
      >
        {{ abortLabel() }}
      </AdminBaseButton>
      <AdminBaseButton
        :variant="props.confirm_variant"
        :loading="props.is_loading"
        :disabled="confirmDisabled()"
        :test_id="`${props.test_id}-confirm`"
        @click="confirm"
      >
        {{ confirmLabel() }}
      </AdminBaseButton>
    </template>
  </AdminBaseModal>
</template>
