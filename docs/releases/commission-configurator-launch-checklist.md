# Commission Configurator Launch Checklist

> Owner: DevOps Agent  
> Review date: 2026-07-29  
> Scope: Commission Configurator, persistent Quote line commissions, Quote
> commission summary, and backend-driven export integration.

## Release Information

- Feature / change: Commission Configurator and Quote commission snapshots
- Version: release candidate; version/tag not assigned
- Planned date: not assigned
- Release owner: not assigned
- Current verdict: **NO-GO until the open release gates below are closed**

## Open Release Gates

- [ ] Reviewer sign-off is recorded.
- [ ] QA sign-off, regression result, and required coverage of at least 85% are
  recorded.
- [ ] Security sign-off is recorded for module permissions, Quote commission
  field permissions, export authorization, and activity-log visibility.
- [ ] The two migrations are rehearsed against the same MySQL/MariaDB major
  version and collation used in production.
- [ ] Production database version is confirmed to enforce `CHECK` constraints
  (MySQL 8.0.16+ or a compatible MariaDB version).
- [ ] A fresh, restorable database backup is verified before migration.
- [ ] A persistent queue worker is configured, healthy, and restarted as part
  of deployment. No worker process definition exists in this repository.
- [ ] Intended roles receive the new resource permissions and field
  permissions; `permissions:sync` creates permissions but does not assign them
  to existing roles.
- [ ] The release host uses PHP 8.4 and Node.js `^20.19.0`, `^22.12.0`, or
  `>=24.0.0`. Node.js 22 LTS is the recommended release runtime.

## Quality Gates

- [x] Feature specification exists:
  `docs/specs/0066-commission-configurator.md`.
- [x] Architecture decision exists:
  `docs/adr/0017-commission-engine-and-applied-commission-snapshots.md`.
- [x] API and rollout documentation exists:
  `docs/api/0006-commission-configurator-and-quote-integration.md`.
- [x] Targeted backend tests passed: 10 tests, 78 assertions.
- [x] Frontend production build passed with Node.js 22.22.0.
- [ ] Full backend and frontend test suites pass.
- [ ] Backend and frontend coverage are at least 85%.
- [ ] Reviewer sign-off is complete.
- [ ] QA regression sign-off is complete.
- [ ] Responsive behavior and browser console are verified.

## Backend

- [x] The Commission Configurator REST routes and Quote defaults route are
  registered.
- [x] Configuration cache can be built successfully.
- [x] Request validation, policy wiring, resource permissions, and field
  authorization are present.
- [x] Activity-log models and registry entries are present.
- [x] Referenced configurations are protected by both an application-level
  `409` guard and a restrictive foreign key.
- [ ] Full backend suite has no errors or critical warnings.
- [ ] Production logs are free of unexpected errors after the smoke test.

## Frontend

- [x] TypeScript and the Vite production build pass on Node.js 22.22.0.
- [ ] Release environment explicitly selects a supported Node.js version.
- [ ] Browser console has no errors or critical React warnings.
- [ ] Responsive and accessibility checks are complete.
- [ ] Loading, error, and empty states are verified in the deployed build.

## Database and Migrations

### Static review

- [x] Both migrations are additive: they create
  `commission_configurations` and `quote_line_commissions`.
- [x] Dependency order is correct:
  Product Categories/Products/Quote Lines exist before Commission
  Configurations, and Commission Configurations exist before applied
  commissions.
- [x] Reverse order is correct: applied commissions are dropped before
  configurations.
- [x] Product/category deletes are restricted when referenced by a rule.
- [x] Quote-line deletes cascade only to that line's applied commissions.
- [x] Configuration deletes are restricted when an applied snapshot references
  the rule.
- [x] The unique key on `(quote_line_id, recipient_role)` prevents duplicate
  per-role snapshots for a line.
- [x] Resolver and reporting indexes fit within the modern InnoDB 3072-byte
  index-key limit under `utf8mb4`.
- [x] MySQL-only checks cover valid scope ownership, non-negative values, and a
  valid date range.
- [ ] Production engine/version enforcement of all three `CHECK` constraints is
  verified with `SHOW CREATE TABLE`.
- [ ] Query plans for product/category resolution are sampled with realistic
  staging data.

### Isolated validation evidence

The following checks used a temporary SQLite database and did not access
application data:

```bash
DB_CONNECTION=sqlite \
DB_DATABASE=/tmp/qnet-commission-migration-XXXXXX.sqlite \
APP_ENV=testing CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync \
php artisan migrate:fresh --force --no-interaction
```

