# Phase 10 (interlude) — Richer text editor and AI-JSON help · Report

| | |
|---|---|
| Date | 2026-10-10 |
| Branch | `phase/10-external` (not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Awaiting review. |

```
PART:    richer text editor (asked by the team lead during 10A), and help for AI-made JSON
STATUS:  READY FOR REVIEW. Not merged.
         Decisions (team lead, 2026-10-09: "use your defaults"): R-1 theme colours only,
         R-2 images from the Media Library only, R-3 no video embeds in text, R-4 after 10A.
```

## Implemented

| Area | What exists now |
|---|---|
| **One editor everywhere** | Article text, Text blocks and rich-text fields of your own content types share it (the short Summary editor stays simple). |
| **Toolbar** | Undo/redo · Paragraph / Heading 2–4 · bold, italic, underline, strikethrough, superscript, subscript, inline code · **text colour** and **highlight** (theme colours) · **align** left / centre / right · bullet and numbered lists, quote, horizontal line, **highlight boxes** (Info, Success, Warning, Note) · **link** (address, or search a page or item by title; new tab) · **image** from the Media Library (or upload) · **table** · clear formatting · **full screen** (Esc to leave) · word count. |
| **Images** | A selected image shows its panel: size and position (full width, wide, left or right with text wrapping), alt text (taken from the library), caption. The website shows the 1280px version. |
| **Tables** | Insert with a header row; when inside a table: add or remove rows and columns, header row, merge or split cells, delete. On the website: striped, readable, scrolling sideways on phones. |
| **Pasting** | Formatting the editor does not know (fonts, colours, styles from Word or Google Docs) is dropped automatically. |
| **Safety** | Everything is stored as a fixed list of class names, never inline styles; the server keeps only those classes, and images only from this site's library (others are removed with their captions). |
| **AI-made JSON** | A plain `"url"` (or `"href"`) on a block with one link field, e.g. a button from ChatGPT, is now read as its link (noted in the report) instead of failing the import. |

## Fixes on the way

- While adding the editor's settings to the admin layout, every admin page broke for a few minutes on your machine (a Blade syntax mix-up). Found by the tests before committing and fixed; all admin tests pass.

## Files

**Created:** `resources/js/admin/builder/fields/rte/extensions.js` (+ test), `resources/scss/_rich-content.scss`, `tests/Feature/Content/RichTextTest.php`, this report.

**Modified:** `RichTextInput.jsx`, `HtmlSanitizer` (classes, images), `PortableTranslator` (url shorthand), `Admin/MediaResource` (`large`), admin layout (endpoints for the editor), `admin.scss`, `public.scss`, `package.json` (TipTap table, subscript, superscript).

## Tests

Pest **355 passed**, Vitest **93 passed**, Larastan, Pint and ESLint clean, build OK. New: allowed classes, figures, tables and boxes kept; unknown classes, styles, event handlers and outside images removed; article text saved with them; editor output as classes (colours, highlights, alignment, boxes, figures, tables) and read back unchanged; word count; the `"url"` shorthand.

## Checked in the browser

The news edit page: the new toolbar and the table panel (nothing saved).

## Next

After review: merge, then **10B (Facebook)**.
