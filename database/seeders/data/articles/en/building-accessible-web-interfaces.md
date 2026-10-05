Accessibility is not a checklist you run once before launch. It is a set of constraints that make the interface work for everyone, keyboards and screen readers included.

## Start with semantic HTML

A `div` styled as a button is a button for nobody but the mouse. Use the element that carries the meaning and the behaviour follows.

```html
<button type="button" @click="submit">Submit</button>
<!-- not: <div class="button" @click="submit"> -->
```

> [!IMPORTANT]
> The first rule of ARIA is to not use ARIA when a native element already does the job.

## Focus is a feature

Every interactive element must be reachable and visible when focused. Removing focus outlines without an equivalent replacement is a regression.

| Element | Keyboard behaviour |
| --- | --- |
| Modal | trap focus, restore on close |
| Menu | arrow keys, `Escape` closes |
| Form | logical tab order |

## Test in CI

Automated checks catch a large share of issues. They do not replace manual keyboard and screen reader testing, but they stop regressions.

```bash
npx axe --exit public/
```

> [!TIP]
> Colour contrast failures are the easiest to catch automatically and the easiest to fix in the design tokens.

## Treat it as quality

Accessible interfaces are usually better interfaces: clearer hierarchy, predictable focus, honest labels.
