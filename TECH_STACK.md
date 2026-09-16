# Tech stack

## 1. Observed: the legacy repository

Inspected: `https://github.com/ShaneCunn/Support-Ticket-system.git`, commit `c8c469b8388ab585e395548481d5fc24ae3f4475` (17 Oct 2018), application under `support/`.

| Layer | Declared constraint (`support/composer.json`) | Actually locked (`composer.lock`) |
| --- | --- | --- |
| PHP | `>=7.0.0` | n/a (runtime, not locked) |
| Laravel | `5.5.*` | `v5.5.25` |
| fideloper/proxy | `~3.3` | `3.3.4` |
| intervention/image | `^2.4` | `2.4.1` |
| laravelcollective/html | `^5.4.0` | `v5.5.1` |
| mews/purifier | `^2.0` | `2.0.9` |
| yajra/laravel-datatables-oracle | `^8.3` | `v8.3.2` |
| phpunit (dev) | `~6.0` | `6.5.4` |

Frontend (`support/package.json`, devDependencies only): Vue `^2.5.7`, `bootstrap-sass ^3.3.7` (Bootstrap 3), jQuery `^3.2`, `laravel-mix ^1.0`, axios `^0.17`. TinyMCE 4.x and DataTables are **not** npm-managed: TinyMCE is vendored as static files under `support/public/js/tinymce/`, and DataTables is pulled from two different CDN URLs (`1.10.16` and `1.10.7`) in different Blade views.

Storage: MySQL only (`.env.example` hard-codes `DB_CONNECTION=mysql`); mail via SMTP/Mailtrap; Redis/Pusher config present but unused (all drivers default to `file`/`log`/`sync`).

**A minimum PHP version does not prove modern-PHP compatibility.** Laravel 5.5 reached end of life (no more security fixes) years ago, and its declared `>=7.0.0` constraint says nothing about behaviour under PHP 8.x - several of its dependencies (fideloper/proxy, laravelcollective/html) are themselves unmaintained and were never made PHP 8-compatible. Running this code base as-is on a current PHP is not a supported configuration.

## 2. Licence status (see also `MIGRATION_PLAN.md` §1)

- Root `composer.json`: `"license": "proprietary"`, author Shane Cunningham. **No `LICENSE`/`LICENCE`/`COPYING` file exists anywhere in the repository.**
- Nested `support/composer.json`: `"license": "MIT"` - but this is Laravel's own stock `laravel/laravel` skeleton manifest (`"name": "laravel/laravel"`), not a statement about the application code that was added on top of it.
- The only license-named file in the tree is `support/public/js/tinymce/license.txt` (TinyMCE's own bundled LGPL 2.1 text for that vendored asset).

**Conclusion:** there is no reuse grant for the application code. This build does **not** copy, vendor, or adapt any code from the legacy repository. It is an independent implementation from the BUILD PROMPT's functional specification, informed only by *structural* observations (schema/route/role shape) gathered for `MIGRATION_PLAN.md`. See `MIGRATION_PLAN.md` for what was inspected and why nothing was copied.

## 3. Selected: this pilot

| Layer | Choice | Why |
| --- | --- | --- |
| Language | PHP 8.4.19 (container-provided) | Currently security-supported; satisfies Laravel 13's `^8.3` requirement. |
| Framework | Laravel 13.32.0 | Latest stable at implementation time (`composer create-project laravel/laravel`, no version pin, resolved to `^13.17`). Per Laravel's release policy, Laravel 13 (released ~17 Mar 2026) receives bug fixes for 18 months and security fixes for 2 years from release - see `SOURCES.md`. |
| Auth scaffolding | Laravel Breeze (Blade stack) | Minimal, first-party, no SPA build complexity; matches the prompt's "maintainable Laravel monolith, Blade with modest interactive components" guidance. |
| Roles | `spatie/laravel-permission` (`administrator` only) + a custom `access_grants` table for every scoped role | Spatie's roles are global; the spec needs per-company/per-project scoping with effective/expiry dates, which a flat role table can't express. See `ARCHITECTURE.md`. |
| Frontend | Blade + Tailwind CSS 4 + Alpine.js (Vite) | Already the Laravel 13 default scaffold; "modest interactive components," not a separate SPA. |
| Database (local pilot) | **SQLite** (`database/database.sqlite`) | See §4 below - this is a deliberate, documented deviation from the prompt's MySQL preference, forced by this environment's tooling. |
| Database (documented target) | MySQL 8.x / MariaDB 10.11+ | What `config/database.php`'s `mysql` connection and `docker-compose.yml` (see `LOCAL_SETUP.md`) are written for; not exercised in this session. |
| Queue | Database driver (`QUEUE_CONNECTION=database`), `laravel/framework`'s queue worker | No Redis available in this environment; database queue is adequate for pilot volume and needs no extra service. |
| Mail | `log` driver in `.env`/`.env.example` | Section 11's "no live messages in development" requirement - notifications are queued and land in `storage/logs/laravel.log`, never sent externally. |
| Images | `intervention/image` v3 | Maintained fork/major-version successor to the legacy app's `intervention/image` v2; used nowhere yet in this pilot (attachments are stored as-is) but pinned for the roadmap's photo-compression work. |
| File storage | Local disk, split into a public `storage` disk (unused by tickets) and a **private** `private` disk (`storage/app/private/attachments`, `serve => false`) | Ticket/comment attachments must never be web-addressable by guessing a URL (section 10). |

## 4. Deviations from the prompt's defaults, and why

- **MySQL → SQLite for the local runtime.** This container has PHP's `pdo_mysql` extension but no `mysqld`/`mysql` binary and no Docker daemon (`docker ps` fails: "no such file or directory" on the daemon socket). SQLite (`pdo_sqlite`, bundled) is the only database actually runnable here. `config/database.php`'s `mysql` connection is left fully configured and unedited from the Laravel default, and `docker-compose.yml` (see `LOCAL_SETUP.md`) provisions real MySQL for anyone with Docker - but that path was **not executed** in this session; say so plainly rather than claim a MySQL run that didn't happen.
- **No Docker Compose run.** Same root cause (no Docker daemon in this container). The compose file is provided and reviewed for correctness, not verified by an actual `docker compose up`.
- **Composer plugins disabled** (`COMPOSER_ALLOW_SUPERUSER=1` used to work around running as root in a sandbox) - a normal developer machine won't hit this; noted in `LOCAL_SETUP.md`.

## 5. What was force-installed and what wasn't

Nothing was installed by disabling security checks, ignoring `composer.json` platform requirements, or broadly upgrading dependencies past what `composer.json` declares. `composer require` was used for `laravel/breeze`, `spatie/laravel-permission`, and `intervention/image` with explicit version constraints; `composer.lock` reflects normal dependency resolution against Packagist, no `--ignore-platform-reqs`.
