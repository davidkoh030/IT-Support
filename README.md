# Construction IT Support (pilot)

A configurable IT service-management pilot for a construction group with headquarters, temporary site offices, multiple projects, subcontractors, consultants, warehouses and precast plants - built as an independent Laravel rebuild per the assessment in `MIGRATION_PLAN.md` (the legacy repository this replaces has no reuse licence; see there for why).

**Start here:** `LOCAL_SETUP.md` - exact commands to run this locally (verified this session), demo login credentials, and how to run the test suite.

## Documentation index

| Document | What's in it |
| --- | --- |
| [`TECH_STACK.md`](TECH_STACK.md) | Observed legacy stack, licence findings, and the stack chosen for this pilot, with reasons. |
| [`REQUIREMENTS.md`](REQUIREMENTS.md) | Every BUILD PROMPT requirement, section by section, marked Implemented / Partial / Deferred / Assumption. |
| [`ARCHITECTURE.md`](ARCHITECTURE.md) | Data model, the ticket lifecycle state machine, SLA clock mechanics, authorization boundary, background jobs. |
| [`LOCAL_SETUP.md`](LOCAL_SETUP.md) | Install / start / stop / restart, demo credentials, running tests. |
| [`ADMIN_GUIDE.md`](ADMIN_GUIDE.md) | How to configure companies, projects, sites, calendars, categories, SLA policies, users/access, assets, request templates. |
| [`MIGRATION_PLAN.md`](MIGRATION_PLAN.md) | Why this is an independent rebuild, what was inspected in the legacy repo, field mapping, and the process for a future real data import. |
| [`OPERATIONS.md`](OPERATIONS.md) | Backup/restore (verified this session), queue/scheduler operation, retention, patching. |
| [`TEST_REPORT.md`](TEST_REPORT.md) | Per-acceptance-criterion evidence: automated test, manual verification, or an honestly-stated gap. |
| [`ROADMAP.md`](ROADMAP.md) | Prioritised Phase C backlog with prerequisites and acceptance criteria per item. |
| [`SOURCES.md`](SOURCES.md) | External sources consulted, what each one specifically informed. |

## Status

Core pilot (Phase A + B of the BUILD PROMPT): implemented, seeded with a fictional demo dataset, and tested - 47 automated tests passing plus the manual/browser verification in `TEST_REPORT.md`. Not production-deployed, not certified against any compliance standard, and not published anywhere beyond this repository.
