# Probha Aurora CMS — Block JSON Schema (v1.0)

| | |
|---|---|
| Document | CMS-BLOCK-SCHEMA.md (Phase 1 deliverables U, V) |
| Schema version | **1.0** (draft, frozen when Phase 6 ships) |
| Audience | PACMS developers, and anyone (human or AI such as ChatGPT, Claude or Gemini) generating PACMS JSON |
| Machine-readable schema | Generated in Phase 6 to `resources/schemas/pacms/1.0/*.schema.json` (JSON Schema 2020-12) and downloadable from **Admin → Import / Export → Schema** |

---

## 0. Rules for anyone generating PACMS JSON (including AI tools)

1. Output **JSON data only**. Never PHP, JavaScript, SQL, shell commands, `<script>`, event-handler attributes (`onclick` …), `javascript:` URLs or CSS expressions. **PACMS never executes imported content.** Anything code-like is rejected or stripped and listed in the import report.
2. Always include `"schema_version": "1.0"`.
3. Use only block `type`s listed in §5 (or custom types that exist on the target site, `custom/<slug>`).
4. Reference site content by **slug**, never by guessed numeric IDs: `{"$ref": {"entity": "programs", "slug": "eco-schools"}}`.
5. Reference images you want imported through the **`assets`** list and `{"$asset": "key"}`. Use `{"$media": 42}` only if you know the ID exists on *this* site. Missing IDs are reported and **never silently substituted**.
6. Prefer **design tokens** (`{"$token": "color.primary"}`) over literal colours and sizes.
7. Rich text is limited HTML (§8.3). Anything else is removed.
8. Imports always create **drafts**. Nothing goes live until a person publishes it.

---

## 1. Document envelope

Every import/export file is one envelope:

```json
{
  "schema_version": "1.0",
  "kind": "page",
  "meta": {
    "generator": "ChatGPT | Claude | Gemini | PACMS 1.0 export | …",
    "created_at": "2026-09-24T10:00:00+06:00",
    "title": "Programs landing page",
    "notes": "optional free text, shown in the import preview, never executed"
  },
  "assets": [ /* §9 */ ],
  "page":   { /* when kind = page      (§3) */ },
  "blocks": [ /* when kind = block | section | template: one or more root nodes (§2) */ ],
  "template": { /* when kind = template: name, slug, scope, category */ },
  "custom_block_types": [ /* optional: definitions the content needs (§10) */ ]
}
```

| `kind` | Contents | Import result |
|---|---|---|
| `block` | `blocks: [node]` (single node, children allowed) | inserted into a chosen page/position, or saved to clipboard |
| `section` | `blocks: [node…]` whose roots are `section` blocks | inserted into a page |
| `template` | `template` + `blocks` | new draft template |
| `page` | `page` (incl. its `blocks`) | new draft page (or replaces the working copy of a chosen page, with confirmation) |
| `site` | *reserved for a future full-site package* | not supported in 1.0 |

Limits (1.0): file ≤ 2 MB, ≤ 2,000 nodes, depth ≤ 12, ≤ 200 assets, strings ≤ 100,000 chars (rich text) / 2,048 (URLs) / 255 (short text).

---

## 2. Block node

```json
{
  "type": "hero",
  "key": "hero-main",
  "name": "Homepage hero",
  "hidden": false,
  "source":  { "mode": "static" },
  "content": { },
  "display": { },
  "layout":  { },
  "style":   { },
  "responsive": { "tablet": { }, "mobile": { } },
  "advanced": { },
  "children": [ ]
}
```

| Property | Required | Meaning |
|---|---|---|
| `type` | ✔ | Block type slug (§5). |
| `key` | – | Author-chosen identifier, unique within the document (letters, digits, `-`, `_`; ≤ 64). Used in import reports and error paths. PACMS assigns its own `uuid` on import. In **exports** `uuid` is also present and is ignored on re-import unless "update in place" is chosen. |
| `name` | – | Admin label. |
| `hidden` | – | `true` = kept but not rendered. |
| `source` | – | Where content comes from (§6). Default `{"mode":"static"}`. |
| `content` | – | Values for the block type's fields (§7, §8). |
| `display` | – | Display mode + options (§11). |
| `layout` | – | Positioning & dimensions (§12). |
| `style` | – | Visual appearance (§13). |
| `responsive` | – | Overrides for `tablet` / `mobile`. Each may contain `layout`, `style`, `display`, `visible` (§14). |
| `advanced` | – | Anchor, classes, attributes, custom CSS, visibility (§15). |
| `children` | – | Child nodes, only if the type allows children (§5). |

