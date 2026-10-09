# Phase 9A — Navigation: menus, header and footer · Report

| | |
|---|---|
| Date | 2026-10-09 |
| Branch | `phase/9-navigation` (not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Awaiting review. |

```
PHASE:   9A — menus and the Menu Builder, header and footer as global blocks (with site
         logo, menu, social links, contact info, copyright and account blocks),
         Design → Header & footer, a page's own header/footer, mobile drawer, menus API
STATUS:  READY FOR REVIEW. Not merged.
         Decisions applied (team lead, 2026-10-09: "use your defaults"):
         - N-1 two parts: 9A (this), 9B (mega panels, starter-kit header/footer, pre-filled menus)
         - N-2 no newsletter block (no newsletter service); a button to a page instead
         - N-3 members-only menu items: see "One change from the plan" below
         - N-4 the demo has a full main menu, a footer menu, a header and a footer
```

## Implemented

| Area | What exists now |
|---|---|
| **Design → Menus** | Permission "Manage menus" (administrators and editors). Create, rename and delete menus. Each has a fixed key (`main`, `footer`…) that Menu blocks use. |
| **Menu Builder** | An indented list of items, up to 4 levels. Drag a row, or use the buttons: ↑ ↓ move among siblings, → places an item under the one above, ← moves it out (all reachable by keyboard). Click an item to edit it. "Save menu" stores everything at once; if someone else saved in the meantime, you are told to reload. Unsaved changes ask before you leave. |
| **Menu items** | Link to a **page**, a **content item** (any module, including your own types), a **category** (its listing page, filtered), a **web address** (https, mailto, tel), an **address on this site** (`/donate`, `#contact`), or a **heading** without a link. Options: label (empty = the target's current title), open in a new tab, icon, CSS class, shown to everyone / visitors not signed in / signed-in members. |
| **Links never break** | Pages and items are stored by id; their address is looked up when the menu is shown, so renaming a page's URL keeps the menu working. Unpublished or deleted targets are hidden on the website with their sub-items, and flagged "Not published" in the builder. |
| **Header and footer** | Global blocks of the new kinds **Header** and **Footer**, built in the normal block builder. New blocks in the **Site** group: **Site logo**, **Menu** (horizontal or vertical), **Social links**, **Contact info**, **Copyright** (`{{year}}` and `{{site_name}}` only), **Account link** (Sign in / My account). |
| **Design → Header & footer** | The site's header and footer, "keep the header at the top", "see-through header over a hero or slider", the logo and a logo for dark backgrounds, social profiles (Facebook, YouTube, LinkedIn, Instagram, X; https only) and the copyright line. Without a header or footer chosen, the website keeps its plain header and footer. |
| **Per page** | Page settings → Header and Footer: *Site default*, *None* (landing pages), or *Choose one*. Part of the page's draft, so it changes when the page is published. |
| **On the website** | Desktop: sub-menus open with buttons (never on hover only), Esc closes and returns focus, clicking elsewhere closes, the current page is marked. Below desktop width the menu becomes a **Menu** button opening a drawer with accordion sub-levels; focus stays inside, Esc closes. Vertical menus (footers) list every level. |
| **API** | `GET /api/v1/menus/{slug}` (cached 5 minutes); `/api/v1/site` includes the resolved header and footer. |

## One change from the plan (N-3)

I planned two cached versions of each menu on the server (visitors / members). While building it I found that menus also sit inside cached page content, which would have needed a guest and a member copy of every page. Instead, the server sends **one** menu with each item's "shown to" setting, and the website hides the items that do not apply. Visitors see exactly the same; the only difference is that the address of a members-only link is in the page data, which is fine because, as agreed, these are links, not protected content. Protected pages are still protected by their own permissions.

## Fixes found on the way

- **Restoring a page revision could fail** (a new column was empty in snapshots of new pages). Pages now start with "Site default", and older revisions restore with the default.
- **Menus did not refresh when the home page changed** (cache key). Fixed and tested.
- **Phones scrolled sideways** on pages with a list of cards (e.g. Programmes on the home page): the list grew with long titles. Fixed for every list.
- In the Menu Builder, saving copied each page's current title into the item's label (renaming the page later would not have changed the menu). Fixed and tested.

## Files

**Created:** migration `2026_10_16_100000_navigation`; models `Menu`, `MenuItem`; `Services/Navigation/MenuService`; controllers `Admin/MenuController`, `Admin/NavigationSettingsController`, `Api/V1/MenuController`; blocks `SiteBlock`, `SiteLogoBlock`, `MenuBlock`, `SocialLinksBlock`, `ContactInfoBlock`, `CopyrightBlock`, `AccountLinkBlock`; views `admin/menus/{index,edit}`, `admin/settings/navigation`; island `menu-builder.jsx` with `menu-builder/{MenuBuilder.jsx,tree.js}`; `blocks/components/site.jsx`, `blocks/common/visitor.js`, `public/contexts/ChromeContext.jsx`; tests `Navigation/MenusTest`, `menu-builder/tree.test.js`, `components/site.test.jsx`; this report.

**Modified:** `BlockType` (`data()`), `BlockPayloadResolver`, `GlobalBlock` (kinds), `Page` (header/footer fields), `PageRequest`, `PageController`, `admin/pages/form`, `PagePayloadBuilder` (`chrome`), `SitePayload` (header, footer, cached), `SettingsService` (`navigation`), `AdminNavigation` (Design → Menus, Header & footer), `AppServiceProvider` (morph aliases), `config/pacms.php`, `routes/admin.php`, `routes/api.php`, `vite.config.js`, `SiteShell.jsx`, `PageView.jsx`, `BlockRenderer` registry, `admin.scss`, `public.scss`, `app.js`, `DemoSeeder` (+ test), `DATABASE-ARCHITECTURE.md`, `CMS-ARCHITECTURE.md`, `DEVELOPMENT-ROADMAP.md`.

## Database changes

- New tables **menus**, **menu_items**.
- **pages**: `header_mode`, `header_global_block_id`, `footer_mode`, `footer_global_block_id`.
- Settings group `navigation` (no table change).

## Tests

| Check | Result |
|---|---|
| Pest (full suite) | **337 passed** (1,945 assertions) |
| Vitest | **85 passed** (16 files) |
| Larastan (level 6) | No errors |
| Pint, ESLint | Clean |
| Production build (Vite) | OK |

New tests:
- **Menus:** permission; nested menu of every item type with resolved addresses, icons, classes, visibility; unsafe addresses (`javascript:`, `//host`), missing labels, bad classes, unknown targets and a fifth level refused; edit conflict; unpublished targets hidden with sub-items and flagged in the builder; a moved page followed; the home page as `/`; stable item ids; the public API.
- **Header and footer:** chosen in settings and resolved with the logo, menu, copyright and social links; a saved menu reaches the header at once; pages with none, with another header, or with the default; validation of the page choice and of the settings.
- **Menu Builder (Vitest):** flatten/rebuild, move up/down with sub-items, indent/outdent, maximum depth, drag positions, remove, and the saved label.
- **Website menu (Vitest):** guest/member items, sub-menu buttons with Esc and focus return, current page, safe new tabs, the drawer (focus, accordion, Esc), vertical menus, and the site blocks.

## Checked in the browser

On a separate copy of the site (port 8765, test database filled with the demo, so your local data was not touched; the demo's image files were removed afterwards and your 33 media files checked):
- desktop header with an open sub-menu, the current page marked; the footer with logo, text, social icons, footer menu, contact details and copyright;
- phone width: header with the **Menu** button and Sign in, the drawer with accordion sub-levels;
- the Menu Builder: nested items, the item editor, reordering and **Save menu** (stored correctly).

Note for your machine: your Vite dev server (`npm run dev`) once kept an empty copy of `public.scss` after quick edits, so the site showed without styles until the file was saved again. If that ever happens, save any `.scss` file or restart `npm run dev`.

## Known issues / deferred to 9B

- **Mega panels** (a top-level item opening a panel built from blocks).
- **Starter-kit header and footer**, and pre-filled main and footer menus for existing sites (`pacms:starter`).
- Your local site has no header, footer or menus yet: create them under Design (or wait for 9B's `pacms:starter`).
- **Newsletter** block: deferred until a newsletter service is chosen.

## Next

**9B:** mega panels, starter-kit header and footer, pre-filled menus. Then tag v0.9.0.
