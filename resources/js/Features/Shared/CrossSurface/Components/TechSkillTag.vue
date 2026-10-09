<script setup lang="ts">
import { Icon } from '@iconify/vue';
import {
    ensureDeviconIcons,
    useDeviconIconsReady,
} from '@shared-cross/Composables/useDeviconIcons';

interface TechSkill {
    id: number;
    name: string;
    icon: string;
    proficiency?: number;
    proficiencyLabel?: string;
}

interface TechSkillTagClasses {
    container?: string;
    icon?: string;
    name?: string;
    proficiency?: string;
}

const props = withDefaults(
    defineProps<{
        skill: TechSkill;
        showProficiency?: boolean;
        linked?: boolean;
        href?: string;
        classes?: TechSkillTagClasses | null;
        test_id?: string;
    }>(),
    {
        showProficiency: false,
        linked: false,
        href: undefined,
        classes: null,
        test_id: 'tech-skill-tag',
    },
);

// The chip renders devicon glyphs; the icon must not hit the DOM before
// the offline subset is registered, or the Iconify runtime falls back to
// its remote API (blocked by CSP `connect-src 'self'`). The glyph pops
// in once the shared registration resolves.
ensureDeviconIcons();
const deviconReady = useDeviconIconsReady();

function blogFilterUrl(skill: TechSkill): string {
    return `/blog?skill_tags=${skill.id}`;
}

function resolvedHref(): string | undefined {
    if (!props.linked) {
        return undefined;
    }

    // Feature callers may override the default blog-filter destination
    // (e.g. the landing's featured cards link to /projects?tags=).
    return props.href ?? blogFilterUrl(props.skill);
}
</script>

<template>
  <component
    :is="props.linked ? 'a' : 'span'"
    class="tech-skill-tag"
    :class="props.classes?.container"
    :href="resolvedHref()"
    :data-testid="props.test_id"
  >
    <Icon
      v-if="deviconReady && props.skill.icon"
      class="tech-skill-tag__icon"
      :class="props.classes?.icon"
      :icon="props.skill.icon"
      aria-hidden="true"
    />
    <span
      class="tech-skill-tag__name"
      :class="props.classes?.name"
    >
      {{ props.skill.name }}
    </span>
    <span
      v-if="props.showProficiency && props.skill.proficiency !== undefined"
      class="tech-skill-tag__proficiency"
      :class="props.classes?.proficiency"
      :data-testid="`${props.test_id}-proficiency`"
    >
      <span
        class="tech-skill-tag__proficiency-dot"
        :style="{ '--proficiency': String(props.skill.proficiency) }"
        aria-hidden="true"
      />
      {{ props.skill.proficiencyLabel }}
    </span>
  </component>
</template>

<style scoped>
.tech-skill-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35em;
    padding: 0.2em 0.65em;
    font-size: var(--font-size-sm);
    font-weight: var(--font-medium);
    color: var(--color-text-secondary);
    background: var(--color-bg-secondary);
    border: 1px dashed var(--color-border);
    border-radius: var(--radius-full);
    white-space: nowrap;
    text-decoration: none;
    transition: all var(--transition-fast) var(--ease-default);
}

a.tech-skill-tag:hover {
    background: var(--color-accent);
    border-color: var(--color-accent);
    color: #fff;
}

.tech-skill-tag__icon {
    width: 1em;
    height: 1em;
    flex-shrink: 0;
}

.tech-skill-tag__name {
    overflow: hidden;
    text-overflow: ellipsis;
}

.tech-skill-tag__proficiency {
    display: inline-flex;
    align-items: center;
    gap: 0.3em;
    font-size: 0.85em;
    color: var(--color-text-tertiary);
}

.tech-skill-tag__proficiency-dot {
    width: 0.6em;
    height: 0.6em;
    border-radius: var(--radius-full);

    /* 1–5 proficiency scale mapped to accent intensity. */
    background: color-mix(in srgb, var(--color-accent) calc(var(--proficiency) * 20%), var(--color-bg-tertiary));
}
</style>