Result: all migrations passed, including:

```text
2026_07_29_110000_create_commission_configurations_table ... DONE
2026_07_29_110100_create_quote_line_commissions_table ....... DONE
```

The rollback and re-apply check also passed:

```bash
php artisan migrate:rollback --step=2 --force --no-interaction
php artisan migrate --force --no-interaction
```

Result: Laravel dropped `quote_line_commissions` first, then
`commission_configurations`, and recreated both successfully.

SQLite does not execute the migrations' MySQL-specific `ALTER TABLE ... CHECK`
statements. A staging rehearsal on the production database engine remains
mandatory.

## Permissions

- [x] `php artisan permissions:sync` succeeds on an isolated migrated database.
- [x] The command creates the eight expected permissions:
  `viewAny`, `view`, `create`, `update`, `delete`, `export`, `import`, and
  `viewActivity`.
- [x] Permission cache is cleared by the synchronization command.
- [ ] Resource permissions are assigned to the intended production roles.
- [ ] Configurator field permissions are assigned for all twelve fields.
- [ ] Quote field permissions are assigned for `commissions`,
  `commission_recipient`, `commission_type`, `commission_value`, and
  `commission_internal_note`.
- [ ] Least-privilege behavior is smoke-tested using a non-super-admin account.

`commission-configurations.import` is generated by the shared policy catalogue,
but this release does not provide an import workflow. Do not grant it unless a
future workflow requires it.

## Queue-Backed Export

- [x] Configurator export uses the existing private-storage,
  `GenerateExportJob` flow.
- [x] A generation exception changes the export run to `failed` and rethrows
  for failed-job recording.
- [x] Targeted `GenerateExportJob` tests passed.
- [x] Database queue tables already exist in the baseline migration set.
- [ ] Production uses `QUEUE_CONNECTION=database` or another explicitly
  supported asynchronous connection, never `sync`.
- [ ] The process manager continuously runs `php artisan queue:work`.
- [ ] Worker timeout and retry settings exceed the largest expected export
  duration and are consistent with `DB_QUEUE_RETRY_AFTER`.
- [ ] Deployment runs `php artisan queue:restart` after the new backend code is
  active.
- [ ] A Configurator CSV/XLSX export is completed and downloaded in staging.
- [ ] Generated files are confirmed to remain on private storage.

## Build and Runtime

- Backend requirement: PHP `^8.4`.
- Frontend requirement inherited from Vite 8: Node.js `^20.19.0`,
  `^22.12.0`, or `>=24.0.0`.
- CI currently uses PHP 8.4 and Node.js 26.
- Local Node.js 18.16.0 failed before bundling with Vite's unsupported-runtime
  error; Node.js 22.22.0 built successfully.
- The successful build emitted a non-blocking large-chunk warning
  (`index` approximately 3.46 MB / 915 KB gzip) and an ineffective dynamic
  import warning. Track bundle splitting separately; these warnings do not
  block this feature.
- [ ] Add an explicit Node.js version declaration (`engines`, `.nvmrc`, or
  deployment-platform setting) before release.
- [ ] Ensure CI runs the production frontend build; the current workflow runs
  frontend coverage but does not run `npm run build`.

## Deployment Plan

Use a staging environment backed by the same database engine/version as
production before these production steps.

1. Record the release commit/tag and confirm that the backend and frontend
   artifacts were built from that exact revision.
2. Verify the database backup timestamp and complete a restore check in a
   non-production environment.
3. Verify PHP 8.4, a supported Node.js runtime, writable private export storage,
   queue connection, process manager, and `/up` monitoring.
4. Preview pending SQL and confirm that only intended migrations are pending:

   ```bash
   cd backend
   php artisan migrate:status
   php artisan migrate --pretend
   ```

5. Deploy backend dependencies/code while the new navigation remains disabled
   or inaccessible to unassigned roles.
6. Apply additive migrations:

   ```bash
   php artisan migrate --force
   ```

7. Verify the production DDL:

   ```sql
   SHOW CREATE TABLE commission_configurations;
   SHOW CREATE TABLE quote_line_commissions;
   SHOW INDEX FROM commission_configurations;
   SHOW INDEX FROM quote_line_commissions;
   ```

8. Synchronize the permission catalogue, assign intended role permissions and
   field permissions, then verify a least-privilege user:

   ```bash
   php artisan permissions:sync
   ```

9. Refresh framework caches according to the existing deployment procedure:

   ```bash
   php artisan optimize
   ```

