<!-- AdminPageHeader (admin-section-components.md:344-406, admin-section-ui.md:58-75) — full-width top bar (content spans the whole header, not the centred container — mockup profile.html): logo left, controls + admin identity right. The `controls` slot defaults to the shared ThemeSwitch + LanguageSelector (ADR-0023 bridge) with the Tailwind classes from `theme_props`/`language_classes`. Responsive (breakpoint `lg`, ADR-0024): below lg the identity group (avatar letter + name → profile) and the Logout button are hidden and a hamburger renders as the rightmost control — right of the language selector — toggling the full-width mobile menu (`toggle-mobile-menu`, aria-expanded/aria-controls="admin-mobile-menu"). The admin name/avatar letter fall back to the shared `auth.user` prop so every admin page shows the identity without per-page props (4.2 rework, user request). -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import LanguageSelector from '@shared-cross/Components/LanguageSelector.vue';
import ThemeSwitch from '@shared-cross/Components/ThemeSwitch.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useThemeStore } from '@shared-cross/Stores/themeStore';
import { computed } from 'vue';

import AdminBaseButton from './AdminBaseButton.vue';

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
        mobile_menu_open?: boolean;
        locale_props?: LocaleProps;
        theme_props?: Record<string, string>;
        language_classes?: Record<string, string>;
        test_id?: string;
    }>(),
    {
        logo_url: '/admin',
        admin_name: undefined,
        profile_url: '/admin/profile',
        logout_url: '/admin/logout',
        mobile_menu_open: false,
        locale_props: () => ({ active_locale: 'en', supported_locales: [] }),
        theme_props: () => ({}),
        language_classes: () => ({}),
        test_id: 'admin-header',
    },
);

const emit = defineEmits<{ 'toggle-mobile-menu': [] }>();

const { t } = useI18n();
const page = usePage<{ auth?: { user?: { name?: string } } }>();

// The prepared theme-variant logo (Makefile `images` pipeline); the
// themeStore is hydrated from the shared `theme` prop on every visit and
// follows the ThemeSwitch toggle reactively.
const themeStore = useThemeStore();
const logoImage = computed(() => `/images/logo-full-${themeStore.theme}.svg`);

// `admins.name` mirrors the login (ADR-0013) and is shared on every page as
// `auth.user` — the explicit prop only overrides it.
const resolvedAdminName = computed(() => props.admin_name ?? page.props?.auth?.user?.name ?? '');

const avatarLetter = computed(() => resolvedAdminName.value.charAt(0).toUpperCase());

const hamburgerIcon = computed(() =>
    props.mobile_menu_open ? 'heroicons:x-mark' : 'heroicons:bars-3',
);

function handleLogout(): void {
    if (props.logout_url !== undefined) {
        router.post(props.logout_url);
    }
}
</script>

<template>
  <header
    class="sticky top-0 z-overlay bg-white dark:bg-surface-dark border-b border-slate-200 dark:border-slate-700"
    :data-testid="props.test_id"
  >
    <!-- The header content spans the FULL header width (mockup profile.html
         header) — only the main content column is block-centred. -->
    <div class="flex items-center justify-between h-16 px-4 sm:px-6">
      <a
        class="flex items-center"
        :href="props.logo_url"
        :data-testid="`${props.test_id}-logo`"
      >
        <img
          class="h-8 w-auto"
          :src="logoImage"
          :alt="t('admin.section_title')"
          :data-testid="`${props.test_id}-logo-image`"
        >
      </a>
      <!-- Right cluster order: controls, hamburger (mobile only), identity +
           logout (lg+ only — the divider lives on the identity group). -->
      <div class="flex items-center gap-2 sm:gap-3">
        <div class="flex items-center gap-3">
          <slot name="controls">
            <ThemeSwitch :classes="props.theme_props" />
            <LanguageSelector
              :active_locale="props.locale_props.active_locale"
              :supported_locales="props.locale_props.supported_locales"
              :classes="props.language_classes"
            />
          </slot>
        </div>
        <AdminBaseButton
          variant="icon-only"
          :icon="hamburgerIcon"
          class="lg:hidden"
          :aria-label="t('admin.nav.sidebar')"
          :aria-expanded="props.mobile_menu_open"
          aria-controls="admin-mobile-menu"
          :test_id="`${props.test_id}-hamburger`"
          @click="emit('toggle-mobile-menu')"
        />
        <div
          class="hidden lg:flex items-center gap-2 pl-3 border-l border-slate-200 dark:border-slate-700"
          :data-testid="`${props.test_id}-identity`"
        >
          <a
            class="flex items-center gap-2"
            :href="props.profile_url"
            :aria-label="resolvedAdminName || t('admin.nav.profile')"
          >
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-600 text-white text-sm font-bold">
              {{ avatarLetter }}
            </div>
            <span
              v-if="resolvedAdminName"
              class="text-sm font-semibold text-slate-700 dark:text-slate-200"
            >
              {{ resolvedAdminName }}
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
  </header>
</template>
