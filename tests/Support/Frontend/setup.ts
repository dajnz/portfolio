import { addCollection } from '@iconify/vue';
import type { IconifyJSON } from '@iconify/types';
import deviconJson from '@iconify-json/devicon/icons.json';
import heroiconsJson from '@iconify-json/heroicons/icons.json';

// Registers the iconify collections for Iconify's `Icon` component the
// same way the app entries do (resources/js/app.ts / ssr.ts +
// iconRegistry.ts). Without registration @iconify/vue attempts a runtime
// fetch from the Iconify API, which happy-dom aborts at window teardown
// (AbortError noise). heroicons covers the base components; devicon
// covers TechSkillTag/TechSkillAutocomplete. The fixture JSONs are
// aliased in vite.config.ts (test-only).
addCollection(heroiconsJson as IconifyJSON);
addCollection(deviconJson as IconifyJSON);