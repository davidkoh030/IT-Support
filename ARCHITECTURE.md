# Architecture

## 1. Shape

A single Laravel monolith (Blade + Alpine.js, no separate API/SPA). Controllers call small service classes (`app/Services/*`) for anything with real business logic (priority calculation, SLA clocks, ticket lifecycle); everything else is a thin Eloquent-backed controller + policy. There is no microservice boundary anywhere - the prompt explicitly asked to avoid that, and a pilot of this size doesn't need it.

## 2. Data model and relationships

```
Company (self-referencing parent_company_id = group/subsidiary)
 ├─ Department
 ├─ Project (belongs to Company; manager_user_id, information_manager_user_id)
 │    └─ (many-to-many) Site
 ├─ Site (belongs to Company; support_calendar_id -> Calendar)
 └─ SlaPolicy (optional company override; falls back to the global-default row)

Calendar
 └─ CalendarHoliday (many)

AccessGrant (user_id, scope_type: company|project, scope_id, role, effective/expiry/revoked_at)
 - role ∈ {member, project_manager, it_agent, it_manager, approver, auditor}
 - this ONE table is the entire scoping model: no separate "project_members"
   or "company_staff" tables. A company-scoped grant also covers every
   descendant company (Company::ancestorChain / idsIncludingDescendants),
   so a group-level IT manager needs one grant, not one per subsidiary.

User (Spatie HasRoles - "administrator" is the only global role; vendor_id -> Vendor)

Ticket
 ├─ belongs to: Company, Project?, Site?, Category, Vendor?, SlaPolicy?
 ├─ requester_id / assigned_agent_id -> User
 ├─ has many: TicketEvent (audit trail), TicketComment (public|internal),
 │            TicketAttachment, TicketShare, TicketSlaClock (first_response|restoration),
 │            SatisfactionScore, ChecklistTask, Approval (polymorphic), ExternalAccessGrant
 ├─ many-to-many: Asset (ticket_asset)
 └─ major_incident_id -> Ticket (self-reference, for linked reports)

Asset
 ├─ belongs to: Company, Site?, Project?, User? (assigned_user_id), Vendor?
 └─ has many: AssetEvent (append-only custody history)

Approval (polymorphic approvable_type/id - today always Ticket, extensible later)
KnowledgeArticle (owner_id -> User)
AuditLog (actor_id -> User?, entity_type/entity_id, before/after JSON) - append-only, no updated_at
```

### Why `access_grants` instead of Spatie's team/permission features

Spatie's roles are naturally global ("this user is an `editor`"). This system needs "this user is an `it_agent` **for company 3** from 1 March to 30 June" - a role *plus* a scope *plus* a validity window, on potentially many rows per user (a PM on one project, an IT agent on another company). A single polymorphic-scope table with `effective_date`/`expiry_date`/`revoked_at` expresses that directly and is queried the same way everywhere (`User::hasScopedRole()`, `User::activeAccessGrants()`, `Ticket::scopeVisibleTo()`). `administrator` stays a plain Spatie role because it genuinely is global and unscoped - using the scoped table for it would just mean every admin grant duplicated across every company for no benefit.

### Why company hierarchy resolves at query time, not by data duplication

