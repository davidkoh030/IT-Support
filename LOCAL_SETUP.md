# Local setup

Two supported paths. **Path A (SQLite) is the one actually run and verified in this session** - every command below was executed against this exact repository during development. Path B (Docker/MySQL) is provided and reviewed for correctness but **not executed** in this session (no Docker daemon was available in that container - see `TECH_STACK.md` §4); treat it as a documented-but-unverified path until someone runs it with Docker available.

## Path A - PHP + SQLite (verified this session)

### Requirements

- PHP 8.3+ (this session used 8.4.19) with `pdo_sqlite`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `fileinfo` extensions (all standard).
- Composer 2.x.
- Node 20+ / npm (this session used Node 22 / npm 10) - only needed to build CSS/JS assets.

### Install

```sh
git clone <this-repository-url> it-support
cd it-support
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
npm install
npm run build
```

### Seed the fictional demo dataset

```sh
php artisan db:seed --force
```

This runs `RoleSeeder` (creates the `administrator` Spatie role) then `DemoDataSeeder` (two companies, one group parent + two legal entities, one HQ site + two project site offices + one precast plant, two Singapore/Malaysia support calendars with holidays, the full SLA-policy and priority-matrix tables, 15 categories, one vendor, 13 named users covering every role, 6 assets, 3 knowledge articles, and 22 tickets covering the acceptance scenarios in `TEST_REPORT.md`). It is safe to re-run after `migrate:fresh` but **not** idempotent against an already-seeded database (it will create duplicates) - always pair it with a fresh migration:

```sh
php artisan migrate:fresh --seed --force
```

### Demo credentials

Every seeded user's password is **`password`**. Emails (all `@demo.test`, none are real addresses):

| Email | Role |
| --- | --- |
| `admin@demo.test` | System administrator |
| `it.manager@demo.test` | IT manager (group-wide scope) |
| `it.agent.sg@demo.test` | IT agent (Singapore company scope) |
| `it.agent.my@demo.test` | IT agent (Malaysia company scope) |
| `pm.mpv@demo.test` | Project manager, Marina Parkview Residences |
| `pm.ilh@demo.test` | Project manager, Iskandar Logistics Hub |
| `auditor@demo.test` | Auditor (time-bound group scope, ~20 days remaining) |
| `requester.mpv@demo.test`, `requester2.mpv@demo.test`, `bim.mpv@demo.test` | Requesters, Marina Parkview |
| `requester.ilh@demo.test` | Requester, Iskandar |
| `vendor@demo.test` | Vendor contact (NetLink Managed Services) |
| `transferred@demo.test` | Requester whose project membership has already expired (for testing access-expiry) |

For a real invited account (not the seeded demo users), an administrator creates it at **Admin → Users & access → Invite user**; the app queues a password-reset link rather than emailing a plaintext password (see `ADMIN_GUIDE.md`). In local dev, that "email" lands in `storage/logs/laravel.log` (`MAIL_MAILER=log`) - open the log and copy the reset link.

### Start

```sh
php artisan serve --port=8000
```

App URL: **http://127.0.0.1:8000**

In a second terminal, start the queue worker (notifications are queued, not sent inline):

```sh
php artisan queue:work
```

In a third terminal (or instead of a real cron in dev), start the scheduler, which fires `sla:scan` every 5 minutes:

```sh
php artisan schedule:work
```

Confirm the scheduler registered correctly at any time with:

```sh
php artisan schedule:list
```

### Stop

`Ctrl+C` in each of the three terminals. Nothing else is left running (SQLite is just a file, no daemon to stop).

### Restart

Same three `php artisan serve` / `queue:work` / `schedule:work` commands - state persists in `database/database.sqlite` between restarts. To confirm a clean restart actually works end-to-end (this was verified in-session), the reliable sequence is:

```sh
php artisan migrate:fresh --seed --force   # optional: reset to a clean demo state
php artisan serve --port=8000 &
php artisan queue:work --stop-when-empty   # drains anything queued, then exits
php artisan schedule:list                  # confirms the scheduler entry is registered
```

### Run the test suite

```sh
php artisan test
```

47 tests, in-memory SQLite, ~2.3s. See `TEST_REPORT.md` for what each covers.

## Path B - Docker Compose + MySQL (documented, not executed this session)

```sh
cp .env.example .env
# edit .env: DB_CONNECTION=mysql, DB_HOST=mysql, DB_DATABASE=it_support,
# DB_USERNAME=it_support, DB_PASSWORD=it_support_dev_password (matches docker-compose.yml)
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app npm install
docker compose exec app npm run build
```

App URL: **http://127.0.0.1:8000**. Mailpit (a local mail catcher UI, optional alternative to the `log` driver) is included at **http://127.0.0.1:8025** - switch `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` in `.env` to use it instead of the log driver. `queue` and `scheduler` containers run `queue:work`/`schedule:work` automatically; no separate terminals needed.

Stop: `docker compose down` (add `-v` to also drop the MySQL data volume - only do this deliberately). Restart: `docker compose up -d`.

**This path has not been run in this session.** If you hit an issue following it, that's expected until someone verifies it against a real Docker install - please report back what broke.
