import { addCollection } from '@iconify/vue';
import heroicons from '@iconify-json/heroicons/icons.json';

// Offline Iconify registration (self-hosted — no runtime calls to the
// Iconify API, per the no-third-party-trackers stance). The heroicons
// set covers the base components' icons; devicon/simple-icons register
// in the feature steps that consume them (5.2 / 7.2 / 9.1).
addCollection(heroicons);
