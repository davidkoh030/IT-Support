# Roadmap: Phase C and near-term follow-ups

Everything here is **backlog only** - none of it is implemented. Ordered roughly by (value ÷ effort), not strictly by the BUILD PROMPT's own numbering. Each Phase C item below restates the prompt's own numbering in brackets for traceability.

## Near-term (not in the prompt's Phase C list, but small gaps surfaced by this session's own testing)

### N1. IT-manager analytics dashboard
**Why now**: `REQUIREMENTS.md` §11 notes the underlying data (every `ticket_sla_clocks` row, `reopen_count`, `major_incident_id` links, `satisfaction_scores`) already exists - this is a reporting-view gap, not a data-model gap, so it's cheap relative to its value.
**Prerequisites**: none new.
**Acceptance criteria**: a dashboard view showing, per company/project/site/category and configurable time window: ticket volume, median and p90 time-to-first-response and time-to-restoration (computed from `ticket_sla_clocks`, excluding paused seconds), SLA compliance %, ticket ageing (open tickets bucketed by age), reopened-ticket rate, repeat-incident rate (tickets sharing a category+site within N days), and average satisfaction score. Denominators, exclusions, and the exact clock used for each metric must be written down in `ARCHITECTURE.md` before shipping, per the prompt's own instruction not to leave metric definitions implicit.

### N2. Retention/anonymisation job with a preview mode
**Why now**: `config('itsm.retention_days')` already states intent; a real deployment needs it enforced, not just documented.
**Prerequisites**: a decision (with legal/compliance input, not an engineering guess) on what "anonymise" means per record class - delete outright vs. null out PII fields vs. archive off-database.
**Acceptance criteria**: a scheduled command that, by default, only **lists** what it would delete/anonymise and why (dry run); a `--apply` flag required to actually act; a per-record "hold" flag that unconditionally excludes a record from any automatic action; an audit-log entry for every record actually affected.

### N3. Second dependent dropdown for project-scoped access grants
**Why now**: `ADMIN_GUIDE.md` documents a real rough edge (admins must type a project ID by hand today).
**Prerequisites**: none.
**Acceptance criteria**: selecting "Project" as scope type in `/admin/users/{user}/edit` populates a second dropdown of project names/codes instead of requiring a raw numeric ID.

### N4. Automated backup/restore check in CI
**Why now**: `TEST_REPORT.md` acceptance-criterion 11 was verified manually twice (the first attempt was a test-harness mistake, caught by hand) - a scripted version would catch this class of mistake automatically going forward.
**Prerequisites**: none.
**Acceptance criteria**: a shell script (or PHPUnit test shelling out) that creates a fixture attachment, backs it up, deletes it, restores, and asserts recovery - runnable in CI, not just by hand.

### N5. Deactivating a user should also invalidate existing sessions
**Why now**: noted as a gap in `ADMIN_GUIDE.md` - toggling "Active" off blocks *new* ticket creation but doesn't force-logout an already-authenticated session.
**Prerequisites**: none (Laravel supports session invalidation per-user via the `remember_token`/session-driver).
**Acceptance criteria**: deactivating a user immediately ends any of their active sessions, verified by a test that logs a user in, deactivates them, and asserts their next authenticated request is redirected to login.

## Phase C (BUILD PROMPT section 12)

### [1] Microsoft Entra ID SSO
**Prerequisites**: a tenant to test against; a decision on which maintained OIDC library (e.g. `socialiteproviders/microsoft` on top of Laravel Socialite) the team is comfortable auditing; a mapping table from Entra tenant/object ID to an `access_grants` row (never trust email alone, per the prompt).
**Acceptance criteria**: authorization-code flow only (no implicit flow); login creates or matches a user by Entra object ID, not email; an unrecognised tenant is rejected before any account is created; existing local-password login keeps working for accounts not yet linked to Entra (no forced cutover); a test asserts that a Entra login for an unmapped user does **not** silently grant any `access_grants` row.

