A design system is a shared language, not a component library. Figma is where the language is defined; code is where it is enforced.

## Tokens before components

Colour, spacing and typography live in variables. Components reference tokens, never raw values.

| Token | Value |
| --- | --- |
| `space-md` | 16px |
| `color-accent` | brand blue |
| `radius-md` | 8px |

> [!IMPORTANT]
> If a component hard-codes a hex value, it is not part of the system. It just looks like it is.

## Components with states

Document every state, not just the happy path: default, hover, focus, disabled, loading, error. Missing states are where designers and developers drift apart.

## Keep code and Figma in step

Name tokens and components identically in both places. The mapping should be obvious, not a translation exercise.

> [!TIP]
> Export tokens from a single source and generate both the Figma variables and the CSS custom properties.

## Contribution is part of the system

A design system that only one person can extend will be abandoned. Document how to propose, review and release a change.

## Measure adoption

Track how many screens use system components. Adoption, not component count, is the health metric.
