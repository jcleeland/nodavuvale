<?php

return [
    'description' => 'Increase discussion content capacity from TEXT to MEDIUMTEXT for longer formatted posts. Existing discussions are preserved.',
    'up' => static function (PDO $pdo): void {
        $type = $pdo->query("SELECT DATA_TYPE FROM information_schema.columns WHERE
            table_schema = DATABASE() AND table_name = 'discussions' AND column_name = 'content'")->fetchColumn();
        // Also tolerate sites that have already increased the capacity manually.
        if (in_array($type, ['mediumtext', 'longtext'], true)) { return; }
        if (!in_array($type, ['tinytext', 'text'], true)) {
            throw new RuntimeException('Expected a TEXT discussions.content column; review the existing schema before retrying.');
        }
        // Reuse the server's column declaration to preserve charset, collation,
        // nullability, defaults and comments; change only the text capacity.
        $definition = $pdo->query('SHOW CREATE TABLE discussions')->fetch(PDO::FETCH_NUM)[1];
        if (!preg_match('/^\s*`content`\s+(?:tinytext|text)\b([^\r\n]*)/mi', $definition, $match)) {
            throw new RuntimeException('Unable to read the discussions.content definition.');
        }
        $attributes = rtrim(trim($match[1]), ',');
        $pdo->exec('ALTER TABLE discussions MODIFY COLUMN `content` MEDIUMTEXT ' . $attributes);
    },
];