An `access_grants` row for the group company (`Straits Build Group`) must cover its two subsidiaries (`... Pte Ltd`, `... Sdn Bhd`) without a separate grant per subsidiary - otherwise onboarding a new legal entity would silently break every group-level IT manager's access until someone remembered to add a new grant. `Company::ancestorChain($id)` walks `parent_company_id` up from a ticket's company to the group, and `hasScopedRole()`/`scopeVisibleTo()` both check membership in that chain rather than an exact `scope_id` match. This was a real bug caught during manual testing this session (an IT manager granted at group level got a 404/403 on a subsidiary's ticket) - see `TEST_REPORT.md`.

## 3. Ticket lifecycle state machine

`App\Models\Ticket::ALLOWED_TRANSITIONS` is the **single** source of truth for legal status transitions:

```
new -> triaged, assigned, cancelled
triaged -> assigned, waiting_approval, cancelled
assigned -> in_progress, waiting_requester, waiting_vendor, waiting_approval, scheduled, cancelled
in_progress -> waiting_requester, waiting_vendor, waiting_approval, scheduled, resolved, cancelled
waiting_requester -> in_progress, resolved, cancelled
waiting_vendor -> in_progress, resolved, cancelled
waiting_approval -> assigned, in_progress, cancelled
scheduled -> in_progress, resolved, cancelled
resolved -> closed, in_progress (= reopen)
closed, cancelled -> (terminal)
```

`TicketService::transition()` (not the controller) throws `InvalidArgumentException` on an illegal move, and additionally refuses to resolve a ticket whose `approval_state` is `pending` or `rejected`. Enforcing this in the service - not the controller - means there is exactly one code path that can change a ticket's status; any future API endpoint that reuses `TicketService` inherits the same guarantee for free.

`approval_state` (`not_required`/`pending`/`approved`/`rejected`) is a column independent of `status`, per the prompt's explicit "an approval cannot silently authorise unrelated work" requirement - a ticket can sit in `in_progress` with a pending approval on some unrelated checklist step without that approval controlling the ticket's own workflow, except at the one point (`resolved`) where the code deliberately checks it.

## 4. SLA clocks

Two `ticket_sla_clocks` rows per ticket (`first_response`, `restoration`), each with a `target_at` computed once at creation (or priority-change) from the SLA policy's `Calendar` (`Calendar::addBusinessMinutes()` walks forward through working days/hours, skipping non-working days and `calendar_holidays`). A clock's *effective* deadline is `target_at + paused_seconds`; pausing (`waiting_requester` only, by default) doesn't move `target_at` itself, it accumulates `paused_seconds` so the audit trail always shows both the original target and how much time was excused. `sla:scan` (scheduled every 5 minutes) finds clocks approaching or past their effective deadline and notifies the assigned agent, using `warned_at`/`breached_notified_at` to guarantee at most one notification per clock per threshold no matter how many times the scheduler fires.

**Metric semantics**: "first response" is only recorded when a **non-requester** posts a **public** comment - never by an automated acknowledgement, per the prompt's explicit instruction. "Restoration" is recorded the first time a ticket reaches `in_progress` or `resolved`. A major incident's linked reports each have their own clocks (so each affected user's own SLA is tracked) but the *service-status page* reports the major incident's own status once, not once per linked report - avoiding the double-counting the prompt warns against.

## 5. Authorization boundary

Every ticket-related controller action calls a `TicketPolicy` method; every list/export query goes through `Ticket::scopeVisibleTo()`. These two are kept deliberately parallel (same company/project/restricted logic) rather than one delegating to the other, because a policy check (single record) and a scope (query builder) can't literally share one method body in Eloquent - but they're tested together (`TicketAuthorizationTest`) specifically to catch drift between them. Attachment downloads (`TicketAttachmentController`) re-run the parent ticket's `view` policy (and `viewInternalNotes` for an attachment on an internal comment) rather than trusting that "the attachment ID was in a URL the user was shown" implies authorisation - a URL is not a credential.

## 6. Background jobs

- **Queue**: `QUEUE_CONNECTION=database`. All five notification classes implement `ShouldQueue`. Run with `php artisan queue:work` (see `OPERATIONS.md` for supervisor/systemd notes for a real deployment).
- **Scheduler**: `routes/console.php` registers `sla:scan` every 5 minutes via `Schedule::command()`. Nothing fires this on its own - it needs `php artisan schedule:work` (dev) or a real cron entry calling `schedule:run` every minute (production), documented in `OPERATIONS.md`.

## 7. File storage boundary

Two local disks: `public` (Laravel's default, unused by this app - nothing writes there) and `private` (`storage/app/private/attachments`, `'serve' => false` in `config/filesystems.php`). Every ticket/comment attachment goes to `private` with a random UUID filename; there is no path from an attachment's database row to a guessable public URL. Downloads are served through `TicketAttachmentController@show`, which is the only place a private-disk file is ever streamed to a browser, and it always re-checks authorization first.

## 8. What's deliberately *not* here

- No API layer / Sanctum tokens - the prompt's core pilot is a server-rendered app; an API is Phase C territory if ever needed.
- No JavaScript framework beyond Alpine (bundled with Breeze) - forms use plain HTML with a little `x-data` for the idempotency key and conditional resolution-code field.
- No rich-text editor - ticket/comment bodies are plain text, escaped by Blade's default output. This sidesteps an entire class of HTML-sanitisation work the legacy app (via `mews/purifier`) needed, at the cost of not supporting formatted replies - a reasonable trade for a pilot.