10. Activate the new backend revision and restart queue workers:

    ```bash
    php artisan queue:restart
    ```

11. Deploy the frontend artifact built with Node.js 22 LTS or another supported
    version.
12. Run the smoke tests below before broad role assignment/navigation enablement.

Backend-before-frontend ordering is required. A new frontend calling an old
backend would not find `POST /api/quotes/commission-defaults`; the new backend
is additive and remains compatible with the previous frontend.

## Smoke Tests

- [ ] `GET /up` returns `200`.
- [ ] An authorized user can list, create, view, update, and suspend a
  configuration.
- [ ] An unauthorized user receives `403`.
- [ ] Product scope wins over category scope for the same role.
- [ ] A role without a recipient creates no commission.
- [ ] A Quote line persists commissions and returns the four-role summary.
- [ ] A manual override changes only that Quote line.
- [ ] Deleting a referenced configuration returns `409`.
- [ ] Configurator table search, filter, sort, CSV export, and XLSX export work.
- [ ] An export moves from `processing` to `completed`, downloads from private
  storage, and does not create a failed job.
- [ ] Activity logs record Configurator CRUD and Quote commission changes
  without exposing hidden fields.

## Observability

- [x] Application health endpoint exists at `/up`.
- [x] Queue failures are persisted in `failed_jobs`.
- [x] Export runs expose `processing`, `completed`, and `failed` states.
- [ ] Availability monitoring checks `/up`.
- [ ] Alerting covers HTTP 5xx responses on Commission Configurator, Quote, and
  export endpoints.
- [ ] Alerting covers failed queue jobs and a growing default queue.
- [ ] Alerting covers Configurator export runs stuck in `processing`.
- [ ] Logs and database metrics are checked immediately, 15 minutes, and
  24 hours after release.

Suggested post-release checks:

```bash
php artisan queue:failed
php artisan queue:monitor default:100
```

```sql
SELECT COUNT(*) AS stuck_exports
FROM export_runs
WHERE resource = 'commission-configurations'
  AND status = 'processing'
  AND created_at < CURRENT_TIMESTAMP - INTERVAL 15 MINUTE;
```

## Backup and Rollback Strategy

### Preferred application rollback

1. Disable Configurator navigation/role access and stop commission writes.
2. Roll back the frontend artifact.
3. Roll back the backend artifact.
4. Restart queue workers so they load the rolled-back code.
5. Leave the two additive tables in place. The previous application does not
   use them, and retaining them preserves applied commission history.
6. Confirm `/up`, Quote CRUD, logs, and queue health.

### Schema rollback

Schema rollback is destructive because it removes configuration rules and
applied commission snapshots. Perform it only after retention has been approved,
the application has been rolled back, queued jobs are drained/stopped, and a
restorable backup has been verified.

Do not use a generic production `migrate:rollback --step=2` unless migration
status proves these exact files are the last two migrations in the last batch.
Other pending migrations may share the batch. Prefer a reviewed forward cleanup
migration for a later release.

If an emergency schema rollback is explicitly approved, the required dependency
order is:

1. Drop `quote_line_commissions`.
2. Drop `commission_configurations`.

Never drop `commission_configurations` first because applied snapshots hold a
restrictive foreign key to it.

## Validation Evidence

| Command | Result |
|---|---|
| `php artisan route:list --path=api --except-vendor` | Configurator CRUD, Quote defaults, and export routes registered |
| isolated `php artisan migrate:fresh --force` | Passed |
| isolated `php artisan permissions:sync` | Passed; 286 total permissions created on an empty database |
| isolated `php artisan migrate:rollback --step=2` | Passed in correct reverse dependency order |
| isolated `php artisan migrate --force` | Both migrations re-applied |
| isolated `php artisan config:cache` | Passed; cache cleared after validation |
| `composer validate --no-check-publish` | Passed; local Composer 2.7.1 emitted PHP 8.4 deprecation notices |
| targeted Pest command | Passed; 10 tests, 78 assertions |
| `npm run build` with Node.js 18.16.0 | Failed as expected: unsupported Vite runtime |
| `npm run build` with Node.js 22.22.0 | Passed with non-blocking bundle warnings |

Targeted backend test command:

```bash
php artisan test \
  tests/Feature/CommissionConfigurations \
  tests/Unit/Commissions \
  tests/Feature/Exports/GenerateExportJobTest.php \
  --compact
```

## Sign-Off

- [ ] Reviewer
- [ ] QA
- [ ] Security
- [ ] DevOps
- [ ] Release owner

