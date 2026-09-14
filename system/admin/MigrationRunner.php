<?php

/** Versioned, forward-only migrations. DDL is not transactionally rolled back by MySQL/MariaDB. */
final class MigrationRunner
{
    public function __construct(private PDO $pdo, private string $directory)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function definitions(): array
    {
        $definitions = [];
        foreach (glob($this->directory . '/*.php') ?: [] as $path) {
            $id = basename($path, '.php');
            if (!preg_match('/^\d{8}_\d{3}_[a-z0-9_]+$/D', $id)) {
                throw new RuntimeException('Invalid migration filename: ' . $id);
            }
            $definition = require $path;
            if (!is_array($definition) || !is_string($definition['description'] ?? null)
                || !is_callable($definition['up'] ?? null)) {
                throw new RuntimeException('Invalid migration definition: ' . $id);
            }
            $definitions[$id] = $definition + ['checksum' => hash_file('sha256', $path)];
        }
        ksort($definitions, SORT_STRING);
        return $definitions;
    }

    private function ledgerExists(): bool
    {
        return (bool) $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'")->fetchColumn();
    }

    /** Inspecting migrations never creates tables or runs a migration. */
    public function status(): array
    {
        $recorded = [];
        if ($this->ledgerExists()) {
            foreach ($this->pdo->query('SELECT * FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $recorded[$row['version']] = $row;
            }
        }
        $result = [];
        foreach ($this->definitions() as $id => $definition) {
            $row = $recorded[$id] ?? [];
            $state = $row['status'] ?? 'pending';
            if (isset($row['checksum']) && !hash_equals($row['checksum'], $definition['checksum'])) {
                $state = 'changed';
            }
            $result[$id] = [
                'description' => $definition['description'], 'status' => $state,
                'last_error' => $row['last_error'] ?? null, 'applied_at' => $row['applied_at'] ?? null,
                'applied_by' => $row['applied_by'] ?? null,
            ];
            unset($recorded[$id]);
        }
        foreach ($recorded as $id => $row) {
            $result[$id] = ['description' => 'Migration file is missing', 'status' => 'missing',
                'last_error' => null, 'applied_at' => $row['applied_at'], 'applied_by' => $row['applied_by']];
        }
        return $result;
    }

    public function runPending(int $administratorId): array
    {
        $name = 'nv_migrations_' . substr(hash('sha256', (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 0)');
        $lock->execute([$name]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new RuntimeException('Another migration run is in progress. Refresh this page shortly.');
        }
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(191) NOT NULL PRIMARY KEY, checksum CHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL, started_at DATETIME NOT NULL,
                applied_at DATETIME NULL, applied_by INT NULL, last_error TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $states = $this->status();
            foreach ($states as $id => $state) {
                if (in_array($state['status'], ['changed', 'missing'], true)) {
                    throw new RuntimeException('Restore the original migration file before continuing: ' . $id);
                }
            }
            $completed = [];
            foreach ($this->definitions() as $id => $definition) {
                if ($states[$id]['status'] === 'applied') {
                    continue;
                }
                $start = $this->pdo->prepare("INSERT INTO schema_migrations
                    (version, checksum, status, started_at, applied_by)
                    VALUES (?, ?, 'running', UTC_TIMESTAMP(), ?)
                    ON DUPLICATE KEY UPDATE status = 'running', started_at = UTC_TIMESTAMP(),
                    applied_by = VALUES(applied_by), last_error = NULL");
                $start->execute([$id, $definition['checksum'], $administratorId]);
                try {
                    ($definition['up'])($this->pdo);
                    $done = $this->pdo->prepare("UPDATE schema_migrations SET status = 'applied',
                        applied_at = UTC_TIMESTAMP(), last_error = NULL WHERE version = ?");
                    $done->execute([$id]);
                    $completed[] = $id;
                } catch (Throwable $error) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    $failed = $this->pdo->prepare("UPDATE schema_migrations SET status = 'failed', last_error = ? WHERE version = ?");
                    $failed->execute([substr($error->getMessage(), 0, 4000), $id]);
                    throw new RuntimeException('Migration failed: ' . $id . '. Review the error below before retrying.', 0, $error);
                }
            }
            return $completed;
        } finally {
            $release = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$name]);
        }
    }
}
