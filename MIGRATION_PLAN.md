# Migration plan

## 1. Decision: independent rebuild, not an upgrade, not a code import

Two routes were on the table per the BUILD PROMPT: incrementally upgrade the legacy Laravel 5.5 app, or rebuild independently and migrate data in. **Neither "upgrade" nor "import legacy code" was viable**, for a reason that sits above the technical comparison: the legacy repository's root `composer.json` declares `"license": "proprietary"` (author Shane Cunningham) and **no `LICENSE` file exists anywhere in the repository** granting reuse rights. The nested `support/composer.json`'s `"license": "MIT"` is Laravel's own stock skeleton manifest, not a statement about the application code layered on top of it. Per the prompt's own instruction ("If licence terms prevent reuse, implement independently from this functional specification and document the constraint"), that is exactly what happened: this pilot contains **zero lines copied or adapted from the legacy repository**. The legacy repo was cloned into a scratch directory for read-only structural inspection only (route names, migration field names, seeder behaviour, upload handling) - never merged, vendored, or referenced by path from this repository.

This also settles the upgrade-vs-rebuild question by elimination: an "incremental upgrade" would mean modifying the legacy code in place, which requires the same reuse rights that don't exist. Rebuilding independently was therefore the only lower-risk *and only legally available* route, not merely the lower-risk one among two options.

## 2. What was inspected (structure only, no code copied)

An automated read-only assessment (full detail in this session's transcript; summarized here) confirmed:

- **Stack**: Laravel 5.5.25 (locked), PHP `>=7.0.0`, MySQL-only, Bootstrap 3 + Vue 2 + jQuery + old Laravel Mix, TinyMCE 4.x vendored as static files, DataTables loaded from two different CDN URLs.
- **Route surface**: ~20 routes plus stock `Auth::routes()`. Real product surface: login/register/password reset, `/new_ticket`, `/my_tickets`, `/tickets/{id}`, `POST /comment`, `/home`, and an `admin/*` prefix (ticket index/close/open/destroy) gated by a custom `AdminMiddleware`. Several routes (`/ajax`, `/table`, `/datatables*`, `/image-upload`, `/dropzoneFileUpload`) are leftover prototype/demo code, not part of the real product - excluded from scope entirely rather than migrated.
- **Schema** (`support/database/migrations/`): `users` (+ `is_admin`/`is_dev`/`is_manager` integer flags, only `is_admin` actually used anywhere), `tickets` (numeric `id` PK *and* a separate string `ticket_id` used in URLs - a modelling quirk this rebuild deliberately does not repeat, using one immutable `ticket_number` instead), `categories`, `comments`, plus two later `ADD COLUMN` migrations for ticket/comment images. **No foreign-key constraints anywhere** - all relationships are Eloquent-only.
- **Seeder**: `TicketsTableSeeder` generates exactly 1,000 fake tickets with `user_id` hard-coded to `1` and `category_id` random between 1-4, with **no accompanying user or category seeder** - i.e. it silently assumes rows that nothing else in the repository creates. Confirmed the concern in the BUILD PROMPT directly: **this seeder was never run and nothing from it was used.**
- **Roles**: three boolean-ish integer columns on `users`, only `is_admin` read anywhere, enforced inconsistently across three layers (a route-group middleware, a controller-constructor `auth` check, and raw `Auth::user()->is_admin` checks duplicated in five different Blade views) - including one admin route (`admin/tickets/{id}` show) that sits outside the `admin` middleware group due to a route-declaration-order bug.
- **Uploads**: written directly into the public webroot (`public_path('images/...')`) with **no access control** and timestamp-based (not random) filenames, so any attachment is guessable/enumerable and same-second uploads can silently overwrite each other.

## 3. Field/role mapping (legacy shape -> this pilot's shape)

| Legacy | This pilot | Note |
| --- | --- | --- |
| `users.is_admin` (int) | `administrator` Spatie role | The other two legacy flags (`is_dev`, `is_manager`) were dead columns - not carried forward at all. |
| `tickets.id` (numeric PK) + `tickets.ticket_id` (random string) | `tickets.id` (PK, internal) + `tickets.ticket_number` (`TCK-000123`, sequential, immutable, used in every URL) | Collapses two ID concepts the legacy app never fully reconciled into one predictable, sequential public identifier. |
| `tickets.priority` (free string) | `tickets.priority` (enum P1-P4, derived from an editable impact x urgency matrix) | The legacy column was a free-text string with no validation and only ever set to `"medium"` by the seeder. |
| `tickets.status` (free string, "Open"/"Closed" only) | `tickets.status` (enum, 11 states with an enforced transition map) | See `ARCHITECTURE.md` §3. |
| `categories.name` | `categories.name` + `type` (incident/service_request) + `is_active` | Legacy had no request-vs-incident distinction and no activate/deactivate. |
| `comments` (flat, no visibility) | `ticket_comments.visibility` (public/internal) | Legacy had no concept of an IT-only internal note - every comment was visible to everyone. |
| Images in `public_path('images/...')` | `ticket_attachments` on the private disk, random UUID filenames | See `TECH_STACK.md` and `ARCHITECTURE.md` §7. |
| (nothing - no company/project/site concept existed) | `companies`/`projects`/`sites`/`access_grants` | The legacy app had a single flat ticket list with no multi-tenant or multi-project concept at all; this is new structure, not a migrated one. |

There is **no live data to migrate** - the legacy repository's own seeder was never run and produces synthetic Faker data with a broken foreign-key assumption (see §2), so there is nothing there worth reconciling against real records. If a real organisation's existing helpdesk data ever needs importing, the process below is what to follow; it has not been exercised against real data because none exists for this pilot.

## 4. Dry-run / reconciliation process (for a future real import)

1. **Export** the source system's users, categories, and tickets to CSV/JSON, redacting nothing yet (redaction happens after reconciliation, so mismatches are still traceable).
2. **Dry-run map** each row against the table in §3 using an Artisan command that only *reads* the source and *writes to a separate staging database* - never the production `it-support` database - printing a per-table count of rows mapped, rows skipped (with the specific reason: missing required field, unresolvable category/user reference, etc.), and rows that need manual review (e.g. free-text priority values that don't cleanly map to P1-P4).
3. **Reconcile** every skipped/manual-review row with a human before touching the real database - this project does not attempt automatic best-guess mapping for ambiguous data.
4. **Attachment paths**: copy files (not database rows) from the legacy `public_path('images/...')` locations into the new private disk under `tickets/{new_ticket_id}/`, renaming to a random UUID and recording the *original* filename in `ticket_attachments.original_filename` - never trust the legacy path as a permanent reference once files move.
5. **Password handling**: legacy password hashes are **never carried forward as-is** unless independently confirmed to use a still-supported hashing algorithm compatible with Laravel's current `Hash` facade config. The safe default is to invite every migrated user fresh (see `ADMIN_GUIDE.md` → Users & access → Invite user), which forces a password reset rather than silently trusting an old hash.
6. **Backup before import**: a full backup of both the source system and the new database (see `OPERATIONS.md`) is taken immediately before the real (non-dry-run) import executes.
7. **Rollback**: because the dry run never touches the production database, "rollback" for a *failed dry run* is simply discarding the staging database. Rollback for a *failed real import* is restoring the pre-import backup from step 6 - there is no partial-import repair path; a real import either fully succeeds against the reconciled data or is rolled back wholesale.

No live data was altered by this pilot at any point - this section is a plan for a future import, not a report of one that happened.
