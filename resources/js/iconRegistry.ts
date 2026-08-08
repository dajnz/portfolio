import { addCollection } from '@iconify/vue';
import heroicons from '@iconify-json/heroicons/icons.json';

// Offline Iconify registration (self-hosted — no runtime calls to the
// Iconify API, per the no-third-party-trackers stance). The heroicons
// set covers the base components' icons; devicon registers statically in
// the TechSkills page (5.2 — race-free at page load, without bloating
// the app bundle); simple-icons registers in the feature steps that
// consume them (7.2 / 9.1).
addCollection(heroicons);
