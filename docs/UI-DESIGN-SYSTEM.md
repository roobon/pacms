# Probha Aurora CMS — UI Design System

| | |
|---|---|
| Document | UI-DESIGN-SYSTEM.md (Phase 1 deliverable J + admin/public UI foundations) |
| Status | Draft for team review, with a **proposed** default palette (the organisation's brand replaces it through tokens) |
| Stack | Bootstrap 5.3 (Sass subset + CSS variables) · Bootstrap Icons 1.13 · no Font Awesome |
| Related | [CMS-ARCHITECTURE.md §8–10](CMS-ARCHITECTURE.md) · [CMS-BLOCK-SCHEMA.md §11–14](CMS-BLOCK-SCHEMA.md) |

---

## 1. Approach

- **Bootstrap is the engine, not the look.** We compile a Bootstrap subset from Sass (grid, utilities, reboot, and only the components we use). A PACMS theme layer then restyles components through CSS custom properties, so the site shouldn't look like default Bootstrap.
- **Two layers of tokens:**
  1. **Build-time defaults** in Sass (`resources/scss/tokens/_defaults.scss`). These are the fallback values if runtime tokens are missing.
  2. **Runtime tokens** stored in the database, edited in **Admin → Design → Tokens**, and emitted as a small `tokens.<hash>.css` file of `--pa-*` custom properties. **Rebranding needs no rebuild.**
- The public site uses the organisation's tokens. **The admin uses its own fixed, neutral admin theme** (§8), so an editor's brand change can never make the admin unreadable.
- Accessibility is part of every component definition, not a separate audit step.

---

## 2. Token catalogue

Token names are what blocks and JSON reference (`{"$token": "color.primary"}`). The CSS variable is `--pa-` followed by the name with dots replaced by `-`.

### 2.1 Colour (proposed default "Aurora" palette)

| Token | Default | Use | Contrast (WCAG 2.2) |
|---|---|---|---|
| `color.primary` | `#0A6B66` (deep aurora teal) | primary buttons, links, active states | white on it **6.35:1** ✔ AA; on `bg-light` as link 5.90:1 ✔ |
| `color.primary-strong` | `#08564F` | hover/pressed (derived, overridable) | white 8.56:1 ✔ |
| `color.secondary` | `#23305E` (night indigo) | secondary buttons, dark sections | white 12.68:1 ✔ AAA |
| `color.accent` | `#F2A93B` (aurora gold) | highlights, CTA accents, badges | **use with dark text only**: heading on it 8.65:1 ✔; **not** for text on white (2.0:1 ✘) |
| `color.heading` | `#0F1B2D` | headings | on white 17.28:1 ✔ |
| `color.body` | `#334155` | body text | on white 10.35:1, on `bg-light` 9.62:1 ✔ |
| `color.muted` | `#5B6B7F` | secondary text, meta | on white 5.45:1, on `bg-light` 5.06:1 ✔ AA |
| `color.bg-light` | `#F4F7F8` | alternate section background | — |
| `color.bg-dark` | `#0B1628` | footer, dark sections, overlays | white on it 18.11:1; accent on it 9.07:1 ✔ |
| `color.border` | `#D5DEE4` | dividers, card/input borders (decorative) | 1.36:1 (decorative only; input borders use `color.border-strong`) |
| `color.border-strong` | `#7D8EA3` | form control borders (UI component ≥ 3:1) | on white 3.35:1, on `bg-light` 3.11:1 ✔ |
| `color.success` | `#1B7A4B` | success | white 5.34:1 ✔ |
| `color.warning` | `#F4B740` | warning (backgrounds) | heading-colour text 9.62:1 ✔; never white text |
| `color.danger` | `#B8322A` | errors, destructive | white 5.95:1 ✔ |
| `color.focus` | `#1B64D1` | focus ring | 5.54:1 on white ✔ (≥ 3:1 required) |
| `color.white` / `color.black` | `#FFFFFF` / `#000000` | | |

These ratios were computed for this document (2026-09-24). The token editor re-checks the defined text/background pairs and **warns before saving any pair below AA** (4.5:1 for text, 3:1 for large text and UI components).

### 2.2 Typography

| Token | Default |
|---|---|
| `font.heading` | one of a fixed list: **Plus Jakarta Sans** (default), Inter, System UI, Serif |
| `font.body` | one of a fixed list: **Inter** (default), Plus Jakarta Sans, System UI, Serif |
| `font.mono` | `ui-monospace, "SFMono-Regular", Consolas, monospace` |
| `font-weight.regular / medium / semibold / bold` | 400 / 500 / 600 / 700 |
| `line-height.tight / base / relaxed` | 1.2 / 1.6 / 1.75 |

Fonts are chosen from a fixed list in the token editor (free text is not accepted). They are **self-hosted WOFF2 variable fonts** (`@fontsource-variable`), with no Google Fonts CDN at runtime. The site is English-only (Q-02), so no Bengali font is bundled; Bangla text inside content falls back to the system font. Adding one later is a token-list change.

**Fluid type scale** (min at 360 px → max at 1320 px viewport):

| Token | Size (clamp) | Typical use |
|---|---|---|
| `font-size.xs` | 0.75rem | captions, legal |
| `font-size.sm` | 0.875rem | meta, labels |
| `font-size.base` | 1rem → 1.0625rem | body |
| `font-size.lg` | 1.125rem → 1.25rem | lead text |
| `font-size.xl` | 1.25rem → 1.5rem | h5/h4 |
| `font-size.2xl` | 1.5rem → 1.875rem | h3 |
| `font-size.3xl` | 1.875rem → 2.375rem | h2 |
| `font-size.4xl` | 2.25rem → 3rem | h1 |
| `font-size.5xl` | 2.75rem → 4rem | hero display |

### 2.3 Spacing (4 px base)

| Token | Value | | Token | Value |
|---|---|---|---|---|
| `space.0` | 0 | | `space.6` | 2rem |
| `space.1` | 0.25rem | | `space.7` | 3rem |
| `space.2` | 0.5rem | | `space.8` | 4rem |
| `space.3` | 0.75rem | | `space.9` | 6rem |
| `space.4` | 1rem | | `space.10` | 8rem |
| `space.5` | 1.5rem | | `space.section` | clamp(3rem, 6vw, 6rem) |

### 2.4 Layout

| Token | Value |
|---|---|
| `container.sm / md / lg / xl / xxl` | 540 / 720 / 960 / 1140 / 1280 px (xxl narrowed from Bootstrap's 1320 for line length) |
| `container.narrow` | 760 px (long-form reading) |
| `container.wide` | 1440 px |
| `gutter` | 1.5rem (mobile 1rem, which is the 16 px side gutter) |
| Breakpoints (fixed, not tokens) | mobile < 768, tablet 768–991.98, desktop ≥ 992 |

### 2.5 Shape and depth

| Token | Value |
|---|---|
| `radius.none / sm / md / lg / xl / pill` | 0 / 0.375rem / 0.625rem / 1rem / 1.5rem / 999px |
| `shadow.none` | none |
| `shadow.sm` | `0 1px 2px rgb(15 27 45 / .06), 0 1px 3px rgb(15 27 45 / .08)` |
| `shadow.md` | `0 4px 12px rgb(15 27 45 / .08)` |
| `shadow.lg` | `0 12px 32px rgb(15 27 45 / .12)` |
| `motion.duration.fast / base / slow` | 120 / 200 / 320 ms |
| `motion.easing` | `cubic-bezier(.2, .7, .2, 1)` |

### 2.6 Component tokens

| Token | Default |
|---|---|
| `button.radius` | `radius.md` |
| `button.padding-y / padding-x` | 0.625rem / 1.25rem |
| `button.font-weight` | 600 |
| `button.text-transform` | none |
| `form.radius` | `radius.sm` |
| `form.border-color` | `color.border-strong` |
| `form.focus-ring` | `0 0 0 3px color-mix(in srgb, var(--pa-color-focus) 35%, transparent)` |
| `card.radius` | `radius.lg` |
| `card.shadow` | `shadow.sm`, hover `shadow.md` |

---

## 3. From tokens to Bootstrap

`tokens.<hash>.css` (generated):
```css
:root {
  --pa-color-primary: #0A6B66;
  --pa-color-primary-strong: #08564F;
  --pa-font-body: "Inter", "Noto Sans Bengali", system-ui, sans-serif;
  --pa-space-4: 1rem;
  --pa-radius-md: .625rem;
  /* … */
}
```

`resources/scss/public/_bridge.scss` (compiled once at build time) maps Bootstrap's variables to ours:
```scss
:root {
  --bs-body-font-family: var(--pa-font-body);
  --bs-body-color: var(--pa-color-body);
  --bs-link-color-rgb: /* set in tokens.css as --pa-color-primary-rgb */ var(--pa-color-primary-rgb);
  --bs-border-color: var(--pa-color-border);
  --bs-border-radius: var(--pa-radius-md);
  --bs-focus-ring-color: color-mix(in srgb, var(--pa-color-focus) 35%, transparent);
}
.btn-primary {
  --bs-btn-bg: var(--pa-color-primary);
  --bs-btn-border-color: var(--pa-color-primary);
  --bs-btn-hover-bg: var(--pa-color-primary-strong);
  --bs-btn-active-bg: color-mix(in srgb, var(--pa-color-primary-strong) 85%, black);
  --bs-btn-color: var(--pa-color-on-primary);
  --bs-btn-border-radius: var(--pa-button-radius);
  --bs-btn-font-weight: var(--pa-button-font-weight);
}
```

- The token generator also emits `*-rgb` triplets and computes **`on-*` colours** (the best readable text colour for each fill), so token changes can't produce unreadable buttons.
- `color-mix()` is supported in all current evergreen browsers. Older browsers fall back to the non-mixed base value declared first.

---

## 4. Components (public)

| Component | Styling decisions | Accessibility requirements |
|---|---|---|
| Buttons | Token radius/weight. Variants: primary, secondary, accent (dark text), outline, link. Min target 44×44 px. Icon + label spacing `space.2`. | Real `<button>` or `<a>`. Icon-only buttons need `aria-label`. Visible focus ring. Disabled state is not colour-only. |
| Links | Primary colour, underline on hover and **always inside body text**. | External links in new tabs are announced with a visually hidden "(opens in new tab)" and icon. |
| Forms | Labels above fields. `border-strong` borders. Error text uses the danger colour **plus an icon**. Helper text is muted. | `<label for>`, `aria-describedby` for help and errors, `aria-invalid`, error summary on submit with focus moved to it, required fields marked in text. |
| Cards | `card.radius`, subtle shadow, image ratio from display options, whole card clickable through a stretched link (one link per card). | Heading level fits the section outline. Link text is the title, not "Read more" alone. |
| Tables | Striped off, row borders only, sticky header on admin, horizontal scroll wrapper on mobile. | `<th scope>`, caption (visually hidden allowed). |
| Navigation | Header height 72 px desktop / 64 px mobile. Dropdowns use `shadow.lg`. Mega menu uses the full container width. | Disclosure pattern (`aria-expanded`, Esc closes, focus returns), `aria-current="page"`, skip link first in DOM. |
| Badges | Pill radius. Neutral, primary, accent, success, warning, danger. | Never the only carrier of meaning. |
| Alerts | Left accent bar + icon + title. | `role="status"` for info, `role="alert"` only for urgent errors. |
| Modals / lightbox | `radius.lg`, dark translucent backdrop. | Focus trap, Esc closes, focus returns to trigger, `aria-labelledby`, page scroll locked. |
| Carousel / slider | Visible prev/next and pause controls. Dots optional. | Pause and stop available. Autoplay off under `prefers-reduced-motion`. Slides announced "Slide 2 of 5". Hidden slides are `inert`. |
| Accordion / tabs | Bootstrap collapse/tab with theme styling. | WAI-ARIA accordion and tabs patterns (arrow keys for tabs). |
| Empty states | Icon (Bootstrap Icons, muted), one-line explanation, optional action. | Text, not just an illustration. |
| Loading states | Skeletons matching final layout (no layout shift). Spinners only for actions under ~1 s. | `aria-busy` on the region, a "Loading…" text for screen readers. |
| Error states | Friendly title + what to do + retry. Never raw error text. | Focus moved to the error heading on full-page errors. |

---

## 5. Micro-interactions and motion

- Hover: cards lift (`translateY(-2px)` + `shadow.md`) over `motion.duration.base`. Buttons darken through `primary-strong`.
- Focus: an instant ring (`color.focus`, 3 px), never animated away.
- Block entrance animations (`fade-up` etc.) run once, only on scroll into view, and are off under `prefers-reduced-motion`.
- No parallax by default, no animated counters that block reading (the statistics count-up is optional and reduced-motion aware), and no auto-playing video with sound.

---

## 6. Iconography

- **Bootstrap Icons** only (MIT). Names are stored as `bi-<name>`.
- Public site: the icon webfont is self-hosted and preloaded (≈ 130 KB WOFF2, loaded once and cached). Phase 13 evaluates switching to an on-demand SVG sprite of used icons if the font weight matters (R-12).
- Decorative icons have `aria-hidden="true"`. Meaningful icons have a text alternative.

---

## 7. Imagery

- Aspect ratios come from display options (`1:1, 4:3, 3:2, 16:9, 21:9`) with `object-fit: cover` and the media focal point (`object-position`).
- Text over images is always paired with an overlay token. The builder warns if none is set.
- Alt text is required at upload unless the image is marked decorative.

---

## 8. Admin design system

The admin is a separate theme compiled from the same Bootstrap subset with **fixed admin tokens**. It does not use the organisation's runtime tokens.

| Aspect | Decision |
|---|---|
| Palette | Neutral slate UI (`#0F172A` sidebar, `#F8FAFC` canvas, white panels), **Aurora teal `#0A6B66` as admin primary**, standard success/warning/danger. |
| Layout | Fixed left sidebar (collapsible, icons + labels, off-canvas on mobile), top bar (search, "View site", notifications, user menu), content area max 1440 px. |
| Density | Comfortable by default. Tables use `table-sm` spacing in a "compact" preference (stored per user). |
| Forms | Two-column edit layout: main fields + a right "publish box" sidebar (status, workflow buttons, schedule, featured image, taxonomy, SEO, revisions). |
| Status colours | Draft (slate), In Review (indigo), Approved (teal outline), Published (green), Scheduled (gold), Archived (grey), Rejected (red). Always **badge + text**. |
| Builder | Dark-neutral chrome around a light canvas so the page being edited stays visually dominant. The inspector uses tabs (Content / Layout / Style / Advanced) with a device switcher on responsive-capable controls. |
| Feedback | Toasts for success (auto-dismiss 5 s, pausable), inline errors for validation, a confirm dialog for destructive actions that states *what* will be deleted and usage counts. |
| Dark mode | Not in v1 (admin). The token structure allows adding it later. |

---

## 9. Implementation structure

```
resources/scss/
├── tokens/_defaults.scss        build-time fallbacks (mirror §2)
├── bootstrap/_subset.scss       only used Bootstrap modules
├── public/
│   ├── _bridge.scss             Bootstrap vars → --pa-* tokens
│   ├── components/_buttons.scss, _cards.scss, _nav.scss, _forms.scss, …
│   ├── blocks/_hero.scss, _gallery.scss, … (block-specific, minimal)
│   └── public.scss
└── admin/
    ├── _admin-tokens.scss
    ├── components/…
    └── admin.scss
```

- Utility-first where Bootstrap utilities suffice. Components get SCSS only when utilities would repeat.
- **No inline styles in components.** Block styling comes only from the StyleCompiler's scoped classes.
- A **design-system preview page** (`/admin/design/preview`, Phase 2) renders every component with the current tokens. It is used for visual review and contrast checks in each phase.
