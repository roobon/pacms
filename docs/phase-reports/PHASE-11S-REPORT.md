# Phase 11S — Starter kit designs · Report

| | |
|---|---|
| Date | 2026-10-12 |
| Branch | `phase/11s-starter-designs` (from `main` after 11A) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Awaiting review by the team lead. |

```
PHASE:   11S — more starter kit designs, requested by the team lead (2026-10-12): five designs
         to choose from for the header, footer, Home, About us, Contact and Photo gallery pages,
         with pictures wherever a design is chosen.
STATUS:  READY FOR REVIEW. 11B (states, accessibility, performance, OpenAPI) follows; v0.11.0
         is tagged after 11B.
         Decisions (team lead, 2026-10-12, "use your defaults"): K-1 five style families;
         K-2 grouped pickers with pictures; K-3 no photos shipped; K-4 install adds, never
         changes, plus --style; K-5 small block additions; K-6 own branch and report.
```

## The five styles

Each design number is one style used across every area. A site can use one style everywhere, or mix them.

| Style | Header | Footer | Home | About us | Contact | Photo gallery |
|---|---|---|---|---|---|---|
| **Classic** | Logo, menu, search, sign-in | Dark, three columns | Gradient hero, numbers, news, programmes and events, testimonials, partners | Mission, vision and values | Details, form, questions | Albums grid |
| **Bold** | Dark bar, Donate button | Big call-to-action band above a dark footer | Full-width photo slider, numbers on dark, featured news, a testimonial on colour | Team-focused | Form beside a dark details panel | Latest album on dark, then the other albums |
| **Editorial** | Centred logo, menu centred below | Light and centred | Story-led: large statement, wide photo, quote, news as a reading list | Story and milestones | Questions first, then the form | Masonry wall, then all albums as a list |
| **Impact** | Coloured top bar with contact details, social links and search, then a Get involved button | Dark, "Stay in touch" row and four columns | Numbers first, approach in four steps, projects, voices, events | Impact report: results, money, reports | Departments, offices on colour, the form | Album carousel, then all albums |
| **Minimal** | Menu button only (drawer on every screen) | One compact row | Community: welcome, events, what people say, news | Partners and history | Just the form | Photo albums and video albums |

The files are in `resources/starter-kit/` (`globals/header-*.json`, `footer-*.json`, `templates/pages/*-<style>.json`); Classic keeps the existing files.

## Choosing a design

| Where | What it shows |
|---|---|
| **New page → Start from** | The list is grouped by area (Home pages, About pages, Contact pages, Gallery pages, Pages). **See the designs** opens a window with each design's picture and description; choosing one fills in "Start from". |
| **Design → Header & footer** | The headers and footers as picture cards (radio buttons, keyboard-friendly) instead of a list. |
| **Builder → Templates tab** | Templates grouped by area, with the picture and "Whole page" or "Section". |

Pictures are in `public/images/starter-kit/<slug>.webp` (36 files, about 350 KB). They were taken from the real designs, rendered with the demo content in a separate copy of the application, so they show what a visitor sees. Designs made on the site show an icon instead.

## Installing

```bash
php artisan pacms:starter                            # adds the new designs; your edits stay
php artisan pacms:starter --pages --style=bold       # draft pages, header and footer in the Bold style
```

- `--style` (classic, bold, editorial, impact, minimal) chooses the design for `--pages`, and the header and footer when none is chosen yet. The default is Classic, as before.
- On an existing site, the shipped Home, About and Contact templates move from "Pages" to their area group. Their design and name stay as they are; `--restore=all` also brings back the shipped names ("Home: Classic"…).

## Small additions to blocks (K-5)

