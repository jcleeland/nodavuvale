<?php
return [
    'description' => 'Allow administrators to publish discussion articles and their images publicly. All existing discussions remain private.',
    'up' => static function (PDO $pdo): void {
        foreach (['is_public' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'public_published_at' => 'DATETIME NULL', 'public_published_by' => 'INT NULL'] as $name => $definition) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE
                table_schema=DATABASE() AND table_name='discussions' AND column_name=?");
            $check->execute([$name]);
            if (!$check->fetchColumn()) { $pdo->exec("ALTER TABLE discussions ADD COLUMN `$name` $definition"); }
        }
    },
];
