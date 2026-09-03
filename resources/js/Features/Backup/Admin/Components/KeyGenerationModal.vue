<!-- KeyGenerationModal (admin-section-components.md:1493-1509, backups.html) — AdminBaseModal showing the raw encryption key exactly once: the `<code>` block, the Copy-to-clipboard button (flips to "Copied!" for 2 s) and the "I've saved it" dismissal. -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseModal from '@shared-admin/AdminBaseModal.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        key_value?: string;
        key_name?: string;
        test_id?: string;
    }>(),
    {
        key_value: '',
        key_name: '',
        test_id: 'key-generation-modal',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    acknowledged: [];
}>();

const { t } = useI18n();

const copied = ref(false);
let copyTimer: ReturnType<typeof setTimeout> | null = null;

async function copyKey(): Promise<void> {
    if (props.key_value === '') {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.key_value);
    } catch {
        // The clipboard API can be unavailable (insecure context, tests) —
        // the copy button still flips to "Copied!"; the value stays
        // visible for manual selection.
    }

    copied.value = true;

    if (copyTimer !== null) {
        clearTimeout(copyTimer);
    }

    copyTimer = setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function acknowledge(): void {
    if (copyTimer !== null) {
        clearTimeout(copyTimer);
        copyTimer = null;
    }

    emit('acknowledged');
    emit('update:modelValue', false);
}
</script>

<template>
  <AdminBaseModal
    :model-value="props.modelValue"
    :title="t('admin.backups.encryption_key')"
    :close_on_scrim="false"
    :close_on_escape="false"
    :test_id="props.test_id"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="space-y-4">
      <p class="text-sm text-slate-600 dark:text-slate-400">
        {{ t('admin.backups.save_key_offline') }}
      </p>
      <div>
        <p class="mb-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400">
          {{ props.key_name }}
        </p>
        <code
          class="block w-full rounded-lg border border-slate-200 bg-slate-50 p-3 font-mono text-sm break-all text-slate-900 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-100"
          :data-testid="`${props.test_id}-key-value`"
        >
          {{ props.key_value }}
        </code>
      </div>
    </div>
    <template #footer>
      <AdminBaseButton
        variant="secondary"
        icon="heroicons:clipboard"
        :test_id="`${props.test_id}-copy`"
        @click="copyKey"
      >
        {{ copied ? t('admin.backups.copied') : t('admin.backups.copy') }}
      </AdminBaseButton>
      <AdminBaseButton
        variant="primary"
        :test_id="`${props.test_id}-acknowledged`"
        @click="acknowledge"
      >
        {{ t('admin.backups.saved_key') }}
      </AdminBaseButton>
    </template>
  </AdminBaseModal>
</template>
