# Requirements: core vs deferred, assumptions, coverage

Section numbers refer to the BUILD PROMPT. Status legend: **Implemented** (working, seeded, and either automated-tested or manually verified this session - see `TEST_REPORT.md` for which), **Partial** (a real, working subset; gaps stated), **Deferred** (Phase C, see `ROADMAP.md`), **Assumption** (a choice made because the prompt left it open).

## Section 4 - Organisation, project scope and permissions

| Requirement | Status | Notes |
| --- | --- | --- |
| Group / legal company / department / project / site modelled separately | Implemented | `companies` self-references via `parent_company_id` (group vs. legal entity); `departments`, `projects`, `sites` are separate tables. See `ARCHITECTURE.md` for the modelling choice and why a separate `groups` table wasn't added. |
| Project ↔ site many-to-many | Implemented | `project_site` pivot. |
| HQ / shared-services ticket without a fabricated project code | Implemented | `tickets.project_id` is nullable; a null project means HQ/shared-services, and the create form offers "HQ / shared services" instead of forcing a project pick. |
| Project code, lifecycle stage, dates, manager, information manager | Implemented | `projects` columns; admin CRUD at `/admin/projects`. |
| Site address, area/block/floor/zone, operating hours, access contact, support calendar | Implemented | `sites` columns; admin CRUD at `/admin/sites`. |
| Project membership effective/expiry dates affecting tickets/attachments/search/exports/notifications | Implemented | `access_grants.effective_date`/`expiry_date`/`revoked_at`; `User::hasScopedRole()` and `Ticket::scopeVisibleTo()` both filter on these live, so expiry removes access from every listing, not just a UI toggle. Automated test: `TicketAuthorizationTest::test_project_transfer_and_membership_expiry_revokes_access`. |
| Roles: requester, PM, IT agent, IT manager, approver, vendor, auditor, sysadmin | Implemented | `administrator` is a global Spatie role; `project_manager`/`it_agent`/`it_manager`/`approver`/`auditor`/`member` (=requester's base membership) are scoped rows in `access_grants`; `vendor` is `users.vendor_id` pointing at a `vendors` row. See `ARCHITECTURE.md` for why these are three different mechanisms. |
| Requesters see own + explicitly shared tickets | Implemented | `ticket_shares` table; `TicketPolicy::view` and `Ticket::scopeVisibleTo`. |
| PM sees authorised non-restricted project tickets | Implemented | Restricted tickets excluded from PM/auditor scope even within their project - tested in `TicketAuthorizationTest`. |
| IT scoped to company/project | Implemented | Company-scoped grants also cover subsidiaries via `Company::ancestorChain()`/`idsIncludingDescendants()` (a group-level IT manager sees every legal entity under it without a grant per company). |
| Vendor sees only assigned/shared | Implemented | `Ticket::scopeVisibleTo` for `vendor_id` users. |
| Auditor time-bound scope | Implemented | Same `access_grants` row type as everything else, with `effective_date`/`expiry_date`; auditors get read-only access (no `transition`/`assign`/`comment` internal ability). |
| Restricted visibility overriding ordinary PM access | Implemented | `tickets.restricted`; enforced in `TicketPolicy::view` and the `scopeVisibleTo` query, not just the Blade view. |
| Internal notes never exposed to requesters/vendors | Implemented | `ticket_comments.visibility`; policy-gated `viewInternalNotes`/`commentInternal`; tested. |
| Server-side authorisation on every record/file operation, deny by default | Implemented | Every controller action calls `$this->authorize()`; attachment downloads re-check the parent ticket's policy (`TicketAttachmentController`); no policy method defaults to `true` without a real check. |
| No access solely from email-domain match | Implemented | Nothing in the codebase reads the email domain for authorisation; scope is always an explicit `access_grants` row. |
| Invited local accounts, secure password reset, no open registration | Implemented | `Auth\RegisteredUserController` and its routes were removed; `Admin\UserController::store` creates the user with a random 40-char password and immediately queues a password-reset link (log-driver mail in dev). |
| Optional Entra sign-in | Deferred | Phase C item 1 - see `ROADMAP.md`. |

## Section 5 - Fast reporting from office or site

| Requirement | Status | Notes |
| --- | --- | --- |
| Responsive phone/tablet/desktop portal, large touch targets | Implemented | Tailwind, single-column mobile layout, large buttons/inputs. Verified visually at 390x844 (iPhone-class) and 1440x900 viewports - screenshots delivered in-session. |
| Main actions: report problem, request equipment/access, my tickets, service status, help articles | Implemented | Top nav; "request equipment/software/access" is the `service_request` ticket type plus the request-template flows (§8) rather than a separate menu entry. |
| Agent work queue separate from requester view | Implemented | `/tickets?view=assigned` / `?view=unassigned`, dashboard tiles differ by role. |
| Short guided form, minimal required fields, prefilled identity | Implemented | `tickets/create.blade.php`: company/project/category/summary/description/impact/urgency required; everything else optional. Requester identity comes from the session, not a form field. |
| Conditional fields (asset, affected users, deadline, etc.) | Partial | Fields exist and are shown, but are not yet conditionally *hidden/revealed* based on impact (e.g. "work stopped" doesn't yet auto-reveal a workaround field). All optional fields are simply always visible under a clearly optional heading - simpler, but not the full "conditional" UX the prompt describes. |
| QR code opens authenticated form, prefilled, no sensitive data pre-auth | Implemented | `GET /report/asset/{asset:tag}` checks the user's company/IT scope for that asset *before* redirecting into the create form with `asset_id`/`site_id`/`company_id` prefilled; an unauthorised user gets a 403, not asset details. |
| Photo capture, compression, upload progress/retry | Partial | Upload works (validated mime/size, private storage); no client-side image compression and no explicit progress/retry UI beyond the browser's native file input behaviour. |
| Failed submission preserves form, no false "submitted" state | Implemented | Standard Laravel validation redirect-back-with-old-input; the success page only renders after a real 302 to the ticket's own URL, never optimistically. |
| Idempotency key prevents duplicate tickets on retry | Implemented | `tickets.idempotency_key` unique column; `TicketService::create()` returns the existing ticket if the key was already used. Automated test: `TicketSubmissionTest::test_repeated_submission_after_a_network_error_creates_exactly_one_ticket`. **Caveat:** the key is generated once by Alpine.js when the page loads; it is stable across a resubmission of the *same* page load (e.g. a failed fetch, browser "resend form data") but a full page *reload* generates a new key. True offline-safe idempotency (surviving a reload) is a Phase C item. |
| Operational network detail hidden from non-IT | Implemented | `assets.network_notes` is never rendered outside `/admin/assets` (IT-only route, gated by `AssetPolicy`). |
| No collecting passwords/MFA/API secrets in the form | Implemented | No such field exists anywhere in the ticket form. |

## Section 6 - Ticket catalog, records and lifecycle

| Requirement | Status | Notes |
| --- | --- | --- |
| Configurable incident/service-request categories, seeded per the listed examples | Implemented | `categories` table, seeded with all nine bullet groups from the prompt (see `DemoDataSeeder`). |
| Immutable identifier, full field set (requester, scope, type, category, impact/urgency/priority, assignment, approval state, timestamps, SLA snapshot, replies, notes, attachments, vendor link) | Implemented | `tickets` table; `ticket_number` is set once at creation and never updated. |
| Event history for transitions/assignment | Implemented | `ticket_events`, written by `TicketService` on every mutation, never edited or deleted by any application code path. |
| Lifecycle: New → Triaged → Assigned → In progress → Resolved → Closed + branches | Implemented | `Ticket::ALLOWED_TRANSITIONS` is the single source of truth, enforced in `TicketService::transition()` (throws on an illegal move) - not just in the controller, so there is no code path that can skip it. Tested: `PriorityAndSlaTest::test_illegal_transition_is_rejected`. |
| Approval state separate from ticket status | Implemented | `tickets.approval_state` (`not_required`/`pending`/`approved`/`rejected`) is a distinct column from `status`; resolving is blocked while `pending` or `rejected` (`TicketService::transition`). |
| Resolution code + explanation, workaround tracked separately, requester confirmation, configurable reopen | Implemented | `resolution_code`/`resolution_notes`/`workaround`/`requester_confirmed`/`reopen_count` columns. Auto-closure is **not implemented and not enabled** - a resolved ticket stays resolved until a human closes or reopens it, matching "demo auto-closure should be disabled until configured." |
| Major incident linking multiple reports, published status without leaking private content | Implemented | `tickets.major_incident_id` self-reference; `/service-status` shows only summary/status/company/project/site of tickets with linked reports, never comments/notes/attachments. |
| Recurring-incident links, lightweight root cause | Partial | The same `major_incident_id` link doubles as the "recurring incident" link; there is no separate root-cause-record field beyond the resolution notes. Full problem management is Phase C item 8. |

## Section 7 - Priorities and SLA

| Requirement | Status | Notes |
| --- | --- | --- |
| Priority from impact × urgency, admin-editable matrix | Implemented | `priority_matrix_rules`, editable at `/admin/priority-matrix`; `PriorityCalculator` reads it live. |
| IT can override priority with reason, audited | Implemented | `tickets.priority_overridden*` columns + `AuditLog` + `ticket_events` row. Tested end-to-end (manual curl session) and unit-level in `PriorityAndSlaTest`. |
| Security escalation for suspected active compromise | Implemented | The ticket form's "This may be a security incident" checkbox sets `restricted = true` immediately; it does **not** auto-set priority to P1 (IT still confirms classification and impact/urgency, per the prompt's explicit instruction not to auto-escalate). |
| Demo priority/SLA matrices from the prompt, editable, not contractual | Implemented | Seeded exactly as specified (P1 15min/4h ... P4 8h/40h); `/admin/sla-policies` and `/admin/priority-matrix` let an administrator change them. |
| Separate support-hours calendar vs site operating hours | Implemented | `calendars` (support/SLA clock) vs `sites.operating_hours_start/end` (site access hours) are different tables/columns; demo data seeds distinct Singapore and Malaysia support calendars plus separate site operating hours. |
| No 24/7 promise; optional on-call; P1 continuous clock only when staffed | Partial | The calendar model supports a 24/7 calendar (e.g. the precast plant site uses `00:00-23:59` operating hours), and SLA policies can be pointed at any calendar - but there is no dedicated on-call-schedule entity or a UI toggle labelled "continuous clock policy." An administrator can approximate it today by creating a 24/7 `Calendar` and assigning it to a policy. |
| First response / restoration / resolution tracked separately; automated ack ≠ human response | Implemented | `first_response_at` is only set by `TicketService::addComment()` when a **non-requester** posts a **public** comment - an automated "ticket received" message is never generated, so there is nothing that could be mistaken for a human response. |
| First-response clock never pauses; only waiting-requester pauses restoration by default; vendor-waiting keeps counting | Implemented | `SlaClockService`; tested in `PriorityAndSlaTest::test_waiting_requester_pauses_restoration_clock_but_waiting_vendor_does_not`. |
| Breach/pre-breach escalation, deduplicated | Implemented | `sla:scan` console command + `warned_at`/`breached_notified_at` columns prevent duplicate notifications on re-run. Tested: `SlaScanCommandTest`. Scheduled every 5 minutes in `routes/console.php` - requires `php artisan schedule:work` (dev) or real cron (`OPERATIONS.md`) to actually fire. |
| Report gross elapsed + paused time; reopen/priority/policy changes don't erase earlier breaches | Implemented | `paused_seconds` accumulates rather than resetting; `overridePriority()`/`rebaseAfterPolicyChange()` only touch clocks that haven't `achieved_at` yet, leaving an already-achieved or already-breached clock's history intact. |
| Timers via a real scheduled worker, documented | Implemented | See `OPERATIONS.md` for exact `schedule:work`/cron instructions. |

## Section 8 - Access, employee movement, site lifecycle

| Requirement | Status | Notes |
| --- | --- | --- |
| Joiner/Transfer/Leaver/External access/Mobilisation/Demobilisation templates with checklist + approval | Implemented | `config/itsm.request_templates`; `Admin\RequestTemplateController` raises a `service_request` ticket with its checklist tasks and approval step(s) attached. **Limitation:** the template *definitions* (which checklist items, which approver role) are configurable only by editing `config/itsm.php` - there is no GUI template editor in this pilot. This is stated honestly rather than built as a stretch feature under time pressure. |
| External access: sponsor, employer, resource, purpose, level, expiry; revocation evidence; don't claim revoked when only membership expired | Implemented | `external_access_grants` table with a distinct `revoked_at`/`revocation_evidence` from the ticket's own status; `isOverdueForRevocation()` and the IT-manager dashboard surface an expired-but-not-revoked grant until evidence is recorded. Tested. |
| Route approval to resource owner/manager/information manager; procurement thresholds; block self-approval | Implemented (self-approval + wrong-approver blocking); Partial (procurement thresholds) | `approvals.approver_role` covers `resource_owner`/`manager`/`information_manager`/`finance`, and `TicketController::decideApproval` refuses self-approval and refuses anyone but the named `approver_user_id`/delegate - tested (`test_self_approval_is_rejected_even_via_direct_endpoint`, `test_unauthorised_approver_cannot_decide...`). The `threshold_amount`/`currency` columns exist on `approvals` but nothing yet auto-routes a request to a different approver based on amount - an administrator sets the approver manually per request. |
| Track rejected/withdrawn/expired approvals + delegation | Implemented | `approvals.decision` enum includes all four states plus `delegated_to_id`. |
| Site mobilisation/demobilisation checklists; project closure doesn't delete history | Implemented | Same request-template mechanism; asset/ticket/checklist rows are never deleted on "closure," only status/lifecycle_stage fields change. Tested: `AssetAndDemobilisationTest`. |

## Section 9 - Assets and vendors

| Requirement | Status | Notes |
| --- | --- | --- |
| Asset register with the specified fields | Implemented | `assets` table; admin CRUD at `/admin/assets`. |
| Custody/transfer/loan/repair/return/disposal as history, never overwritten | Implemented | `asset_events`, append-only; `Admin\AssetController` writes a new event on every reassignment instead of just updating `assigned_user_id`. |
| Tickets linked to assets/services; seeded router/switch/laptop/printer/UPS/meeting-room display | Implemented | `ticket_asset` pivot; demo data seeds exactly this asset mix. No "heavy-equipment maintenance module" was built, per the explicit instruction not to. |
| Vendor ownership/contact/hours/warranty/case number/attendance/due date/closure evidence | Implemented | `vendors` table + `tickets.vendor_case_number`/`vendor_update_due_at`/`vendor_closure_evidence`. |
| PO reference + approved cost linked to cost code (reference only, not a ledger) | Implemented | `assets.approved_cost`/`currency`/`cost_code`; explicitly not wired to any accounting system. |

## Section 10 - Security, evidence, resilience

| Requirement | Status | Notes |
| --- | --- | --- |
| Scoped permissions in queries, downloads, exports, jobs, notifications | Implemented | `Ticket::scopeVisibleTo` is the one query scope used by the index, the CSV export, and the dashboard "at risk"/"pending approval" widgets - there is no second, unscoped query path. Tested (`CsvExportTest`). |
| Private attachment storage, authorisation, MIME/size allowlist, random names, no executables/archives | Implemented | `private` disk (`serve => false`), `mimes:jpg,jpeg,png,gif,webp,pdf` allowlist (no `.exe`, no archives), UUID filenames. Tested: `TicketSubmissionTest::test_disallowed_file_type_is_rejected`. **Not implemented:** an actual malware-scanning integration - the code has an obvious extension point (`TicketController::storeAttachments`) but no scanner is wired up (there's nothing to scan with, locally). |
| Rich-text sanitisation, CSRF, output escaping, rate limits, secure sessions, no debug info in prod | Partial | Ticket bodies are plain `text` inputs escaped by Blade's default `{{ }}` output (no HTML/rich-text editor was built, so there is no HTML-injection surface to sanitise in the first place - a smaller but sufficient guarantee for this pilot). CSRF is Laravel's default (`@csrf` + `VerifyCsrfToken`), sessions are the framework default (`SESSION_DRIVER=database`, secure cookies default off only because `APP_ENV=local`/no HTTPS locally). Laravel's default login throttling (`throttle` on the auth routes) is in place; no additional custom rate limiting was added. `APP_DEBUG` must be set to `false` for any non-local environment - **this is a manual deployment step, not enforced by code**, and is called out in `OPERATIONS.md`. |
| Audit log: actor/action/timestamp/entity/before-after for access/approval/status/priority changes, downloads, exports, config | Implemented | `audit_logs` table + `AuditLog::record()`, called from every mutating controller/service path exercised in this session (ticket lifecycle, approvals, checklist, access grants, admin CRUD, attachment downloads, CSV export). `audit_logs` has no `updated_at` and no application code path updates a row after insert - but this is an application-level convention, not a database-enforced immutability guarantee; a superuser with direct DB access could still edit it. This is stated plainly rather than described as "tamper-proof." |
| Retention config, hold exceptions, previewable deletion/anonymisation, minimise PII, audit export/access-correction workflow | Deferred (data-level); Assumption (defaults) | `config('itsm.retention_days')` documents intended retention periods per record class; **no scheduled deletion/anonymisation job runs them yet** - implementing an irreversible deletion job without a "preview" UI first felt like the wrong tradeoff to make unreviewed. CSV export (`/tickets-export.csv`) doubles as a basic authorised data-export workflow. Listed honestly in `ROADMAP.md` rather than half-built. |
| Singapore PDPA as a deployment review input; record hosting region/processors | Assumption | Demo defaults assume Singapore-hosted data (`ITSM_DEFAULT_TIMEZONE=Asia/Singapore`), but this pilot makes **no hosting-region claim** - it runs in whatever container executes it. A real deployment must independently confirm hosting region and any external processors (e.g. an SMTP relay) against PDPA and any other applicable law; see `SOURCES.md`. |
| Backup/restore documented and evidenced in a disposable environment | Implemented (documented + exercised) | See `OPERATIONS.md` for the exact SQLite file-copy backup/restore procedure and `TEST_REPORT.md` for the restore evidence captured this session. MySQL/`mysqldump` equivalents are documented but not exercised (no MySQL server available here - see `TECH_STACK.md` §4). |
| Restricted security-incident handling distinct from general notifications; assessment/containment/recovery evidence; legal notification stays with authorised staff | Implemented | `tickets.restricted` plus internal-only comments (`visibility = internal`) is exactly how the demo phishing-incident ticket records assessment/containment steps, invisible to the ordinary project manager. No code path sends any external legal/regulator notification - that decision is explicitly left to a human, as instructed. |

## Section 11 - Notifications, knowledge, reporting

| Requirement | Status | Notes |
| --- | --- | --- |
| In-app notifications + queued email, log driver by default, no live messages in dev | Implemented | Laravel's `database` + `mail` notification channels, both queued (`ShouldQueue`); `MAIL_MAILER=log` in `.env`/`.env.example`. Verified this session: notifications queued during seeding, processed by `queue:work`, landed as `notifications` rows and in `storage/logs/laravel.log` - never sent anywhere external. |
| Notify on creation/assignment/reply/approval/escalation/resolution, permission-scoped; internal notes never leak into email | Partial | Assignment, public reply, approval-requested, resolution, and SLA breach/warning are implemented and notify only the relevant single recipient (agent/requester/approver). **Ticket-creation** does not yet notify anyone (no "new ticket" broadcast to the team) - the dashboard's "Unassigned (team)" tile is today's substitute for that. `TicketPublicReply` is only ever constructed from a `visibility = public` comment (enforced in `TicketService::addComment`), so an internal note can never reach an email subject/body/attachment. |
| Searchable KB, owner/audience/review date/draft-published | Implemented | `knowledge_articles`; `/knowledge` (published only) and `/admin/knowledge-articles` (full CRUD). Seeded with three IT help articles containing no passwords or bypass instructions. |
| Role-specific views (requester/agent/PM/IT manager) | Partial | Requester and agent views are fully implemented (dashboard tiles + filtered ticket lists). The IT-manager view has volume/priority/overdue tiles and an overdue-external-access widget, but **not** the full analytics set below. |
| IT manager metrics: volume/category/site trends, median/p90 response/restoration, SLA compliance, ageing, repeat incidents, reopened, asset issues, optional cost | Deferred | Only "unassigned count" and "at-risk (SLA due within 2h)" are implemented on the dashboard today. The underlying data (every `ticket_sla_clocks` row, `reopen_count`, `major_incident_id` links) is all present and query-able - this is a reporting-view gap, not a missing-data gap - listed in `ROADMAP.md` as a fast Phase C follow-up precisely because the data model already supports it. |
| Metric definitions documented; major incident duration measured once | Partial | `ARCHITECTURE.md` documents clock semantics; there is no separate "major incident duration" metric yet since the analytics views themselves are deferred (see above) - but the data model already avoids double-counting by linking reports to one `major_incident_id` rather than duplicating the clock. |
| Permission-scoped CSV export with formula-injection protection | Implemented | `/tickets-export.csv` reuses `Ticket::scopeVisibleTo`; leading `=`/`+`/`-`/`@`/tab/CR characters are neutralised with a leading apostrophe. Tested: `CsvExportTest`. |
| Optional satisfaction score | Implemented | `satisfaction_scores`; requester-only, only after resolution. |

## Section 12 - Phase C

Not implemented; see `ROADMAP.md` for the full prioritised backlog with prerequisites and acceptance criteria per item.

## Assumptions log (things the prompt left open)

1. **Group vs. legal company**: modelled as a self-referencing `companies.parent_company_id` rather than a separate `groups` table (fewer joins, same expressiveness for a two-level hierarchy). See `ARCHITECTURE.md`.
2. **"Requester" is not a distinct role table** - anyone with an active `access_grants` row of any kind (including the base `member` role) may raise tickets in that scope; someone with *no* grant anywhere cannot raise a ticket at all (there is nothing for them to be "the requester for"). This matches "deny by default."
3. **Demo support hours** (Mon-Fri 08:30-17:30, Asia/Singapore/Kuala_Lumpur) are labelled as an assumption in the seeded `calendars` names and in this document, per the prompt's explicit instruction not to present them as a promise.
4. **Auto-closure is present in the schema but disabled** - no scheduled job closes a resolved ticket. An administrator would need to explicitly build/enable that job for a real deployment.
5. **Attachment allowlist** is `jpg,jpeg,png,gif,webp,pdf`, 5MB/file, max 5 files - a reasonable default for site photos and short documents, not stated explicitly in the prompt.
