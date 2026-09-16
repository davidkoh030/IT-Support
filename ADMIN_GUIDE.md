# Admin guide

Everything below lives under **Admin** in the top navigation, visible only to users who pass the `manage-admin` gate: an `administrator` (global Spatie role) or anyone with an active `it_manager` access grant on any company or project (`app/Providers/AppServiceProvider.php`).

## Companies

`Admin → Companies`. A company with no parent is a group/top-level legal entity; setting **Parent company** makes it a subsidiary. There is deliberately no separate "group" entity type - see `ARCHITECTURE.md` §2. Companies are never deleted from this UI (only edited) because tickets/assets/projects reference them by ID; retiring a company in a real deployment is a data-migration decision, not a button click.

Each company has its own default `timezone`/`currency` - used as the fallback for projects created under it, not enforced on every ticket.

## Departments

Not yet exposed in the admin UI (the `departments` table and model exist, seeded data doesn't use them yet). Add via `php artisan tinker` or a future admin screen - listed in `ROADMAP.md`.

## Projects

`Admin → Projects`. Set the company, code, name, **lifecycle stage** (proposed → mobilising → active → demobilising → closed - this is informational; nothing currently blocks ticket creation against a `closed` project, by design, since closed projects still need support for outstanding obligations), manager and information manager (both are plain users - the "information manager" role from the ISO 19650-adjacent guidance in `SOURCES.md` is just a labelled field here, not a separate permission), and which sites the project uses (checkboxes - this is the `project_site` many-to-many).

## Sites

`Admin → Sites`. Type is one of HQ / Site office / Warehouse / Precast plant. **Support calendar** here is the SLA business-hours calendar (see Calendars below) - separate from **Operating hours**, which just documents when the physical site is staffed/accessible and is shown to requesters but doesn't drive any SLA math.

## Calendars

`Admin → Calendars`. Each calendar has a timezone, a set of working weekdays, a daily start/end time, and a list of holiday dates. This is what `Calendar::addBusinessMinutes()` actually walks when computing an SLA target - see `ARCHITECTURE.md` §4. **To add a Malaysia-specific holiday calendar** (or any other regional one), create a new calendar here and point the relevant SLA policies and sites at it; the seeded demo data already has separate Singapore and Malaysia calendars as a working example.

Editing a calendar's hours/holidays only affects **future** SLA target calculations for tickets created after the edit - it does not retroactively recompute existing tickets' `target_at` (that would silently rewrite history the audit trail depends on).

## Categories

`Admin → Categories`. Each is `incident` or `service_request`. "Delete" deactivates rather than removes a category, so historical tickets keep a valid reference.

## SLA policies

`Admin → SLA policies`. One row per priority (P1-P4), each pointing at a calendar. Leave **Company scope** blank to make a policy the default for that priority; set it to a specific company to override the default only for that company's tickets (this is how the demo data gives Malaysia its own calendar for the same P1-P4 targets - see the "Malaysia" rows). `TicketService::resolveSlaPolicy()` always prefers a company-specific row over the blank-scope default for the same priority.

## Priority matrix

`Admin → Priority matrix`. A 3x3 grid (impact x urgency) each mapped to P1-P4. This is exactly the table from the BUILD PROMPT section 7, pre-seeded and editable; a change here affects every **new** ticket's calculated priority immediately (existing tickets keep the priority they were created or overridden with).

## Vendors

`Admin → Vendors`. Contact info, support hours, warranty reference. Linked from both `tickets.vendor_id` (which vendor is handling this ticket) and `assets.vendor_id` (which vendor supplied/supports this asset).

## Assets

`Admin → Assets`. Standard fields plus **Network notes** - a free-text field that is never shown outside this admin screen (enforced by `AssetPolicy`, not just hidden in a view), for exactly the kind of operational network detail section 5 says must stay IT-only. Changing **Assigned user** or reassigning **Status** writes a new `AssetEvent` row (visible at the bottom of the edit form) rather than silently overwriting the previous assignment - custody history is permanent.

## Users & access

`Admin → Users & access`.

- **Invite user**: name, email, phone, optional vendor (only for external vendor contacts). No password field - the app generates a random one and queues a password-reset email (see `LOCAL_SETUP.md` for where that email lands in dev). There is no self-registration route in this application at all.
- **Edit user**: toggle **Active** (an inactive user is blocked from creating tickets by `TicketPolicy::create`, though existing sessions aren't force-logged-out - a genuine gap for a real deployment, noted in `ROADMAP.md`) and **System administrator** (grants the global, unscoped Spatie `administrator` role - use sparingly).
- **Access grants** (bottom of the edit-user page): add a scoped role (`member`/`project_manager`/`it_agent`/`it_manager`/`approver`/`auditor`), a scope type (company or project), the numeric ID of that company/project, an effective date, and an optional expiry date. **The scope-ID dropdown currently only lists companies** - to grant a *project*-scoped role, switch "Scope type" to Project and type the project's numeric ID directly (visible in the Projects list's edit URL, e.g. `/admin/projects/4/edit` → ID 4). This is a known rough edge, not a bug: building a second dependent dropdown was deprioritised in favour of getting the underlying scoping logic (which is the part that matters for security) correct and tested. **Revoke** timestamps `revoked_at` rather than deleting the row, so "who had access and when" is never lost.

## Knowledge articles

`Admin → Knowledge articles`. Draft articles are invisible on the public `/knowledge` page until switched to Published. Set a **review date** as a reminder of when the content should be re-checked (nothing currently automates a review-due notification - `ROADMAP.md`).

## Request templates

`Admin → Request templates`. Six templates (Joiner, Transfer, Leaver, External access, Site mobilisation, Site demobilisation), each defined in `config/itsm.php` - **not** editable from this screen. Raising one here creates a `service_request` ticket with that template's checklist tasks and approval step(s) already attached; fill in company, optional project, the subject user (who this is about), the approver, and a summary/description. To change *what* a template's checklist contains, edit `config/itsm.php` directly and redeploy - this is a deliberate, documented limitation (see `REQUIREMENTS.md` §8), not an oversight.

## Everyday moderation

There is no separate "moderation" screen - ticket-level actions (assign, transition status, override priority, decide an approval, complete a checklist task, share with a vendor) all happen directly on the ticket's own page (`/tickets/{ticket_number}`), gated by `TicketPolicy`. An administrator can do anything an IT manager can; there's no reason to duplicate those controls into a separate admin ticket screen.
