<!-- DashboardPage — TEMPORARY placeholder (step 4.1, B107): the full dashboard (StatsCards + QuickLinks per admin-section-ui.md:220-228) lands in step 4.2. The minimal page keeps `/admin` functional — the login flow redirects here (authentication.md:20,279) and the e2e suite depends on it. -->
<script setup lang="ts">
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import type { components } from '@shared-cross/Types/inertia';

type Stats = NonNullable<components['schemas']['DashboardPageProps']['props']>['stats'];

defineProps<{
    stats?: Stats;
}>();
</script>

<template>
  <AdminPageLayout>
    <h1>Dashboard</h1>
    <ul>
      <li v-if="stats">
        Total Views: {{ stats.total_views }}
      </li>
      <li v-if="stats">
        Blog Views: {{ stats.blog_views }}
      </li>
      <li v-if="stats">
        Projects: {{ stats.projects_count }}
      </li>
      <li v-if="stats">
        Articles: {{ stats.articles_count }}
      </li>
    </ul>
  </AdminPageLayout>
</template>
