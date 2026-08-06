<!-- AdminPageHeader (admin-section-components.md:291-309, admin-section-ui.md:55-60) — centred top bar: logo, controls slot, admin identity, logout. -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

import AdminBaseButton from './AdminBaseButton.vue';
import AdminCenteredContainer from './AdminCenteredContainer.vue';

interface LocaleOption {
    code: string;
    native_name: string;
}

interface LocaleProps {
    active_locale: string;
    supported_locales: Array<LocaleOption>;
}

const props = withDefaults(
    defineProps<{
        logo_url?: string;
        admin_name?: string;
        profile_url?: string;
        logout_url?: string;
        sidebar_collapsed?: boolean;
        locale_props?: LocaleProps;
        theme_props?: Record<string, string>;
        language_classes?: Record<string, string>;
        test_id?: string;
    }>(),
    {
        logo_url: '/admin',
        admin_name: undefined,
        profile_url: '/admin/profile',
        logout_url: undefined,
        sidebar_collapsed: false,
        locale_props: () => ({ active_locale: 'en', supported_locales: [] }),
        theme_props: () => ({}),
        language_classes: () => ({}),
        test_id: 'admin-header',
    },
);

const emit = defineEmits<{ 'toggle-sidebar': [] }>();

const { t } = useI18n();

const avatarLetter = computed(() => (props.admin_name ?? '').charAt(0).toUpperCase());

function handleLogout(): void {
    if (props.logout_url !== undefined) {
        router.post(props.logout_url);
    }
}
</script>

<template>
  <header
    class="bg-white dark:bg-surface-dark border-b border-slate-200 dark:border-slate-700"
    :data-testid="props.test_id"
  >
    <AdminCenteredContainer size="xl">
      <div class="flex items-center justify-between h-16">
        <div class="flex items-center gap-4">
          <AdminBaseButton
            variant="icon-only"
            icon="heroicons:bars-3"
            class="lg:hidden"
            :aria-label="t('admin.nav.sidebar')"
            :test_id="`${props.test_id}-hamburger`"
            @click="emit('toggle-sidebar')"
          />
          <a
            class="text-lg font-extrabold tracking-tight text-slate-900 dark:text-white"
            :href="props.logo_url"
            :data-testid="`${props.test_id}-logo`"
          >
            {{ t('admin.section_title') }}
          </a>
        </div>
        <div class="flex items-center gap-4">
          <div
            v-if="$slots.controls"
            class="flex items-center gap-3"
          >
            <slot name="controls" />
          </div>
          <div class="flex items-center gap-2 pl-3 border-l border-slate-200 dark:border-slate-700">
            <a
              class="flex items-center gap-2"
              :href="props.profile_url"
              :aria-label="props.admin_name ?? t('admin.nav.profile')"
            >
              <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-600 text-white text-sm font-bold">
                {{ avatarLetter }}
              </div>
              <span
                v-if="props.admin_name"
                class="hidden sm:block text-sm font-semibold text-slate-700 dark:text-slate-200"
              >
                {{ props.admin_name }}
              </span>
            </a>
            <AdminBaseButton
              variant="ghost"
              size="sm"
              icon="heroicons:arrow-right-start-on-rectangle"
              :test_id="`${props.test_id}-logout`"
              @click="handleLogout"
            >
              {{ t('admin.nav.logout') }}
            </AdminBaseButton>
          </div>
        </div>
      </div>
    </AdminCenteredContainer>
  </header>
</template>
