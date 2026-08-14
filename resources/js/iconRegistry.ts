import { addCollection } from '@iconify/vue';
import heroicons from '@iconify-json/heroicons/icons.json';
import lucide from '@iconify-json/lucide/icons.json';

// Offline Iconify registration (self-hosted — no runtime calls to the
// Iconify API, per the no-third-party-trackers stance). The heroicons
// set covers the base components' icons; devicon registers statically in
// the TechSkills page (5.2 — race-free at page load, without bloating
// the app bundle); simple-icons registers in the feature steps that
// consume them (7.2 / 9.1).
addCollection(heroicons);

// The lucide set is registered as a SUBSET — only the two icons the
// TextEditor's subscript/superscript buttons need (heroicons has no
// sub/superscript glyphs; B231). Importing the full lucide collection
// (~500 KB raw) would bloat the app chunk; the subset keeps the
// bundle flat while staying offline.
addCollection({
    prefix: 'lucide',
    icons: {
        subscript: lucide.icons.subscript,
        superscript: lucide.icons.superscript,
    },
    width: lucide.width,
    height: lucide.height,
});
