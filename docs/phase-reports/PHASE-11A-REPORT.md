# Phase 11A — Public website: search, contact form, account area · Report

| | |
|---|---|
| Date | 2026-10-12 |
| Branch | `phase/11-public-spa` (from `main` at v0.10.0) |
| Prepared for | Syed Ziaul Habib, Hasibul Hasan, Khandoker Humayoun Kobir |
| Prepared with | Claude Code (AI-assisted). Awaiting review by the team lead. |

```
PHASE:   11A — the parts of the public website visitors use: site search (page, header
         block, 404 box), a spam-protected contact form block with an admin inbox, and an
         account area where registered visitors manage their details or delete their account.
STATUS:  READY FOR REVIEW. 11B (loading/error/empty states, accessibility pass, performance,
         OpenAPI) follows; v0.11.0 is tagged after 11B.
         Decisions (team lead, 2026-10-12, "use your defaults"): P-1 messages e-mailed and kept
         90 days, inbox for administrators and editors; P-2 honeypot + minimum time + rate
         limits, no captcha; P-3 search icon in the header; P-4 11A then 11B; P-5 end-to-end
         browser tests in Phase 12.
```

## Implemented

| Area | What exists now |
|---|---|
| **Search page** | `/search?q=…&type=…&page=…` (not indexed). The address holds the search, so results can be shared and Back works. Filter chips per content type, results with type and date, the search words marked in the excerpt (as text, no HTML), pages of results, a loading skeleton, an error with **Try again**, and suggestions when nothing is found. Screen readers hear the result count. |
| **Search block** | New site block **Search** (Header & footer): an icon that opens a search box (Esc, a click elsewhere or leaving it closes it) or an always-visible box. The starter kit's header now has it between the menu and the account link. The 404 page also has a search box. |
| **Contact form block** | New content block **Contact form**: heading, recipient e-mail, subject on/off, consent text (empty = no checkbox), button label, thank-you text. The **recipient never reaches the browser** (new `privateFields()` on block types keeps it out of the public payload). Field errors appear next to the fields; the thank-you message gets focus. The starter kit's Contact page now includes it. |
| **Spam protection** | No captcha, no cookies: a hidden field people leave empty and robots fill in, a minimum of 3 seconds to fill the form (both answer robots with a normal-looking reply and keep nothing), 5 messages a minute and 20 a day per connection. |
| **Delivery** | Each message is saved and e-mailed (queued) to the block's recipient, or the site contact e-mail; **Reply** in the mail program answers the visitor. If the mail fails, the message is still in the admin. |
| **Messages inbox** | Admin → **Messages** (permission `messages.manage`: administrators and editors), with an unread count in the menu: list (all / unread), read, mark unread, delete. Messages are deleted automatically after **90 days**. |
| **Account area** | Registered visitors can change their **name and e-mail** (a new address must be verified again) and their **password**, see their testimonials as before, and **delete their account** with their password. Unpublished testimonials are deleted with the account; published ones stay without the link to it. Staff accounts are deleted by an administrator, not here. |

## Fixed on the way

- **Account deletion brought the user back**: signing out after deleting saved a new "remember me" token and so re-created the account. Now the session is cleared without saving the user.
- The activity log entry for a deleted account is written before the account goes (otherwise the database refused it).

## Files

**Created:** `Cms/Blocks/Types/{SearchBlock, ContactFormBlock}`; `Api/V1/{ContactController, AccountController}`; `Admin/MessageController`; `Models/ContactMessage`; `Mail/ContactMessageMail`; migration `contact_messages`; views `admin/messages/*`, `mail/contact-message`; `public/pages/SearchPage.jsx`, `public/pages/account/AccountSettings.jsx`, `public/utils/highlight.jsx`, `blocks/components/forms.jsx`; tests `Public/ContactFormTest`, `Auth/AccountDeletionTest`, `blocks/components/forms.test.jsx`; this report.

**Modified:** `BlockType` and `BlockPayloadResolver` (private fields), `PermissionCatalog`, `AdminNavigation`, `SpaController`, `config/pacms.php`, `routes/{admin,api,console}.php`, `site.jsx`, `registry.js`, `AppRoutes.jsx`, `NotFoundPage.jsx`, `AccountPage.jsx`, `public.scss`, starter kit (`main-header`, `contact`), `StarterKitTest`, docs (CMS-BLOCK-SCHEMA, DATABASE-ARCHITECTURE, API-ARCHITECTURE).

## Tests

Pest **375 passed**, Vitest **100 passed**, Larastan, Pint and ESLint clean, build OK. New: message kept and mailed to the block's recipient with reply-to; the recipient absent from the public payload; validation and consent; robots (honeypot, too fast) get 202 and nothing is kept; rate limit; fallback to the site e-mail; inbox permissions, read/unread/delete; 90-day pruning; account deletion (wrong password, testimonials, tokens, activity log, sign-out), staff and guests refused; `/search` served without indexing; the kit's Contact template has a contact form; front end: contact form request and errors, search block open/Esc/focus, highlighting without HTML.

## Not checked

- **In a browser**: the search page, header search, contact form and account forms (covered by tests; please try them with `npm run dev`).
- **Real e-mail delivery**: with `MAIL_MAILER=log` the message appears in `storage/logs/laravel.log`; a queue worker must run (`composer dev` starts one).

## After pulling

```bash
php artisan migrate              # contact_messages; gives editors the Messages permission
php artisan pacms:blocks:sync    # Search and Contact form blocks
php artisan pacms:starter --restore=all   # optional: header with search, Contact page with the form
```

## Next

After review: **11B** — loading, error and empty states across the website, an accessibility pass, performance (prefetching, code-splitting review) and OpenAPI for the public API; then tag **v0.11.0**.