### [2] Authenticated mailbox-to-ticket ingestion
**Prerequisites**: a real mailbox to poll or webhook from; sender-verification policy (SPF/DKIM check plus matching sender to a known user); a duplicate-detection strategy (Message-ID header).
**Acceptance criteria**: an email from an unrecognised sender does not create a ticket silently attributed to a guessed user; a reply to an existing ticket threads onto it (not a new ticket) by matching a reply-to token in the subject/body; attachments are scanned before being accepted (this also unblocks the malware-scanning gap noted in `TEST_REPORT.md`); an auto-reply loop (e.g. an out-of-office bouncing against the ticket-notification address) is detected and does not create runaway tickets - a test simulating 5 auto-replies in a row must show at most 1 resulting ticket/comment.

### [3] Teams notifications
**Prerequisites**: an approved Teams webhook/app registration per company (not one shared global channel - the prompt is explicit that sensitive content must not land in a broad channel).
**Acceptance criteria**: a restricted ticket's Teams notification contains a link only, no ticket content; recipients are resolved the same way email notification recipients are (never broader).

### [4] Intune/asset inventory sync and monitoring alerts
**Prerequisites**: read access to an Intune tenant or equivalent MDM API; a decision on how an incoming device record maps to an existing `assets` row (by serial? by a synced device ID field this schema doesn't have yet).
**Acceptance criteria**: sync is read-only by default (never pushes changes back to Intune); duplicate detection prevents the same physical device being synced into two `assets` rows; a maintenance-window concept suppresses alerts during planned downtime.

### [5] ACC/Forma or other CDE links/connectors
**Prerequisites**: confirmed API access, licence terms, and permission model for the specific CDE product in use - **do not** promise real-time sync before this is confirmed.
**Acceptance criteria** (first increment only): an authorised deep link from a ticket to the relevant CDE folder/item, stored as a plain URL field - explicitly *not* a synchronisation claim.

### [6] Offline draft capture / PWA
**Prerequisites**: a decision on minimum cached data (the prompt is explicit: no full offline project database cache by default) and a conflict-resolution policy for a draft submitted after the user's membership was revoked while offline.
**Acceptance criteria**: explicit pending/sent states visible to the user; a shared-device logout clears all cached drafts; on reconnect, the client re-validates the user's membership/scope before actually submitting a queued draft (a revoked user's queued draft must not silently submit as though they still had access) - this directly extends the idempotency-key limitation noted in `TEST_REPORT.md` (a reload-surviving key would need to live in this same offline-draft store).

### [7] Multilingual forms and knowledge articles
**Prerequisites**: a decision on which languages, and whether translation is human-reviewed or machine (and if machine, that it's disclosed as such).
**Acceptance criteria**: the original-language text is always retained alongside any translation (never overwritten); `knowledge_articles` gains a language field and articles can exist per-language without forcing a 1:1 translation of every article.

### [8] Formal problem/change management
**Prerequisites**: a decision on whether this reuses the `tickets` table (a new `type`) or gets its own table - the current `major_incident_id` self-reference is a reasonable seed for "problem" linking but was deliberately kept lightweight (see `REQUIREMENTS.md` §6).
**Acceptance criteria**: a change record requires an approval and a documented rollback plan before it can move to "scheduled"; a post-change evidence field is required before it can close.

### [9] Optional AI classification/duplicate-suggestion/knowledge-retrieval/draft-reply
**Prerequisites**: a named model provider and a written data-handling statement (what ticket text/attachments are sent, retention on the provider side) before any ticket content is sent anywhere - this is a real decision with data-protection consequences (see `REQUIREMENTS.md` §10 on PDPA), not a technical one.
**Acceptance criteria** (non-negotiable, per the prompt): retrieval respects the requesting user's own `Ticket::scopeVisibleTo()` scope - an AI feature must never see more than the user invoking it could see themselves; a restricted/security ticket is never sent to any AI provider; a draft reply requires explicit human approval before sending, every time; core ticketing continues to work with zero AI configuration (this is already true today - nothing about the current implementation depends on any AI service).

## What "prioritised" means here

If forced to pick three to build next: **N1** (analytics - cheap, high visibility, no new integration risk), **N2** (retention job - a real compliance gap, not a nice-to-have), then **[1] Entra SSO** (the single Phase C item most organisations of this shape will actually ask for first, and the one with the clearest, already-documented acceptance criteria in the BUILD PROMPT itself).