Only `type` is required. Everything omitted falls back to the block type defaults and then to design tokens.

---

## 3. Page object

```json
"page": {
  "title": "Our Programs",
  "slug": "programs",
  "parent": { "$ref": { "entity": "pages", "path": "about" } },
  "excerpt": "What we do and where.",
  "featured_image": { "$asset": "programs-hero" },
  "template": "default",
  "header": { "$ref": { "entity": "global_blocks", "slug": "main-header" } },
  "footer": null,
  "seo": {
    "title": "Programs | Example Organization",
    "description": "Environmental education programs …",
    "robots": { "index": true, "follow": true },
    "og": { "title": null, "description": null, "image": { "$asset": "programs-hero" } }
  },
  "blocks": [ /* root nodes, normally `section`s */ ]
}
```

`header`/`footer`: omitted = site default, `null` = none (landing page), `$ref` = specific global block.

---

## 4. Reference objects

All references are objects whose single key starts with `$`. They are **the only** way to point outside the document.

| Form | Meaning | Resolution on import |
|---|---|---|
| `{"$token": "color.primary"}` | Design token | Must exist in token catalogue (UI-DESIGN-SYSTEM.md §2). Unknown → reported, value dropped (falls back to default). |
| `{"$asset": "hero-bg"}` | Asset declared in `assets[]` | Per asset strategy (§9). |
| `{"$media": 42}` | Existing media ID on the target site | Must exist and be usable by the importing user. Missing → **reported as missing, field left empty**. Never substituted. |
| `{"$ref": {"entity": "news", "slug": "…"}}` | Content item | Must exist (any status; unpublished targets are reported as a warning because they won't show publicly). |
| `{"$ref": {"entity": "pages", "path": "about/team"}}` | Page by path | Same. |
| `{"$ref": {"entity": "terms", "taxonomy": "news_category", "slug": "environment"}}` | Category/tag | Missing → reported; option "create missing terms" in preview. |
| `{"$ref": {"entity": "global_blocks", "slug": "partner-logos"}}` | Global block | Missing → reported; the `global-ref` node is kept but disabled. |
| `{"$ref": {"entity": "external_sources", "slug": "un-environment-rss"}}` | Configured RSS/Facebook/… source | Missing → reported under **external configuration requirements** (source must be created by an admin). |
| `{"$bind": "photo"}` / `{"$bind": "item.url"}` | Field binding | Only valid inside a **custom block type structure** (§10). |

Entities accepted in `$ref`: `pages, news, events, projects, programs, publications, team, partners, testimonials, media_coverage, galleries, terms, global_blocks, templates, external_sources, menus`.

---

## 5. Block type catalogue (1.0)

`C` = may have children (allowed child types in brackets). `Src` = allowed source modes (S static, D dynamic, E external).

### Basic
| type | C | Src | content fields |
|---|---|---|---|
| `heading` | – | S | `text` (≤ 255), `level` (1–6, default 2), `eyebrow`? |
| `rich-text` | – | S | `html` (§8.3) |
| `image` | – | S | `image` (media), `alt`?, `caption`?, `link`? (§8.4), `lightbox` bool |
| `video` | – | S | `provider` (`youtube`/`vimeo`/`media`), `url` or `media`, `poster`?, `title` (required for a11y) |
| `button` | – | S | `label`, `link`, `variant` (`primary`/`secondary`/`accent`/`outline`/`link`), `icon`?, `icon_position` |
| `button-group` | ✔ [button] | S | `align` |
| `divider` | – | S | `style` (`solid`/`dashed`/`dotted`), `width` |
| `spacer` | – | S | `size` (space token) |
| `icon` | – | S | `icon` (Bootstrap Icons name, e.g. `bi-tree`), `label` (a11y), `size`, `link`? |

### Layout
| type | C | Src | content fields |
|---|---|---|---|
| `section` | ✔ [any except section] | S | `semantic` (`section`/`div`/`aside`), `aria_label`? |
| `container` | ✔ [any except section] | S | – |
| `columns` | ✔ [column] | S | – (column count/ratios in `layout.columns`) |
| `column` | ✔ [any except section, columns-in-columns ≤ 2 levels] | S | – |
| `flex-row` | ✔ [any basic/content] | S | – |

### Content
| type | C | Src | content fields |
|---|---|---|---|
| `hero` | ✔ [container, heading, rich-text, button, button-group, image, icon] | S | `background` via style; `min_height` via layout |
| `banner` | ✔ [same as hero] | S | – |
| `slider` | ✔ [slide] | S | – (behaviour in `display`) |
| `slide` | ✔ [basic, container, columns] | S | `background_image`? |
| `carousel` | – | S | `items` repeater: `image`, `title`, `text`, `link` |
| `cards` | – | S | `items` repeater: `image`, `icon`, `title`, `text`, `link` |
| `statistics` | – | S, D | `items` repeater: `value` (number), `prefix`, `suffix`, `label`, `icon`; D = computed counts (entity totals) |
| `timeline` | – | S | `items` repeater: `date_label`, `title`, `text`, `image` |
| `accordion` | ✔ [accordion-item] | S | `heading`?, `description`? |
| `accordion-item` | ✔ [basic, columns] | S | `title` (required). Children = the answer body |
| `faq` | – | S | `items` repeater: `question`, `answer` (rich text); emits FAQPage JSON-LD |
| `tabs` | ✔ [tab] | S | – |
| `tab` | ✔ [basic, columns] | S | `title` |
| `quote` | – | S | `text`, `author`, `role`, `image` |
| `cta` | ✔ [heading, rich-text, button, button-group] | S | – |

### Media
| type | C | Src | content fields |
|---|---|---|---|
| `gallery` | – | S, D, E | S: `items` repeater (`image`, `caption`, `credit`); D: via `source` (galleries / one gallery); E: external source |
| `video-gallery` | – | S, D, E | S: `items` (`provider`, `url`, `title`, `thumbnail`) |
| `document-list` | – | S, D | S: `items` (`file` media, `label`, `description`); D: publications |

### Collections (organizational + dynamic)
| type | Src | Static items (repeater) | Dynamic entity |
|---|---|---|---|
| `news` | S, D | `title, excerpt, image, link, date` | `news` |
| `events` | S, D | `title, date, venue, link, image` | `events` |
| `projects` | S, D | `title, excerpt, image, link` | `projects` |
| `programs` | S, D | `title, excerpt, image, link` | `programs` |
| `publications` | S, D | `title, cover, file, link, date` | `publications` |
| `team` | S, D | `name, designation, photo, link` | `team` |
| `partners` | S, D | `name, logo, link` | `partners` |
| `testimonials` | S, D | `quote, name, designation, organization, photo` | `testimonials` |
| `media-coverage` | S, D | `title, source_name, date, link, image` | `media_coverage` |
| `galleries` | D | – | `galleries` |

Shared collection `content` fields: `heading`?, `intro`?, `empty_text`?, `more_link`? (label + link), `card` options (`show_image`, `show_date`, `show_excerpt`, `show_category`, `show_author`, `image_ratio`).

### External
| type | Src | content fields |
|---|---|---|
| `rss-feed` | E | `heading`?, `show_image/title/excerpt/date/author/source/category`, `read_more_label`, `open_in_new_tab`, `empty_text` |
| `facebook-feed` | E | `heading`?, `show_image/text/date`, `follow_link_label`, `empty_text` |

### Structural
| type | C | Meaning |
|---|---|---|
| `global-ref` | – | `content.global`: `$ref` to a global block |
| `menu` | – | `content.menu`: `$ref` to a menu; `content.style`: `horizontal`/`vertical`/`mega-capable` (used in header/footer) |
| `site-logo`, `social-links`, `contact-info`, `copyright`, `newsletter` | – | Header/footer building blocks reading site settings |
| `repeat` | ✔ | Only in custom block structures: `content.field` = repeater field key |
| `custom/<slug>` | per type | Instance of a custom block type; `content` = its field values |

---

## 6. Sources

```json
"source": { "mode": "static" }

"source": {
  "mode": "dynamic",
  "provider": "cms",
  "entity": "news",
  "filters": { "category": {"$ref": {"entity":"terms","taxonomy":"news_category","slug":"environment"}},
               "featured": true },
  "order": "latest",
  "limit": 6,
  "pick": [ {"$ref": {"entity":"news","slug":"eco-schools-award"}} ]
}

"source": {
  "mode": "external",
  "provider": "rss",
  "source": {"$ref": {"entity":"external_sources","slug":"un-environment-rss"}},
  "limit": 5
}
```

| Key | Rules |
|---|---|
| `mode` | Must be in the block type's allowed modes. |
| `provider` | Dynamic: `cms` (default, may be omitted). External: provider key, must match the referenced source's provider. |
| `entity` | Must match the block type (a `news` block can only use `news`). |
| `filters` | Only keys the entity declares (below); unknown keys → reported + dropped. |
| `order` | `latest`, `oldest`, `title`, `manual` (+ `upcoming`/`past` for events, `position` for team/partners). `manual` uses `pick`. |
| `limit` | 1 – 24 (galleries/partners: 1 – 48). |
| `pick` | Explicit items (only with `order: manual`), ≤ 24. |

Allowed dynamic filters per entity (1.0):

| Entity | Filters |
|---|---|
| news | `category`, `tag`, `featured`, `program`, `project` |
| events | `category`, `when` (`upcoming`/`past`/`all`), `program`, `project` |
| projects | `project_status`, `program`, `featured` |
| programs | `featured` |
| publications | `category`, `featured`, `year` |
| team | `department` |
| partners | `category` |
| testimonials | `featured`, `program`, `project`, `event` |
| media_coverage | `category`, `coverage_type`, `featured`, `program`, `project` |
| galleries | `gallery_type`, `featured`, `event`, `program`, `project`, or `gallery` (single) |

External providers (1.0): `rss`, `facebook`; reserved: `flickr`, `youtube`, `instagram`, `linkedin`, `media-rss`.

---

## 7. Field types and JSON value shapes

| Field type | JSON value |
|---|---|
| text, textarea, email | string |
| rich-text | string of allowed HTML (§8.3) |
| number | number |
| url | link object (§8.4) or string URL |
| image, video, file | `{"$media": id}` or `{"$asset": "key"}` (+ optional `alt`, `caption` siblings when the block defines them) |
| gallery | array of image values |
| icon | `"bi-<name>"` (Bootstrap Icons) |
| color | token ref or `#RRGGBB` / `#RRGGBBAA` |
| date / time / datetime | `"2026-09-24"` / `"14:30"` / ISO-8601 with offset |
| checkbox | boolean |
| radio, select | string (one of the defined options) |
| multi-select | array of strings |
| relationship | `$ref` or array of `$ref` |
| repeater | array of objects keyed by sub-field keys |

---

## 8. Content rules

### 8.1 Plain text
Plain-text fields are stored as text and **escaped on output**; HTML tags in them are shown literally, not interpreted.

### 8.2 Placeholders
Only in `copyright` and mega/footer text fields: `{{year}}`, `{{site_name}}`. No other template syntax exists.

### 8.3 Rich text allowlist
Elements: `p, br, strong, em, u, s, sub, sup, a, ul, ol, li, blockquote, h2, h3, h4, h5, h6, code, pre, hr, table, thead, tbody, tr, th, td, figure, figcaption, img, span`.
Attributes: `a[href, title, target, rel]`, `img[src, alt, width, height]` (src must be a same-site media URL or an asset ref resolved on import), `th/td[colspan, rowspan, scope]`, `span[class]` with classes from a fixed list (`text-primary`, `text-accent`, `lead`, `small`, `visually-hidden`).
Links: `http`, `https`, `mailto`, `tel`, relative, `#anchor`. `target="_blank"` gets `rel="noopener noreferrer"` automatically.
Everything else (scripts, styles, iframes, forms, event handlers, `style` attributes, data URIs) is removed and **reported**.

### 8.4 Link object
```json
{ "type": "url", "url": "https://example.org", "new_tab": true }
{ "type": "entity", "ref": {"$ref": {"entity": "programs", "slug": "eco-schools"}} }
{ "type": "anchor", "anchor": "contact" }
{ "type": "email", "email": "info@example.org" }
```
Entity links are resolved at render time, so slug changes don't break them.

---

## 9. Assets

```json
"assets": [
  { "key": "programs-hero", "url": "https://images.example.org/hero.jpg",
    "alt": "Students planting trees", "credit": "Photo: Example", "strategy": "download" },
  { "key": "partner-logo-1", "url": "https://cdn.partner.org/logo.png",
    "alt": "Partner logo", "strategy": "external" },
  { "key": "team-photo", "media": 42, "strategy": "existing" }
]
```

| strategy | Behaviour |
|---|---|
| `download` | Server downloads via SafeHttpClient (SSRF-guarded, ≤ 20 MB, image/document MIME allowlist), validates, re-encodes images, creates a Media Library item. Failure → reported, field empty. |
| `external` | Keeps the external URL (images only, `https` only). Shown with a warning in admin (not optimized, may disappear, third-party request). |
| `existing` | Uses an existing Media Library item (`media` id). Missing → reported, **not substituted**. |
| *(replace)* | In the import preview the user may map any asset to a different existing media item. This is an explicit user choice, recorded in the report. |

The strategy in the file is a **suggestion**; the importing user confirms or changes it per asset in the preview.

---

## 10. Custom block type definitions (optional in envelope)

```json
"custom_block_types": [{
  "slug": "staff-profile",
  "name": "Staff Profile",
  "category": "organization",
  "icon": "bi-person-badge",
  "fields": [
    { "key": "name",  "type": "text", "label": "Name", "required": true, "max": 120 },
    { "key": "photo", "type": "image", "label": "Photo" },
    { "key": "role",  "type": "text", "label": "Role" },
    { "key": "bio",   "type": "rich-text", "label": "Biography" },
    { "key": "links", "type": "repeater", "label": "Links", "max": 6,
      "fields": [ { "key": "label", "type": "text", "required": true },
                  { "key": "url",   "type": "url",  "required": true } ] }
  ],
  "structure": [{
    "type": "columns", "layout": { "columns": { "desktop": [4, 8], "mobile": [12] } },
    "children": [
      { "type": "column", "children": [
          { "type": "image", "content": { "image": {"$bind": "photo"}, "alt": {"$bind": "name"} } } ] },
      { "type": "column", "children": [
          { "type": "heading", "content": { "text": {"$bind": "name"}, "level": 3 } },
          { "type": "heading", "content": { "text": {"$bind": "role"}, "level": 4 },
            "advanced": { "when": { "field": "role", "is": "filled" } } },
          { "type": "rich-text", "content": { "html": {"$bind": "bio"} } },
          { "type": "repeat", "content": { "field": "links" }, "children": [
              { "type": "button", "content": { "label": {"$bind": "item.label"},
                                               "link": {"type": "url", "url": {"$bind": "item.url"}},
                                               "variant": "link" } } ] } ] }
    ]
  }]
}]
```

Importing a custom type requires `block_types.manage`. If the site already has a type with the same slug, the preview offers: use existing (if field-compatible), import as new slug, or skip (dependent nodes reported).

---

## 11. Display

```json
"display": {
  "mode": "carousel",
  "columns": { "desktop": 3, "tablet": 2, "mobile": 1 },
  "gap": {"$token": "space.4"},
  "autoplay": false, "interval": 6000, "loop": true, "arrows": true, "dots": true,
  "lightbox": false,
  "card_style": "elevated", "image_ratio": "16:9"
}
```

Modes: `grid, cards, list, masonry, justified, carousel, slider, featured, quote-slider, thumbnail-large, single, accordion, tabs`. Allowed modes depend on block type (CMS-ARCHITECTURE.md §7). `columns` 1–6; `interval` 3000–20000 ms; `image_ratio` one of `1:1, 4:3, 3:2, 16:9, 21:9, auto`. Autoplay is always suppressed for visitors with `prefers-reduced-motion`.

---

## 12. Layout

```json
"layout": {
  "container": "boxed",
  "max_width": {"$token": "container.narrow"},
  "min_height": {"value": 70, "unit": "vh"},
  "columns": { "desktop": [6, 6], "tablet": [12, 12] },
  "align": "center",
  "justify": "between",
  "gap": {"$token": "space.5"},
  "padding": { "top": {"$token": "space.section"}, "bottom": {"$token": "space.section"} },
  "margin":  { "bottom": {"$token": "space.6"} },
  "position": "relative"
}
```

| Key | Values |
|---|---|
| `container` | `boxed` (site container), `narrow`, `fluid` (padded full width), `full` (edge to edge) |
| `width`, `max_width`, `min_height`, `height` | token or `{value, unit}`; units `px, rem, em, %, vh, vw` |
| `columns` | per breakpoint: array of Bootstrap 12-grid spans summing to 12 per row, or `"auto"` |
| `align` | `start, center, end, stretch, baseline` (cross axis) |
| `justify` | `start, center, end, between, around, evenly` |
| `gap`, `padding.*`, `margin.*` | space token or `{value, unit}` (margin may be negative `≥ -200px`) |
| `position` | `static, relative, sticky` (`sticky` → `top` offset allowed) |
| `text_align` | `start, center, end` |

---

## 13. Style

```json
"style": {
  "background": {
    "type": "image",
    "image": {"$asset": "programs-hero"},
    "position": "center", "size": "cover", "attachment": "scroll",
    "overlay": { "color": {"$token": "color.bg-dark"}, "opacity": 0.55 }
  },
  "typography": { "color": {"$token": "color.white"}, "font": {"$token": "font.heading"},
                  "size": {"$token": "font-size.xl"}, "weight": 600, "align": "center",
                  "transform": "none" },
  "border": { "width": {"value": 1, "unit": "px"}, "style": "solid", "color": {"$token": "color.border"},
              "sides": ["bottom"] },
  "radius": {"$token": "radius.lg"},
  "shadow": {"$token": "shadow.md"},
  "animation": { "type": "fade-up", "delay": 0, "duration": 500, "once": true }
}
```

| Key | Values |
|---|---|
| `background.type` | `none, color, gradient, image, video` |
| `background.gradient` | `{ "angle": 0–360, "stops": [ {color, at: 0–100} ×2–4 ] }` |
| `background.video` | media/asset (muted, loop, `poster` required; paused under reduced motion) |
| `typography.weight` | 300–900 step 100 |
| `animation.type` | `none, fade, fade-up, fade-down, slide-left, slide-right, zoom` (disabled under reduced motion) |
| `radius`, `shadow` | token only |

When text sits over an image background, the builder warns if no overlay is set (contrast risk).

---

## 14. Responsive

```json
"responsive": {
  "tablet": { "layout": { "padding": { "top": {"$token": "space.6"} } } },
  "mobile": { "layout": { "min_height": {"value": 60, "unit": "vh"} },
              "style":  { "typography": { "size": {"$token": "font-size.lg"} } },
              "display": { "columns": 1 },
              "visible": true }
}
```

Desktop values are the base; `tablet` overrides desktop; `mobile` overrides tablet. Only differing values should be present. Breakpoints: desktop ≥ 992 px, tablet 768–991.98 px, mobile < 768 px.

---

## 15. Advanced

```json
"advanced": {
  "anchor": "programs",
  "classes": ["pa-section-highlight"],
  "attributes": { "data-track": "programs", "aria-label": "Our programs" },
  "custom_css": "& .card-title { letter-spacing: .02em; }",
  "visibility": { "hide_on": ["mobile"], "audience": "everyone" },
  "when": null
}
```

| Key | Rules |
|---|---|
| `anchor` | `[a-z][a-z0-9-]{0,63}`, unique per page |
| `classes` | each `[a-z][a-z0-9-_]{0,63}` |
| `attributes` | only `data-*` and `aria-*`, plus `role`, `title`, `lang`; values plain text ≤ 255. **No `on*`, `style`, `href`, `src`, `id`.** |
| `custom_css` | **requires `blocks.custom_css` permission** (import without it → stripped + reported). Scoped: `&` = this block; all selectors are prefixed with the block scope; `@import`, `url()` except same-site media, `expression`, `behavior`, `-moz-binding`, `</style` are rejected. ≤ 10 KB. |
| `visibility.hide_on` | subset of `desktop, tablet, mobile` |
| `visibility.audience` | `everyone, guests, members` (presentation only, **not** a security boundary) |
| `when` | only in custom block structures: `{ "field": key, "is": "filled" | "empty" }` or `{ "field": key, "equals": literal }` |

---

## 16. Validation (import & builder save)

Validation runs in this order; each stage produces report entries with a **JSON Pointer** path (`/page/blocks/2/children/0/content/text`) and the node `key`.

| Stage | Checks | Severity |
|---|---|---|
| 1. Parse | valid UTF-8 JSON, size ≤ 2 MB, depth, node count | error (stop) |
| 2. Envelope | `schema_version` supported (or migratable), `kind`, required sections | error (stop) |
| 3. Version migration | older versions upgraded by migrators (e.g. 1.0 → 1.1); each change noted | info |
| 4. Schema | JSON Schema per block type (generated from field definitions), child/parent rules, enums, lengths, units, token names | error per node |
| 5. Security | code-like content, HTML sanitation diff, URL protocols, attribute allowlist, custom CSS permission & sanitation, oversized strings | stripped + warning; forbidden constructs → error |
| 6. References | `$ref`, `$media`, `$token`, `$asset` keys, external sources, custom types | missing → warning (node kept, field empty) or error (required field) |
| 7. Unsupported | unknown properties/keys | stripped + warning ("unsupported property") |

Import proceeds only when there are **no errors**; warnings require explicit confirmation.

---

## 17. Import pipeline

```
JSON (paste / upload / AI output)
 → Parse & limits                  (§16.1–2)
 → Schema version check/migrate    (§16.3, §19)
 → Schema validation               (§16.4)
 → Security validation             (§16.5)
 → Reference validation            (§16.6)
 → Asset plan                      (§9: per-asset strategy chosen by user)
 → PREVIEW   (import_jobs.status = awaiting_confirmation; rendered in the builder preview iframe
              from the plan; report shown side by side)
 → USER CONFIRMATION               (explicit; warnings acknowledged; target chosen)
 → IMPORT    (queued job: download assets first → then one DB transaction creating
              draft page/template/blocks/terms; failure → full rollback, downloaded
              assets kept as orphans flagged for cleanup)
 → REPORT    (stored in import_jobs.report; downloadable JSON; activity log entry)
```

### Import report contents

| Section | Content |
|---|---|
| Summary | kind, schema version (and migrations applied), created entities, target |
| Pages | title, slug (+ adjusted slug if taken), status = draft |
| Blocks | count by type; nodes dropped (with reason) |
| Static content | nodes with static content |
| Dynamic content | nodes with dynamic sources + resolved entity/filters |
| Missing assets | asset keys that failed download or missing `$media` |
| Missing references | unresolved `$ref`s with path |
| Unsupported properties | stripped keys with path |
| Security changes | sanitized HTML/attributes/CSS with before/after summary |
| External configuration requirements | external sources that must be created/connected (RSS URL, Facebook page) |

Permissions: `import.run` + create permission for the target type; custom CSS & custom types need their own permissions (§15, §10).

---

## 18. Export

| Scope | Output `kind` | Contains |
|---|---|---|
| Individual block | `block` | node + descendants |
| Section | `section` | section node(s) |
| Template | `template` | template meta + tree |
| Complete page | `page` | page fields, SEO, tree |
| *(future)* Complete site | `site` | reserved |

Export rules:
- Media → `assets[]` entries with `strategy: "existing"`, `media` id, **and** the public `url` (so another site can switch to `download`). Private media is exported as a reference only (no URL).
- Entities → `$ref` by slug/path; tokens stay `$token`.
- Global blocks → `global-ref` with `$ref` (option: "inline global blocks" deep-copies them).
- Custom types used → included in `custom_block_types` (option).
- `uuid` included for round-trip "update in place".
- Never exported: user IDs, emails, revision history, provider credentials, internal notes.
- Round-trip guarantee (tested): export → import on the same site produces an equivalent tree.

---

## 19. Versioning policy

- `schema_version` is `MAJOR.MINOR`.
- **Minor** (1.0 → 1.1): additive only (new block types, optional properties, new enum values). Older files import unchanged. Newer-minor files on an older site: unknown types/properties are reported as unsupported.
- **Major** (1.x → 2.0): breaking changes; PACMS ships a **migrator** `V1ToV2` that upgrades old documents on import; old majors stay importable for at least one major after.
- Stored snapshots (`revisions.schema_version`) are migrated lazily when read.
- Every change to this document updates the changelog below and the machine-readable schema in the same commit.

| Version | Date | Change |
|---|---|---|
| 1.0-draft | 2026-09-24 | Initial Phase 1 design |

---

## 20. Complete examples

### 20.1 Hero with nested content (static)

```json
{
  "schema_version": "1.0",
  "kind": "section",
  "meta": { "generator": "Claude", "title": "Homepage hero" },
  "assets": [ { "key": "hero-bg", "url": "https://images.example.org/forest.jpg",
                "alt": "Young volunteers planting mangroves", "strategy": "download" } ],
  "blocks": [{
    "type": "hero", "key": "hero",
    "layout": { "container": "full", "min_height": {"value": 80, "unit": "vh"}, "align": "center" },
    "style": { "background": { "type": "image", "image": {"$asset": "hero-bg"}, "size": "cover",
                               "overlay": { "color": {"$token": "color.bg-dark"}, "opacity": 0.5 } },
               "typography": { "color": {"$token": "color.white"}, "align": "center" } },
    "responsive": { "mobile": { "layout": { "min_height": {"value": 60, "unit": "vh"} } } },
    "children": [{
      "type": "container", "layout": { "max_width": {"$token": "container.narrow"} },
      "children": [
        { "type": "heading", "content": { "text": "Education for a greener tomorrow", "level": 1 } },
        { "type": "rich-text", "content": { "html": "<p>We help schools and young people take action for the environment.</p>" } },
        { "type": "button-group", "content": { "align": "center" }, "children": [
          { "type": "button", "content": { "label": "Our programs", "variant": "accent",
              "link": { "type": "entity", "ref": {"$ref": {"entity": "pages", "path": "programs"}} } } },
          { "type": "button", "content": { "label": "Get involved", "variant": "outline",
              "link": { "type": "anchor", "anchor": "join" } } } ] }
      ]
    }]
  }]
}
```

### 20.2 Dynamic news carousel

```json
{
  "schema_version": "1.0", "kind": "block",
  "blocks": [{
    "type": "news", "key": "latest-news",
    "content": { "heading": "Latest news", "more_link": { "label": "All news",
                 "link": { "type": "url", "url": "/news" } },
                 "card": { "show_image": true, "show_date": true, "show_excerpt": true } },
    "source": { "mode": "dynamic", "provider": "cms", "entity": "news",
                "filters": { "featured": true }, "order": "latest", "limit": 6 },
    "display": { "mode": "carousel", "columns": { "desktop": 3, "tablet": 2, "mobile": 1 },
                 "arrows": true, "dots": false }
  }]
}
```

### 20.3 Accordion (children) and FAQ (repeater)

```json
{
  "schema_version": "1.0", "kind": "section",
  "blocks": [{
    "type": "section", "layout": { "container": "narrow" },
    "children": [
      { "type": "accordion", "content": { "heading": "How it works" }, "display": { "mode": "accordion" },
        "children": [
          { "type": "accordion-item", "content": { "title": "Who can join?" },
            "children": [ { "type": "rich-text", "content": { "html": "<p>Any school in the country.</p>" } } ] },
          { "type": "accordion-item", "content": { "title": "Is there a fee?" },
            "children": [ { "type": "rich-text", "content": { "html": "<p>No.</p>" } } ] } ] },
      { "type": "faq", "content": { "items": [
          { "question": "How do I contact you?", "answer": "<p>Use the contact form.</p>" } ] } }
    ]
  }]
}
```

### 20.4 External RSS block

```json
{
  "schema_version": "1.0", "kind": "block",
  "blocks": [{
    "type": "rss-feed",
    "content": { "heading": "From our partners", "show_image": true, "show_date": true,
                 "show_source": true, "open_in_new_tab": true, "empty_text": "No updates right now." },
    "source": { "mode": "external", "provider": "rss",
                "source": {"$ref": {"entity": "external_sources", "slug": "partner-news"}}, "limit": 4 },
    "display": { "mode": "cards", "columns": { "desktop": 4, "tablet": 2, "mobile": 1 } }
  }]
}
```
*(If `partner-news` does not exist on the target site, the import report lists it under "External configuration requirements".)*

### 20.5 Minimal full page

```json
{
  "schema_version": "1.0", "kind": "page",
  "page": {
    "title": "Our Programs", "slug": "programs",
    "seo": { "description": "Environmental education programs for schools and youth." },
    "blocks": [
      { "type": "section", "children": [
          { "type": "heading", "content": { "text": "Our Programs", "level": 1 } } ] },
      { "type": "section", "children": [
          { "type": "programs", "source": { "mode": "dynamic", "entity": "programs", "order": "title", "limit": 12 },
            "display": { "mode": "cards", "columns": { "desktop": 3, "tablet": 2, "mobile": 1 } } } ] },
      { "type": "global-ref", "content": { "global": {"$ref": {"entity": "global_blocks", "slug": "cta-donate"}} } }
    ]
  }
}
```
