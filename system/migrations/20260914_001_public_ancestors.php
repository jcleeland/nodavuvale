<?php

return [
    'description' => 'Add ancestor public-access settings and individual exclusions. Public access starts disabled; existing records are preserved.',
    'up' => static function (PDO $pdo): void {
        $column = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE
            table_schema = DATABASE() AND table_name = 'individuals' AND column_name = 'exclude_from_public'")->fetchColumn();
        if (!$column) {
            $pdo->exec('ALTER TABLE individuals ADD COLUMN exclude_from_public TINYINT(1) NOT NULL DEFAULT 0');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS public_access_settings (
            id TINYINT NOT NULL PRIMARY KEY, enabled TINYINT(1) NOT NULL DEFAULT 0,
            threshold_years SMALLINT NOT NULL DEFAULT 50,
            timezone VARCHAR(64) NOT NULL DEFAULT 'Australia/Sydney',
            updated_at DATETIME NULL, updated_by INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT INTO public_access_settings (id, enabled, threshold_years, timezone)
            SELECT 1, 0, 50, 'Australia/Sydney' WHERE NOT EXISTS (SELECT 1 FROM public_access_settings WHERE id = 1)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS public_access_audit (
            id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, individual_id INT NULL,
            administrator_id INT NOT NULL, action VARCHAR(32) NOT NULL,
            details TEXT NOT NULL, created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
];
