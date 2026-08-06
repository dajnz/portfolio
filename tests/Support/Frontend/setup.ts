import '@/iconRegistry';

// Registers the heroicons collection for Iconify's `Icon` component the
// same way the app entries do (resources/js/app.ts / ssr.ts). Without
// registration @iconify/vue attempts a runtime fetch from the Iconify
// API, which happy-dom aborts at window teardown (AbortError noise).
