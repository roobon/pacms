# Phase 10B — External providers: Facebook Page · Report

| | |
|---|---|
| Date | 2026-10-12 |
| Branch | `phase/10-external` (not merged) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Awaiting review. |

```
PHASE:   10B — posts of the organisation's own Facebook Page: connect (OAuth or pasted Page
         token), encrypted token, published posts through the Graph API, images copied into
         the Media Library, "Facebook posts" block. Completes Phase 10.
STATUS:  READY FOR REVIEW. Not merged.
         E-2 (team lead, 2026-10-09): built and tested against simulated Facebook responses.
         A real connection needs your Meta app or a Page token (Q-05, see below).
```

## Implemented

| Area | What exists now |
|---|---|
| **Connect a Facebook Page** | Design → External sources → **Connect a Facebook Page** (permission "Connect Facebook"; administrators). **1. Connect with Facebook** (when `META_APP_ID`/`META_APP_SECRET` are set): Facebook sign-in → long-lived token → choose the Page. **2. Paste a Page access token** (e.g. a Business Manager system-user token, which does not expire) with the Page ID. Either way the token is checked against the Page first. |
| **The token** | Stored **encrypted**, never shown again, never in error messages or logs. If the Meta app can tell when it expires, admins are warned on the sources list a week before. Deleting the source removes the token. |
| **Reading posts** | Graph API `/{page}/published_posts` (fields `id, message, created_time, permalink_url, full_picture, is_published`), published posts only, about once an hour, through the SSRF-guarded client. Text is escaped (line breaks kept); only facebook.com links are kept. If the token stops working, the stored posts keep showing and the source shows "Reconnect the Page". |
| **Images** | Facebook's image addresses expire, so each post image is copied into the Media Library (category **Facebook cache**, credited to the Page) and blocks show the responsive local copy. Copies are deleted when their posts leave the list or the items are cleared (unless an editor used one elsewhere). |
| **"Facebook posts" block** | Under **External**: heading, post text and date on or off, grid / list / carousel. With no posts it shows a **Follow us on Facebook** link (the Facebook address from Header & footer, or one you enter) or hides itself. No Facebook scripts, iframes or cookies for visitors. |

## Before going live (Q-05)

- Use the organisation's **own Page**. Pages you do not manage need Facebook's "Page Public Content Access" review and are not supported.
- **Option 2** (recommended): in Meta Business Manager, create a system user with access to the Page, generate a token with `pages_read_engagement` and `pages_read_user_content`, and paste it with the Page ID.
- **Option 1**: create a Meta app, set `META_APP_ID`, `META_APP_SECRET` (and `META_GRAPH_VERSION`, default `v26.0`) in `.env`, and add the callback address shown on the Connect page as a valid OAuth redirect. Whether the app needs App Review depends on who uses it; check Meta's documentation for your setup.
- **Re-check the Graph API version** (`v26.0`, from the architecture review of 2026-09-24) against Meta's current documentation.

## Files

**Created:** `Models/ExternalProviderAccount`; `Cms/External/{CachesImages, Facebook/GraphClient, Facebook/FacebookImageCache, Providers/FacebookProvider}`; `Services/External/FacebookConnectService`; `Admin/FacebookController`; view `admin/external-sources/facebook`; block `FacebookFeedBlock`; tests `External/FacebookTest`; this report.

**Modified:** `ProviderRegistry`, `ExternalSyncService` (image cache hook, cleanup), `ExternalItemsSource` (local images), `ExternalItem`, `ExternalSource` (account), `ExternalSourceController` (Facebook sources keep their settings; token removed on delete), source views, `collections.jsx`, `registry.js`, `config/services.php`, `config/pacms.php`, `routes/admin.php`, `.env.example`.

## Tests

Pest **361 passed**, Vitest 93 passed, Larastan, Pint and ESLint clean, build OK. New (simulated Graph API): pasted-token connect with encryption checked in the database, published posts only, escaping and link checks, cached image in "Facebook cache"; refused token stores nothing and never echoes it; stale-while-error with "Reconnect the Page"; OAuth start, forged answer refused, Page choice; the block with posts and with the follow link; cached images removed on clear; expiry warning; permissions.

## Not checked

- **In a browser:** the Connect page and the block (covered by tests; the screenshot helper was unavailable).
- **Against the real Facebook**: needs your Page token or Meta app (above).

## Next

After review: merge and tag **v0.10.0** (Phase 10 complete), then **Phase 11 — Public React SPA**.
