# Phase 11I — Blocks for the Institutional look · Report

| | |
|---|---|
| Date | 2026-10-13 |
| Branch | `starter/institutional-blocks` (from `main` after the Institutional style) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Awaiting review by the team lead. |

```
PHASE:   11I — the blocks and options still missing to build a page like fee.global, and
         the Institutional designs updated to use them.
STATUS:  READY FOR REVIEW.
         Decisions (team lead, 2026-10-13, "use your defaults"): I-1 build the five small
         additions; I-2 use them in the Institutional designs; I-3 no Instagram now (the
         Facebook posts block covers "latest on socials"); I-4 no cookie banner (PACMS sets
         no tracking cookies); I-5 own branch and report, before 11B.
```

## Added

| Addition | Where | What it does |
|---|---|---|
| **Image shape** | Image block → Shape | Square corners, rounded corners, or a **circle** (always a square crop), like the round photos on fee.global. |
| **Logo row** (new block) | Content → Logo row | Logos you choose, each with a name and an optional link (to a page here or elsewhere), for programmes, members or campaigns. Names can show under the logos. Until a logo is added, the name shows in a tile the size of a logo. Unlike the Partners block, it does not read partner records. |
| **Letter spacing** | Style → Typography → Letter spacing | Normal, wide, wider, widest, for spaced capitals in titles, buttons and menus. Named steps only, so pages stay consistent. |
| **Compact grids** | Any collection with 5 or 6 columns | The minimum item width drops from 15rem to 10rem, so 5 or 6 columns really fit (before, 5 became 4). |
| **Stacked site logo** | Site logo block → Layout | The site name under the logo, as on fee.global. |

## The Institutional designs now use them

- Section titles, buttons and the header menu are in spaced capitals.
- The header logo is stacked.
- **Home:** the programmes appear as a **logo row** (name tiles until you add your programme logos), then a list of programmes with their descriptions, as on fee.global. News is in **5 columns**.
- The pictures in the design pickers are updated.

## Not done (decisions I-3, I-4)

- **"Latest on socials" with Instagram posts** needs an Instagram provider and a Meta app, like Facebook. It is a later task; the Facebook posts block works today.
- **A cookie banner** is not needed: PACMS sets no tracking or advertising cookies (only the session and security cookies a sign-in needs).

## Files

**Created:** `Cms/Blocks/Types/LogoRowBlock.php`; tests `Blocks/InstitutionalBlocksTest`, `blocks/components/institutional.test.jsx`; this report.

**Modified:** `ImageBlock`, `SiteLogoBlock`, `StyleValidator`, `config/pacms.php`, `basic.jsx`, `content.jsx`, `site.jsx`, `ItemsDisplay.jsx`, `registry.js`, `style/compile.js`, `style/values.js`, `StylePanel.jsx`, `public.scss`, the Institutional kit files and their pictures, CMS-BLOCK-SCHEMA, roadmap.

## Tests

Pest **384 passed**, Vitest **107 passed**; Larastan, Pint and ESLint are clean, and the build works.

New tests cover:
- the new options being accepted, and values outside their lists refused (shape, letter spacing, logo layout);
- the Logo row: names required, an optional logo, and links reaching the page;
- a circle image being cropped square;
- name tiles shown until logos are added;
- letter spacing compiled from named steps only;
- compact items for 5 or 6 columns.

## After pulling

```bash
npm run build                       # or keep npm run dev running
php artisan pacms:blocks:sync       # the Logo row block
php artisan pacms:starter --restore=all   # optional: the updated Institutional templates
```

Your installed Institutional header and footer are not changed by the kit. To get the spaced capitals and the stacked logo there, edit them under Design → Global blocks, or I can update them for you.
