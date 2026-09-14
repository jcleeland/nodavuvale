# Database migrations

Deploy the application files, then visit **Administration → Database Migrations**.
The administration navigation and account menu flag outstanding migrations. Review
the descriptions and click **Run migration(s)**. This is an administrator-only,
CSRF-protected POST. It creates its own ledger on the first run; merely opening a
page does not change the schema. The database account needs CREATE and ALTER
privileges as well as normal application access.

The runner records version, file checksum, state, administrator, timestamps, and
the last error in `schema_migrations`. It takes a database-scoped advisory lock,
rechecks state under that lock, runs in filename order, and stops at the first
failure. Applied migrations are skipped. A changed or missing recorded file blocks
further execution. Restore the exact original file instead of editing history.

Take a backup before running migrations. MySQL/MariaDB DDL may commit implicitly:
the runner does not promise rollback of an entire migration or batch. Failed and
interrupted runs may already have changed part of the schema. Resolve the error,
then retry; each migration MUST tolerate being restarted after any completed step.
Do not use this web runner for long data conversions exceeding server timeouts.

## Adding a migration

Add a new PHP file under `system/migrations/`, named with a monotonically increasing
version, for example `20261001_001_add_example.php`. Files return an array:

```php
<?php
return [
    'description' => 'Explain the database change and any effect on existing data.',
    'up' => static function (PDO $pdo): void {
        // Inspect information_schema before ALTER; use IF NOT EXISTS where suitable.
        // Execute using this PDO connection. Throw on failure. Never echo output.
    },
];
```

Use bound parameters for data, deterministic operations, and resumable steps.
Definition loading itself must have no side effects. Do not import the baseline
`system/nodavuvale.sql` on an existing site: it contains DROP TABLE statements.
Update the baseline when appropriate for new installs, but let migrations record
their own history, including when a new install already has the desired columns.
Keep completed migration files in every subsequent deployment.

Test pending detection, normal execution, repeated execution, partial failure and
retry, concurrent runs, checksum drift, and upgrades from the previous schema.
There is deliberately no automatic down-migration or rollback button.
