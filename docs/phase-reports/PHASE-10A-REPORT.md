# Phase 10A — External providers: feeds · Report

| | |
|---|---|
| Date | 2026-10-09 |
| Branch | `phase/10-external` → merged into `main` 2026-10-09 |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Merged at the team lead's request (2026-10-09: "merge and commit after finishing"); not browser-checked yet. |

```
PHASE:   10A — provider framework, reading other sites' feeds (JSON Feed first, RSS/Atom
         fallback, YouTube via Atom), Feed block, Design → External sources, PACMS's own
         JSON Feed (/feed.json, /feed/{type}.json)
STATUS:  MERGED into main (team lead's request). Review in use; fixes follow as before.
         Decisions (team lead, 2026-10-09): option 1 (JSON Feed + RSS/Atom for reading),
         E-1 10A then 10B, E-2 Facebook against simulated responses, E-3 YouTube through
         feeds, E-4 SimplePie, E-5 external images shown from their address.
NEXT:    the richer text editor (decisions R-1…R-4 agreed), then 10B (Facebook).
```

## Implemented

| Area | What exists now |
|---|---|
| **Design → External sources** | Add a feed by its address, **Test the address** (reads without saving), name, how often to read it (15 minutes to once a day), items to keep (up to 200), enable/disable. Each source shows its status, last success, last error, next sync, the newest stored items and the recent syncs. **Sync now** (editors too; at most 6 times an hour per source), **Clear items**, **Delete**. Adding a source syncs it at once. |
| **Reading feeds** | JSON Feed 1.x first; RSS 2.0 and Atom as the fallback, including YouTube channel and playlist feeds (thumbnails and descriptions). Titles and summaries become plain text, content keeps the rich text allowlist only, links must be http(s) (relative links made absolute), dates far in the future are treated as "now". |
| **Safety** | Downloads go through the SSRF-guarded client (public addresses only, redirects re-checked, 5 MB, 15 s). XML that declares a DOCTYPE or entities is refused before any parser sees it. SimplePie only reads text PACMS downloaded and checked; it never fetches. |
| **Syncing** | Every minute the scheduler queues the sources that are due; one sync per source at a time. On failure the stored items keep showing and the next try waits twice as long each time (at most a day). Every attempt is logged (kept 90 days). |
| **Feed block** | Under **External** in "Add block": choose a source and how many items; list, grid, carousel or featured; summary, date and source name on or off. Links open in a new tab. Pages refresh by themselves after each sync. A disabled source shows nothing. |
| **JSON Feed out (D-15)** | `/feed.json` (news, events, projects, programs, publications) and `/feed/{type}.json` for every module with a listing, optional `?category=`. Published items only, at most 50, absolute addresses, `application/feed+json`, cached; pages announce it to feed readers. |
| **Demo** | "Partner news (demo)" with three stored items, shown on Our programmes. |

## Files

**Created:** migration `2026_10_17_100000_external_providers`; models `ExternalSource`, `ExternalItem`, `ExternalSyncLog`; `Cms/External/{ExternalContentProvider, ProviderRegistry, FeedParser, FeedException, Providers/FeedProvider}`; `Cms/Sources/ExternalItemsSource`; `Services/External/ExternalSyncService`; `Services/Feeds/JsonFeedBuilder`; job `SyncExternalSource`; command `pacms:external:sync`; controllers `Admin/ExternalSourceController`, `Public/JsonFeedController`; block `FeedBlock`; views `admin/external-sources/{index,form}`; tests `External/ExternalSourcesTest` and feed fixtures.

**Modified:** `SafeHttpClient` (`fetch()`), `BlockType` (`externalProviders()`), `SourceRegistry`, `BlockBuilderController`, `SourcePanel.jsx`, `collections.jsx`, `registry.js`, payload cache keys (`external`), `AppServiceProvider`, `routes/{admin,web,console}.php`, `spa.blade.php`, `config/pacms.php` (reserved `feed`), `AdminNavigation`, `DemoSeeder` (+ test), `ContentSourcesTest`; dependency `simplepie/simplepie`.

## Database changes

New tables `external_provider_accounts` (used by 10B), `external_sources`, `external_items`, `external_sync_logs`.

## Tests

Pest **351 passed**, Vitest 88 passed, Larastan, Pint and ESLint clean, build OK.

New: JSON Feed/RSS/Atom reading and cleaning; DOCTYPE/entity attacks and non-feeds refused; storing, updating and trimming; stale-while-error and backoff; internal addresses never read; due sources queued; permissions (administrators manage, editors sync, 6 syncs an hour); Feed block with refresh, disabled and unknown sources; JSON Feed out (published only, categories, discovery link).

## Not done yet

- **Not checked in a browser** (merged at the team lead's request before that step): the External sources screens and the source picker in the block builder.
- **Real-world feeds**: tested with fixtures; try a real feed (e.g. a YouTube channel) under Design → External sources.
