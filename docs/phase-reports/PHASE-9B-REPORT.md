# Phase 9B — Navigation: mega panels, ready-made header and footer · Report

| | |
|---|---|
| Date | 2026-10-09 |
| Branch | `phase/9-navigation` → merged into `main` 2026-10-09, tagged `v0.9.0` |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Approved 2026-10-09. |

```
PHASE:   9B — mega panels; the starter kit's Main header and Main footer; menus filled for
         existing sites. Completes Phase 9 (Navigation).
STATUS:  APPROVED by the team lead (2026-10-09), merged into main and tagged v0.9.0.
         Scope agreed with Phase 9 (N-1, N-4; team lead, 2026-10-09).
```

## Implemented

| Area | What exists now |
|---|---|
| **Ready-made header and footer** | `php artisan pacms:starter` adds **Main header** (site logo · main menu · account link) and **Main footer** (logo for dark backgrounds, a sentence about you, social links · footer menu · contact details · copyright strip). They show your logo, menus, social profiles, contact details and copyright line from the settings, so nothing needs editing to start. Both are normal global blocks: edit them under Design → Global blocks. |
| **Chosen automatically** | When no header or footer is chosen yet, the kit's become the site's. A header or footer you already chose is never replaced. |
| **Menus for existing sites** | The kit creates the menus **main** and **footer** when missing and, when they are empty, fills them with the home page and the published top-level pages (up to 7). Menus that have items are left alone. |
| **Mega panels** | In the Menu Builder, a saved top-level item gets **Add a mega panel / Edit the panel**. The panel is built with the normal block builder (columns, headings, text, buttons, images, collections…) and opens under the header across its full width instead of the sub-items list. Saved live, like menus. **Remove the panel** brings the sub-items list back. Items with a panel show a "Mega panel" badge. |
| **On phones** | A mega item lists its sub-items in the drawer; if it has none, the panel itself is shown there. |
| **Rules** | Only top-level items have panels. Moving an item under another one removes its panel; deleting an item deletes its panel. Panels are validated like global blocks, and their images are tracked in "where is this used". |
| **Demo** | The demo uses the kit's header and footer; "Programmes" opens a mega panel (a list of programmes, a short text and a button). |

## For your local site

Run once:

```bash
php artisan pacms:starter
```

It adds the Main header and Main footer and, because your header and footer are set to "None" now, makes them the site's. Your logo and Facebook link then appear at once. Your existing "Main" menu keeps its items; a "Footer" menu is created with your published top-level pages. Your own "Header" global block stays as it is (choose between them under Design → Header & footer).

## Files

**Created:** `Admin/MenuPanelController`, view `admin/menus/panel`, starter kit `globals/main-header.json` and `globals/main-footer.json`, this report.

**Modified:** `MenuService` (`prefill()`, `savePanel()`, `removePanel()`, `labelOf()`, panels in the resolved menu, panels kept or removed on save), `StarterKitService` (menus first, header and footer chosen), `resources/starter-kit/kit.php`, `routes/admin.php`, `MenuBuilder.jsx`, `site.jsx` and `visitor.js` (panels on desktop and in the drawer), `public.scss`, `DemoSeeder` (+ test), tests.

## Database changes

None (`menu_items.is_mega` and block trees owned by menu items were prepared in 9A).

## Tests

| Check | Result |
|---|---|
| Pest (full suite) | **342 passed** (1,981 assertions) |
| Vitest | **88 passed** (16 files) |
| Larastan (level 6) | No errors |
| Pint, ESLint | Clean |
| Production build (Vite) | OK |

New tests:
- **Starter kit:** menus filled with the home page first and published top-level pages only (drafts left out); the kit's header and footer chosen and rendered with logo, menu and account link; a second run keeps menus with items and a header you chose.
- **Mega panels:** panel screen only for top-level items and only with "Manage menus"; saved panel in the resolved menu; kept when the menu is saved; removed (with its blocks) when the item moves under another; "Remove the panel".
- **Website (Vitest):** the panel opens from its button; on phones, a panel-only item shows the panel in the drawer.
- **Demo:** Programmes has a panel.

## Checked in the browser

On a separate copy with the demo (port 8765, test database; your data untouched, the demo's image files removed afterwards, your 34 media files checked): the kit's header, the Programmes mega panel (made more compact after the first look: smaller headings in panels, a list instead of cards in the demo), and the kit's footer.

## Known issues / deferred

- **Newsletter** block: still deferred (no newsletter service).
- The footer's sentence ("Say in one sentence what your organisation does…") is placeholder text to replace in the Main footer.

## Next

Tagged **v0.9.0**. Next: **Phase 10 — External Providers** (JSON Feed, RSS/Atom reading, Facebook).
