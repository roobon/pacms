# Phase 11W — Full-width blocks · Report

| | |
|---|---|
| Date | 2026-10-17 |
| Branch | `phase/11w-full-width` (from `main` after 11I) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted) |

```
PHASE:   11W — blocks for full-width pages, after leaf.global, and a full-width page design
         built from them.
STATUS:  AWAITING REVIEW. Not merged.
         Decisions (team lead, 2026-10-17): W-1 build all four parts (Split block, image
         filling the width, tiles, templates with pictures); W-2 own branch and report, then
         stop for review. Phase 11B stays next.
```

## What leaf.global does, and what PACMS already had

leaf.global (Squarespace) uses sections with full-bleed backgrounds and **wide** content: rows of text beside a photo, large photos, a news wall, a newsletter form and an Instagram feed.

PACMS could already make a section run edge to edge (**Layout → Width: Full**, or **Fluid** for wide content with a small margin), give it a background photo with an overlay, and set a minimum height. What was missing is below.

## Added

| Addition | Where | What it does |
|---|---|---|
| **Split** (new block) | Content → Split: photo and text | A photo filling half the screen **to its edge**, with headings, text, lists, buttons and more beside it. The text lines up with the site width. Options: photo on the left or right; half, wider (60%) or narrower (40%) photo; as tall as the text, half the screen, or most of it. Phones show the photo first, then the text. Without a photo yet, a quiet panel holds its place. Like Hero, it goes directly on the page. Its **Layout → Padding** is the space above and below the text, never around the photo. |
| **Image → Fill the width** | Image block → Size, Banner height | The image takes the full width of where it is placed, with no corners or margin. In a section whose width is **Full**, it runs edge to edge. A banner height (40, 60 or 80% of the screen) crops it to fit and wins over the crop setting. |
| **Tiles** (display) | Gallery, News, Projects, Programmes, Events, Publications… → Display: Tiles | Photos edge to edge with **no gaps**. News and module items show their title, category and date over the photo on a dark gradient (one link per tile). Items without a photo get a coloured tile. Gallery tiles are square, and their captions stay in the viewer. When there are fewer items than columns, they stretch to fill the row. Galleries can now have 6 columns. |

## Templates (Templates tab → Full width)

| Template | |
|---|---|
| Full width: photo left, text right | A Split with heading, text and a button |
| Full width: text left, photo right | The same, mirrored |
| Full width: banner | Edge to edge, 60% of the screen tall, with a centred sentence. Add a photo under Style → Background → Image (with the overlay). |
| Full width: news tiles | A centred heading, then the five latest news items as tiles in one row |
| Full width: photo tiles | The latest gallery's photos as square tiles, six in a row |
| **Home: Full width** (page, Home pages) | After leaf.global: a centred title, photo-and-text rows that alternate sides, the banner, a resource row with a checklist, a publication row, news tiles, a contact button, photo tiles and the call to action |
| **Page: Full width** (page, Pages) | For any other page (a programme, campaign or landing page): a title banner, "What we do" and "How it works" rows, a numbers band, a quote beside a photo, photo tiles, news tiles and the call to action |

The banners are **Hero** blocks, so their text stays centred in their height. For a page made from either design, set the page's **Layout** to **Full width** as well.

Every template has a picture for the design pickers, taken from the real design with the demo content at 1280 × 800. As decided for the kit before (K-3), no photos are shipped: the photo halves show the quiet panel until a photo is chosen.

## Not done

- **Newsletter sign-up** (leaf.global's footer form): PACMS has no newsletter list yet. That would be a feature of its own (storage, consent, export or a provider).
- **Instagram feed**: needs a Meta app, as decided in 11I (I-3). The Facebook posts block covers the latest social posts today.

## Checked in a browser

The page was rendered at **1280 × 800** and **390 × 844** (phone) with the demo content, in a throwaway copy of the database:
- The Split text lines up with the logo at the site width; the photo half reaches the screen edge; photo-and-text rows alternate.
- On a phone, each Split stacks photo first, then text; tiles go two in a row.
- News tiles fill one row of five; three gallery photos stretch to fill the row of six.

## Files

**Created:** `Cms/Blocks/Types/SplitBlock.php`; kit files `templates/sections/full-{split-left,split-right,banner,news-tiles,photo-tiles}.json` and `templates/pages/{home-full-width,page-full-width}.json` with their pictures; tests `Blocks/FullWidthBlocksTest`, `blocks/components/fullwidth.test.jsx`; this report.

**Modified:** `ImageBlock`, `GalleryBlock`, `NewsBlock`, `ContentCollectionBlock`, `DisplayModeRegistry`, `config/pacms.php`, `content.jsx`, `basic.jsx`, `collections.jsx`, `GalleryGrid.jsx`, `ItemCard.jsx` (`ItemTile`), `ItemsDisplay.jsx`, `registry.js`, `style/compile.js` (a split's padding), `StructurePanel.jsx` (a split saves as a section template), `public.scss`, `kit.php`, starter kit tests (counts), CMS-BLOCK-SCHEMA, README, roadmap.

## Tests

New tests cover:
- the Split block: its options accepted and wrong values refused; page level only; the photo side, width and height classes; the quiet panel without a photo; its padding going around the text only;
- an image filling the width at a banner height (which wins over the crop), and wrong sizes or heights refused;
- tiles accepted for news, projects and galleries (6 columns), refused for cards; tiles showing the title over the photo with one link each, a coloured tile without a photo, gallery tiles without captions underneath.

Pest **388 passed** (full run), and the starter kit tests again after "Page: Full width" was added; Vitest **113 passed**; Larastan, Pint and ESLint are clean, and the build works.

## After pulling

```bash
npm run build                       # or keep npm run dev running
php artisan pacms:blocks:sync       # the Split block and the new options
php artisan pacms:starter           # adds the Full width templates and Home: Full width
```
