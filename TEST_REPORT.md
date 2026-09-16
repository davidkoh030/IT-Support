# Test report

Run this session: `php artisan test` → **47 passed, 0 failed, 135 assertions, ~2.3s** (in-memory SQLite, `php artisan test` output captured verbatim: `{"tool":"phpunit","result":"passed","tests":47,"passed":47,"assertions":135,...}`). Plus the manual/browser verification below, all performed in this session against the actual running app (`php artisan serve` on port 8123/8000), not mocked.

Legend: **Automated** = a PHPUnit test in `tests/Feature/` asserts this, re-runnable with `php artisan test`. **Manual** = exercised by hand this session (curl or a real headless-browser screenshot) with the exact evidence noted; re-running it isn't automated. **Blocked** = could not be exercised in this environment, with the specific blocker stated. **Not implemented** = honestly absent, cross-referenced to `REQUIREMENTS.md`/`ROADMAP.md`.

## Acceptance criteria (BUILD PROMPT section 13)

### 1. Site-wide internet failure receives the configured priority and correct calendar/escalation treatment

**Automated** + **Manual**. `PriorityAndSlaTest::test_site_wide_outage_is_priority_p1` asserts impact=high/urgency=high → P1 via the live priority-matrix table (not a hard-coded return value). Manually verified end-to-end against the seeded major-incident ticket `TCK-000001` (screenshot delivered this session): P1, SLA clocks computed from the Singapore support calendar, vendor case attached, assigned and transitioned to `in_progress` with a full audit history. Calendar correctness (skips weekends/holidays) is separately proven in `PriorityAndSlaTest::test_sla_target_skips_weekends_and_uses_business_hours` and `..._skips_configured_holiday`, with hand-verified expected timestamps (see the test file's comments for the arithmetic), not just self-consistency with the code under test.

### 2. Routine plotter issue reportable from a mobile viewport using asset context and an attachment

**Automated** + **Manual**. `TicketSubmissionTest::test_mobile_report_with_asset_context_and_attachment_creates_ticket_with_attachment` drives the actual QR-entry route (`GET /report/asset/{tag}`, asserts the authorised redirect with asset/site prefill) then submits the ticket with a real uploaded fake image and asserts the ticket-asset link and a stored, existing attachment file. **Manual**: a real Chromium (Playwright) session at a 390x844 (iPhone-class) viewport rendered `/tickets/create` and `/dashboard`; screenshots delivered to the user this session show single-column layout, large touch targets (full-width buttons/selects), and a readable guided form. Desktop viewport (1440x900) checked the same routes plus a ticket detail page.

### 3. BIM licence problem captures an imminent deadline without automatically bypassing priority rules

**Automated**. `PriorityAndSlaTest::test_bim_licence_deadline_does_not_bypass_matrix_derived_priority`: impact=medium/urgency=medium with a deadline supplied still resolves to P3 (the matrix value for that combination), and the deadline is stored (visible to IT) but does not influence the calculated priority. This mirrors the seeded demo ticket (`TCK-000004`, Revit licence before a tender deadline).

### 4. A requester cannot view another user's ticket by URL; a PM/vendor/auditor cannot cross their scope via search, exports, attachments, or notifications

**Automated**. `TicketAuthorizationTest`: `test_a_requester_cannot_view_another_users_ticket_by_url` (403), `test_a_vendor_can_only_see_assigned_or_shared_tickets` (403 outside scope, 200 once assigned or explicitly shared), `test_auditor_scope_is_read_only_and_time_bound` (403 once expired, 200 while active, and a 403 on `transition` proving "read-only" is enforced server-side, not just hidden in the UI). `CsvExportTest::test_export_only_contains_tickets_the_user_can_see...` proves the export path specifically (not just the show route) is scoped, using the exact same `Ticket::scopeVisibleTo()` query as the index and dashboard. Attachment-download scoping: **Manual** - confirmed a user unrelated to a ticket's company/project gets 403 on `GET /attachments/{id}`, while the ticket's own IT agent gets 200 (session transcript; also structurally guaranteed by `TicketAttachmentController` re-running the parent ticket's policy, which the automated suite exercises indirectly via `test_internal_notes_are_hidden_from_the_requester`). Notification scoping: **Manual** - notifications are addressed to a single specific `User` model instance (`$agent->notify(...)`, `$ticket->requester->notify(...)`), so there is no query surface where another user's ticket data could leak into a notification; not covered by a dedicated automated test.

### 5. Internal notes and restricted security tickets remain private even to ordinary project managers

**Automated**. `TicketAuthorizationTest::test_project_manager_cannot_view_a_restricted_ticket_in_their_own_project_scope` (403, with a same-PM/same-project non-restricted control case asserting 200 to prove it's the `restricted` flag doing the work, not a broader scope bug) and `test_it_agent_can_view_restricted_ticket_in_their_company_scope` (200 - restricted tickets are still workable by IT). `test_internal_notes_are_hidden_from_the_requester` asserts an internal note's text is absent from the requester's rendered page and present on the agent's. **Manual** confirmation this session against the real seeded restricted tickets (`TCK-000005` phishing, `TCK-000006` HR-confidential): PM got 403, IT agent got 200, admin got 200 - session transcript captured.

### 6. Project transfer and membership expiry revoke the former access scope; overdue external-system revocation tasks remain visible until evidence is recorded

**Automated**. `TicketAuthorizationTest::test_project_transfer_and_membership_expiry_revokes_access` (an active grant becomes inactive the moment its `expiry_date` passes, and `hasScopedRole()` reflects that immediately - no separate "apply expiry" job needed since it's evaluated live on every check) and `test_external_access_overdue_revocation_remains_visible_until_evidence_recorded` (`isOverdueForRevocation()` is true until `revoked_at`+`revocation_evidence` are both set). **Manual**: the seeded `transferred@demo.test` user (expired 15 days ago) and the IT-manager dashboard's "Overdue external access revocations" widget (screenshot delivered this session, showing `TCK-000012` / Jonathan Wee, expired 3 days ago, not yet revoked) both demonstrate this against real seeded data, not just the unit-level fixture.

### 7. Access/procurement approval rejects self-approval and unauthorised transitions; a rejected request cannot be fulfilled through a direct endpoint

**Automated**. `TicketAuthorizationTest::test_self_approval_is_rejected_even_via_direct_endpoint` (403, decision stays `pending`), `test_unauthorised_approver_cannot_decide_and_rejected_request_cannot_be_resolved` (a bystander gets 403; the real approver's rejection sets `approval_state = rejected`; a subsequent attempt to transition that same ticket to `resolved` via the transition endpoint returns 422, not 200). **Manual**: the non-self-approval happy path (a real approver approving a real seeded request) was also exercised by hand this session (`TCK-000011`, `pm.mpv@demo.test` approving) with the resulting `approval_state` checked directly in the database.

### 8. SLA calculations cover weekends, holidays, after-hours submissions, requester waiting, vendor waiting, reopening, and policy changes; re-running a worker does not duplicate escalation messages

**Automated**, all in `PriorityAndSlaTest` and `SlaScanCommandTest`: weekend-skipping and holiday-skipping (hand-verified arithmetic, see test comments), `waiting_requester` pauses the restoration clock while `waiting_vendor` does not, reopening a resolved ticket increments `reopen_count` without deleting any prior `ticket_events` row, and `sla:scan` run three times in a row sends exactly one breach notification (`Notification::assertSentToTimes($agent, SlaBreachWarning::class, 1)`). **Manual**: forced a real seeded clock into the past, ran `php artisan sla:scan` twice, confirmed "1 breach notification" on the first run and "0" on the second (dedup via `breached_notified_at`) - session transcript. "After-hours submission" specifically: covered by the same `addBusinessMinutes()` logic the weekend/holiday tests exercise (a submission at 16:00 with a 4-hour target rolling into the next business day *is* the after-hours case), not a separately named test.

### 9. A repeated submission after a network error creates exactly one ticket

**Automated**. `TicketSubmissionTest::test_repeated_submission_after_a_network_error_creates_exactly_one_ticket`: identical idempotency key posted twice, both requests redirect to the same ticket, `Ticket::where('idempotency_key', ...)->count()` is 1. **Manual**: the identical scenario was also run by hand this session via two separate `curl` POSTs with the same key against the live server, confirming the same ticket URL both times and a database count of 1 - documented in-session before the automated test was written, so the server-side guarantee was proven twice, independently.

**Caveat stated plainly** (see `REQUIREMENTS.md` §5): the idempotency key is generated client-side by Alpine.js when the page loads and is stable across a resubmission of that same page load, but a full browser *reload* between attempts would generate a new key and thus a new ticket. The server-side "same key ⇒ same ticket" guarantee tested above is real and unconditional; the client-side "the same key survives every kind of retry" guarantee is not - true reload-survivable idempotency is Phase C (see `ROADMAP.md`).

### 10. Site demobilisation tracks asset returns and outstanding actions without deleting project history

**Automated**. `AssetAndDemobilisationTest::test_demobilisation_checklist_tracks_outstanding_actions_and_asset_events_are_never_deleted`: seeds a demobilisation ticket with the real template checklist, returns an asset (asserting a real `AssetEvent` row appended, not an overwrite), completes exactly one checklist task via the actual controller endpoint, and asserts the remaining tasks stay `pending` (visible, not hidden or auto-completed) while the project's `lifecycle_stage` and the asset's custody history both survive untouched.

### 11. Backup restoration recovers linked tickets and private attachments in an isolated test environment

**Manual**, exercised twice this session (the first attempt used a flawed test-harness `cp -r` that nested directories - caught, discarded, and redone correctly rather than reported as a false pass). Final run: created a real ticket attachment, backed it up exactly per `OPERATIONS.md` (`cp` the SQLite file + `tar -czf` the attachments directory), copied the entire working tree to a disposable `/tmp` directory, deleted the ticket (its attachment row cascade-deleted via the FK) and its file to simulate a disaster, then restored the backup in that isolated copy and confirmed: the ticket reappeared with its correct ticket number and summary, the attachment row reappeared, the file reappeared on disk, and its content matched byte-for-byte what was written before the backup. Not automated (backup/restore is a filesystem-level operation outside PHPUnit's normal scope; it could be scripted as a shell test in CI - listed as a small `ROADMAP.md` follow-up).

### 12. Clean setup from documented commands succeeds; application, scheduler, and queue worker restart correctly

**Manual**, exercised this session exactly as `LOCAL_SETUP.md` documents: deleted `database/database.sqlite`, recreated it empty, ran `php artisan migrate --force` (12 migrations, all `DONE`), `php artisan db:seed --force` (both seeders `DONE`), started `php artisan serve` (confirmed `200` on `/login`), ran `php artisan queue:work --stop-when-empty` (drained the queued notification jobs from seeding, e.g. two `TicketAssigned` jobs processed, then `Worker STOPPED Queue empty`), and confirmed `php artisan schedule:list` shows the `sla:scan` entry with a correct "next due" time. This is the literal command sequence in `LOCAL_SETUP.md`, not a paraphrase.

## Additional checks beyond the twelve

- **Group-hierarchy access-scope bug found and fixed during this session's manual testing**: an IT manager granted access at the group-company level initially got a 403/404 on a subsidiary company's ticket, because `hasScopedRole()` compared `scope_id` for exact equality. Fixed with `Company::ancestorChain()`/`idsIncludingDescendants()` so a group-level grant covers every subsidiary; re-verified manually (200) and covered going forward by the same `TicketAuthorizationTest` suite (the `it_agent`/`it_manager` company-scope tests would have caught a regression here, though no test names the group/subsidiary case specifically - a targeted regression test for this exact scenario is a reasonable small addition, not yet added).
- **Formula-injection protection in CSV export**: `CsvExportTest` asserts a summary starting with `=` is exported with a neutralising leading apostrophe.
- **Disallowed attachment types rejected**: `TicketSubmissionTest::test_disallowed_file_type_is_rejected` (a `.exe` upload is rejected by Laravel's `mimes:` validation rule before ever touching storage).
- **Form validation preserves input on failure**: `TicketSubmissionTest::test_form_validation_errors_preserve_submitted_input` (standard Laravel redirect-back-with-errors; no false "submitted" state is possible since the ticket row is never created on a validation failure - asserted via `Ticket::count() === 0`).
- **Notifications actually queue and land** (section 11): confirmed this session that seeding produced 34 queued `jobs` rows (not 0 in the `notifications` table until processed), running `queue:work` processed all 34, and 17 landed as `notifications` table rows plus corresponding entries in `storage/logs/laravel.log` (the `log` mail driver) - no live email was sent anywhere.
- **Code style**: `./vendor/bin/pint` was run and its fixes applied; the full test suite was re-run afterward and still passes (47/47), confirming the formatting pass didn't change behaviour.

## What is explicitly not covered

See `REQUIREMENTS.md` for the full per-requirement breakdown. The largest gaps, stated plainly:

- IT-manager analytics (volume/category trends, median/p90 response times, ageing, repeat-incident rates) - dashboard has only "unassigned count" and "at-risk" tiles today; the underlying data exists, the reporting views don't yet.
- Retention/anonymisation scheduled job - documented intent (`config('itsm.retention_days')`) but no job runs it.
- Malware scanning on uploads - an obvious extension point exists in `TicketController::storeAttachments`, nothing is wired to it.
- MySQL/Docker path - reviewed for correctness, not executed (no Docker daemon in this environment).
- All of Phase C (section 12) - backlog only, see `ROADMAP.md`.

No mocked integration was ever substituted for a claim of "live verification" - every "Manual" item above was run against the actual application code on the actual running server in this session, and every "Automated" item is a real, currently-passing PHPUnit test in this repository.