| Addition | Why |
|---|---|
| **Menu: "Menu button only"** style | The Minimal header: the menu opens as a drawer on every screen width. |
| **Gallery: Masonry** display | The Editorial gallery: photos keep their own shape, in columns. |
| **Galleries: "Gallery type" filter** (photos, videos, both) | The Minimal gallery's photo and video sections. The block schema already listed it, but the code did not have it. |
| **Header columns follow their own alignment** | A column set to "Justify: centre" is centred (the Editorial header). |
| **Rows follow the text alignment** | Social links and contact details centre in a centred section (the Editorial footer). |

## Fixed on the way

- **A chosen theme text colour was lost.** Choosing a theme colour such as "Heading text" for a block wrote a CSS variable that referred to itself (`--pa-color-heading: var(--pa-color-heading)`). Browsers drop such a variable, so the text took the colour around it, for example white text on a white footer section. Theme colours are now read from their RGB copy. A test covers it.
- **Links on dark or coloured backgrounds** kept the primary colour, so they were hard to read. They now take the readable text colour, like headings do. The design tokens include RGB values of the readable colours for this.
- **Footers built from blocks had an extra 3rem dark band** at the top and bottom, because a later rule overrode the intended zero padding.
- **Drop-down menus and the search box in a dark header** would have shown white text on their white panels. They now use the theme's own text colours.
- **Header buttons** no longer wrap onto two lines.

## Files

**Created:**
- Kit designs: `resources/starter-kit/globals/{header,footer}-{bold,editorial,impact,minimal}.json`, `templates/pages/{home,about,contact}-{bold,editorial,impact,minimal}.json` and `gallery{,-bold,-editorial,-impact,-minimal}.json`.
- Pictures: `public/images/starter-kit/*.webp`.
- View `components/admin/design-cards.blade.php`.
- Tests `Starter/StarterStylesTest`.
- This report.

**Modified:**
- Starter kit: `kit.php` (styles, style globals, templates), `StarterKitService` (`--style`, `thumbnail()`, area groups), `StarterKitCommand`, classic kit files (names, area groups).
- Blocks: `MenuBlock`, `GalleryBlock`, `GalleryType` (filter), `site.jsx`, `collections.jsx`, `GalleryGrid.jsx`, `style/compile.js`, `DesignTokenService`.
- Admin: `PageController`, `NavigationSettingsController`, `ReusableBlockController`, the page form and Header & footer views, `Palette.jsx`, `app.js`, `admin.scss`.
- `public.scss`.
- Tests: `StarterKitTest`, `TeamPartnersGalleriesTest`, `compile.test.js`, `site.test.jsx`, `GalleryGrid.test.jsx`.
- Docs: README, CMS-BLOCK-SCHEMA, roadmap.

## Tests

Pest **380 passed**, Vitest **102 passed**; Larastan, Pint and ESLint are clean, and the build works.

New tests cover:
- every kit file passing the import checks (all 55, in the kit install test);
- five designs per area, and a picture for each one (unsafe slugs get none);
- `--style=bold` building the Bold pages and choosing the Bold header and footer, and an unknown style being refused;
- existing kit templates moving to their area group with their design and name unchanged;
- the pickers: picture cards in Header & footer, the grouped list and design window on New page, and pictures in the builder's templates list;
- the gallery-type filter;
- the drawer-only menu staying open on wide screens, and the masonry gallery;
- theme text colours not referring to themselves, and links taking the readable colour.

## Not checked

- **The admin pickers in your browser.** The pictures and pages were checked in screenshots, and the pickers by tests. Please open **New page → See the designs** and **Design → Header & footer**.
- **Phone widths.** The designs use the same responsive blocks as before, but each one has not been checked on a phone yet. That is part of 11B's accessibility pass.

## After pulling

```bash
npm run build                      # or keep npm run dev running
php artisan pacms:blocks:sync      # menu and gallery options
php artisan pacms:starter          # installs the new designs (your edits stay)
```

## Next

After review: merge, then **11B**: loading, error and empty states, an accessibility pass (including phone widths for the new designs), performance and OpenAPI. Then tag **v0.11.0**.
