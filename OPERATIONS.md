# Operations

## Backups and restore

### SQLite (this session's actual local runtime)

**Backup** is a file copy - the entire database is `database/database.sqlite`:

```sh
mkdir -p backups
cp database/database.sqlite "backups/it-support-$(date +%Y%m%d-%H%M%S).sqlite"
```

Private attachments live under `storage/app/private/attachments/` - back that directory up alongside the database (a ticket's attachment rows point at paths under it; the two must be restored together or attachment links break):

```sh
tar -czf "backups/attachments-$(date +%Y%m%d-%H%M%S).tar.gz" storage/app/private/attachments
```

Config that matters for restore: `.env` (not itself sensitive-data-bearing beyond `APP_KEY` - back it up separately from the database dump, never commit it).

**Restore**, verified in this session against a disposable copy of the repository:

```sh
cp backups/it-support-<timestamp>.sqlite database/database.sqlite
tar -xzf backups/attachments-<timestamp>.tar.gz -C .
php artisan config:clear
```

Verification performed (in a disposable `/tmp` copy of the working tree, never against the live session database): created a real ticket attachment, took the `cp`/`tar` backup exactly as documented above, then deleted the ticket (its attachment row cascade-deleted with it, per the `ticket_attachments.ticket_id` foreign key) and removed the file to simulate a disaster. Restoring the snapshot in that isolated copy recovered the ticket (`TCK-000001`, correct summary), the attachment row, and the attachment file with byte-for-byte correct content (`Storage::disk('private')->get($path)` matched what was written before the backup). See `TEST_REPORT.md` for the full transcript.

### MySQL (documented target, not exercised this session - no MySQL server was available in this container)

**Backup**:

```sh
mysqldump --single-transaction -u it_support -p it_support > "backups/it-support-$(date +%Y%m%d-%H%M%S).sql"
```

**Restore**:

```sh
mysql -u it_support -p it_support < backups/it-support-<timestamp>.sql
```

Same attachment-directory backup as above applies regardless of database engine (attachments are always on local disk, not in the database).

### RPO/RTO assumption (demo default, not a contractual promise)

Daily backup (RPO ≤ 24h), restore procedure above targets under 15 minutes for a database of this pilot's size (RTO). Neither number has been load-tested against a production-scale dataset - state that plainly to any stakeholder relying on this document rather than implying it's been proven at scale.

## Queue worker

`QUEUE_CONNECTION=database`. Run:

```sh
php artisan queue:work --tries=3
```

For a real deployment, run this under a process supervisor so it restarts automatically (systemd unit or Supervisor program), e.g. a minimal systemd unit:

```ini
[Unit]
Description=IT Support queue worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/it-support
ExecStart=/usr/bin/php artisan queue:work --tries=3 --sleep=3
Restart=always

[Install]
WantedBy=multi-user.target
```

**Monitoring**: `php artisan queue:failed` lists failed jobs (a `failed_jobs` table migration ships with Laravel's default scaffold and was not removed). There is no alerting wired up in this pilot - a failed-job count check is a reasonable addition for a real deployment, not built here.

## Scheduler

`routes/console.php` registers exactly one scheduled task: `sla:scan`, every 5 minutes, `withoutOverlapping()`. In dev, run:

```sh
php artisan schedule:work
```

In production, add **one** crontab entry (never one entry per command):

```
* * * * * cd /var/www/it-support && php artisan schedule:run >> /dev/null 2>&1
```

Verify registration at any time with `php artisan schedule:list` (verified working in this session - see `TEST_REPORT.md`).

## Retention

`config('itsm.retention_days')` documents intended retention periods (`ticket` 3 years, `audit_log` 7 years, `attachment` 3 years) but **no scheduled job currently enforces them** - this is intentionally listed as not-yet-implemented in `REQUIREMENTS.md` rather than shipped as an unreviewed, irreversible deletion job. Building that job for a real deployment should include a **preview/dry-run mode** (list what would be deleted/anonymised without doing it) before any destructive run, and should respect a "hold" flag on records under legal/audit hold - neither of which exists yet.

## Patching

- **Framework/dependencies**: `composer outdated`, then `composer update <package>` one at a time (never a blanket `composer update` without reviewing the diff), followed by `php artisan test` before deploying.
- **PHP version**: Laravel 13 supports PHP 8.3-8.5 (per `SOURCES.md`); track PHP's own security-support calendar independently of Laravel's.
- Never run with `APP_DEBUG=true` outside local development - this must be set in the deployment's `.env`, it is not enforced by application code. A quick manual check before any non-local deploy: `grep APP_DEBUG .env` should show `false`.

## Environment configuration checklist (before any non-local deployment)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` generated fresh for that environment (`php artisan key:generate`), never reused across environments
- [ ] `DB_*` pointed at a real MySQL instance, not SQLite
- [ ] `MAIL_MAILER` pointed at a real transactional mail provider (not `log`) - and confirm the "no live messages in development" requirement doesn't apply once this flips
- [ ] `SESSION_SECURE_COOKIE=true` if served over HTTPS (it should be)
- [ ] Hosting region and any external processors (mail provider, error tracker, etc.) recorded for the PDPA/data-protection review mentioned in `REQUIREMENTS.md` §10 and `SOURCES.md`
- [ ] A backup taken and its restore procedure tested against *that* environment specifically, not assumed from this document
