<?php

/**
 * Builds a read-only database/upload integrity report and performs a deliberately
 * small set of recoverable, revalidated cleanup actions.
 *
 * The application currently contains MyISAM tables, so this service does not
 * pretend that a transaction can make multi-table cleanup atomic. Every action
 * is instead exported to a recovery manifest before the first mutation.
 */
final class DatabaseCleanupService
{
    public const SCAN_TOKEN_TTL = 600;
    public const FILE_GRACE_PERIOD = 86400;

    private object $db;
    private string $projectRoot;
    private string $archiveRoot;

    public function __construct(object $db, string $projectRoot, ?string $archiveRoot = null)
    {
        $resolvedRoot = realpath($projectRoot);
        if ($resolvedRoot === false) {
            throw new InvalidArgumentException('The NodaVuvale project root could not be resolved.');
        }

        $this->db = $db;
        $this->projectRoot = rtrim($resolvedRoot, DIRECTORY_SEPARATOR);
        $defaultArchiveRoot = dirname($this->projectRoot, 2)
            . DIRECTORY_SEPARATOR . basename($this->projectRoot) . '-cleanup-archive';
        $this->archiveRoot = $archiveRoot
            ?? (defined('NODAVUVALE_CLEANUP_ARCHIVE_DIR')
                ? (string) constant('NODAVUVALE_CLEANUP_ARCHIVE_DIR')
                : $defaultArchiveRoot);
    }

    public function getArchiveRoot(): string
    {
        return $this->archiveRoot;
    }

    /**
     * Return all current cleanup candidates. This method never changes domain
     * data or files.
     */
    public function scan(): array
    {
        $generatedAt = time();
        $sections = [];

        $sections[] = $this->section(
            'orphan_items',
            'Items without individual links',
            'These item rows have no matching item_links row, so they are not attached to any individual in the family tree.',
            'Not visible or usable: Facts, Events, Media, and Latest Updates retrieve items through item_links. An unlinked item with no group, file, comment, or reaction dependencies cannot be reached through the application.',
            'Remove every row for which “Remove orphan item” is offered. Those rows contain no usable system information. A row marked review-only still has dependent data and must be repaired or reviewed first.',
            $this->scanOrphanItems()
        );
        $sections[] = $this->section(
            'dangling_item_links',
            'Item links with missing items',
            'These item_links rows point to item IDs that no longer exist, so the link cannot resolve to any fact, event, or other item content.',
            'Not visible or usable: the missing item prevents the link from producing content anywhere in the application. The row is only dead relationship metadata and may distort integrity counts.',
            'Remove all listed broken links. Their target item is already absent, so removing the link does not remove any remaining fact, event, file, or user content.',
            $this->scanDanglingItemLinks()
        );
        $sections[] = $this->section(
            'duplicate_item_links',
            'Exact duplicate item links',
            'Two or more item_links rows connect the same surviving item to the same individual. Only the excess rows are listed; the oldest matching link is retained.',
            'The underlying item remains visible and usable through the retained link. Excess links contain no unique information and can cause duplicate display, counting, or processing of the same item.',
            'Remove every listed duplicate. The cleanup removes only the excess link row; it preserves the item, its files and interactions, and the oldest valid link.',
            $this->scanDuplicateItemLinks()
        );
        $sections[] = $this->section(
            'item_group_integrity',
            'Item group and interaction integrity',
            'This section combines empty item groups, items whose group definition is missing, and comments or reactions whose item and group are both missing.',
            'Empty groups are not visible and have no effect. An item missing its group may appear incomplete or lose its event grouping. Detached comments and reactions are not visible, but they still contain user-generated information.',
            'Remove empty groups when the action is offered. Do not automatically remove review-only items, comments, or reactions; reconstruct their group/item where possible, or make a deliberate content decision after reviewing the recorded IDs.',
            $this->scanItemGroupIntegrity()
        );
        $sections[] = $this->section(
            'unlinked_file_records',
            'File records without file links',
            'These files rows have no file_links row connecting the upload to an individual, item, or standalone media entry. The scanner also checks whether the same path is used elsewhere before offering an action.',
            'Normally not visible or usable in the application: individual Media views and Latest Updates require file_links. A row is made review-only if its path is still used by another file record, discussion, avatar, item text, or site setting.',
            'There is currently no tool for attaching an existing unlinked file record to an individual. If the upload is valuable, open or download it from the Path link, manually upload that copy on the correct individual’s page, and confirm the new upload works before quarantining this record. Otherwise quarantine every actionable row; the original file and a recovery manifest are retained outside the web tree.',
            $this->scanUnlinkedFileRecords($generatedAt)
        );
        $sections[] = $this->section(
            'broken_file_links',
            'Broken and duplicate file links',
            'This section identifies links whose file record is missing, valid files attached to missing items, and exact duplicate links to the same file, individual, and item.',
            'A link with no file record is invisible and unusable. A valid file attached to a missing item cannot appear as part of that fact/event, but can still be useful as standalone media. Duplicate links may make the same upload appear or be processed more than once.',
            'Remove links whose file record is missing and remove excess duplicates. When a valid file has only lost its item, use “Preserve as standalone” so the upload remains available to the individual instead of deleting it.',
            $this->scanBrokenFileLinks()
        );
        $sections[] = $this->section(
            'stale_key_images',
            'Key image metadata without a file',
            'These Key Image item rows are attached to individuals but have no file_links/files record capable of supplying an image.',
            'The metadata itself is not visible or useful as a profile picture. Profile and family views require a real linked image and will instead show another available image or the default avatar.',
            'Remove every actionable stale Key Image item, then upload or select the intended profile image if one is required. Review-only rows retain grouped or user-generated dependencies and should be examined before removal.',
            $this->scanStaleKeyImages()
        );

        $filesystem = $this->scanFilesystem($generatedAt);
        $sections[] = $this->section(
            'missing_physical_files',
            'Referenced paths missing from disk',
            'The database still references these paths, but no physical file exists at the expected location on the server.',
            'Potentially visible but broken: users may see a missing image, failed preview, or download link that cannot open. The database metadata can still retain useful ownership, date, description, and relationship information.',
            'Do not remove these automatically. First restore the file from backup at the recorded path or locate and relink a replacement. Remove metadata manually only after confirming that recovery is impossible and the associations are no longer valuable.',
            $filesystem['missing']
        );
        $sections[] = $this->section(
            'unreferenced_physical_files',
            'Physical files without a reference',
            'These files exist under uploads but are absent from files, discussion_files, user avatars, discussion HTML, item text, and site settings. Recent uploads remain protected by a 24-hour grace period.',
            'Not visible or usable through the application because no database or rich-text content points to the file. It could only be reached by someone who already knows its exact raw URL.',
            'Quarantine every actionable file. Do not act on files still inside the grace period; reload after 24 hours so an in-progress upload has time to acquire its database reference. Referenced rich-text files are excluded automatically.',
            $filesystem['unreferenced']
        );

        $myisamTables = 0;
        $engineRows = $this->db->fetchAll(
            'SELECT ENGINE, COUNT(*) AS table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() GROUP BY ENGINE ORDER BY ENGINE'
        );
        foreach ($engineRows as $engineRow) {
            if (strcasecmp((string) ($engineRow['ENGINE'] ?? ''), 'MyISAM') === 0) {
                $myisamTables = (int) ($engineRow['table_count'] ?? 0);
            }
        }

        $findingCount = 0;
        $actionableCount = 0;
        foreach ($sections as $section) {
            $findingCount += count($section['rows']);
            foreach ($section['rows'] as $row) {
                if (!empty($row['action'])) {
                    $actionableCount++;
                }
            }
        }

        return [
            'generated_at' => $generatedAt,
            'generated_at_display' => date('Y-m-d H:i:s', $generatedAt),
            'expires_at_display' => date('Y-m-d H:i:s', $generatedAt + self::SCAN_TOKEN_TTL),
            'finding_count' => $findingCount,
            'actionable_count' => $actionableCount,
            'engine_rows' => $engineRows,
            'myisam_tables' => $myisamTables,
            'archive_root' => $this->archiveRoot,
            'sections' => $sections,
            'exclusions' => [
                'empty_linked_items' => (int) $this->db->fetchValue(
                    "SELECT COUNT(DISTINCT i.item_id)
                     FROM items i
                     JOIN item_links il ON il.item_id = i.item_id
                     LEFT JOIN file_links fl ON fl.item_id = i.item_id
                     WHERE TRIM(i.detail_value) = '' AND fl.id IS NULL"
                ),
                'standalone_file_links' => (int) $this->db->fetchValue(
                    'SELECT COUNT(*) FROM file_links WHERE item_id IS NULL'
                ),
                'physical_file_count' => $filesystem['physical_count'],
                'structured_path_count' => $filesystem['structured_count'],
                'rich_text_reference_count' => $filesystem['embedded_reference_count'],
            ],
        ];
    }

    public function issueActionToken(string $action, string $target, string $csrfToken, ?int $issuedAt = null): string
    {
        $payload = json_encode([
            'action' => $action,
            'target' => $target,
            'issued_at' => $issuedAt ?? time(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, $csrfToken);

        return $encoded . '.' . $signature;
    }

    public function validateActionToken(
        string $token,
        string $action,
        string $target,
        string $csrfToken,
        ?int $now = null
    ): bool {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], $csrfToken), $parts[1])) {
            return false;
        }

        $padding = strlen($parts[0]) % 4;
        $encoded = $parts[0] . ($padding === 0 ? '' : str_repeat('=', 4 - $padding));
        $payload = json_decode(base64_decode(strtr($encoded, '-_', '+/'), true) ?: '', true);
        if (!is_array($payload)) {
            return false;
        }

        $currentTime = $now ?? time();
        $issuedAt = (int) ($payload['issued_at'] ?? 0);

        return ($payload['action'] ?? null) === $action
            && (string) ($payload['target'] ?? '') === $target
            && $issuedAt <= $currentTime + 60
             && $issuedAt >= $currentTime - self::SCAN_TOKEN_TTL;
    }

    /**
     * Sign an existing, safe upload path for administrator-only review.
     */
    public function issueFileReviewToken(string $relativePath, string $csrfToken): ?string
    {
        $absolute = $this->reviewableUploadPath($relativePath);
        if ($absolute === null || $csrfToken === '') {
            return null;
        }

        $normalized = $this->normalizeRelativePath($relativePath);
        return hash_hmac('sha256', 'cleanup-file-review:' . $normalized, $csrfToken);
    }

    /**
     * Resolve a signed review request without permitting paths outside uploads.
     */
    public function resolveFileReviewPath(string $relativePath, string $token, string $csrfToken): ?string
    {
        if ($token === '' || $csrfToken === '') {
            return null;
        }

        $absolute = $this->reviewableUploadPath($relativePath);
        if ($absolute === null) {
            return null;
        }

        $normalized = $this->normalizeRelativePath($relativePath);
        $expected = hash_hmac('sha256', 'cleanup-file-review:' . $normalized, $csrfToken);
        return hash_equals($expected, $token) ? $absolute : null;
    }

    /**
     * Re-scan the requested target, write a recovery manifest, then perform the
     * one action represented by the signed scan token.
     */
    public function performAction(
        string $action,
        string $target,
        string $scanToken,
        string $csrfToken,
        int $actorId
    ): array {
        if (!$this->validateActionToken($scanToken, $action, $target, $csrfToken)) {
            throw new RuntimeException('The cleanup scan has expired or does not match this action. Reload the page and review a fresh scan.');
        }

        $candidate = $this->findCurrentCandidate($action, $target);
        if ($candidate === null) {
            throw new RuntimeException('This finding is no longer actionable. The database or files changed after the scan; review the refreshed report.');
        }

        $manifest = $this->writeManifest($action, $target, $actorId, $candidate);
        $message = '';

        try {
            switch ($action) {
                case 'cleanup_delete_orphan_item':
                    $affected = $this->execute(
                        "DELETE FROM items
                         WHERE item_id = ?
                           AND item_identifier IS NULL
                           AND NOT EXISTS (SELECT 1 FROM item_links WHERE item_links.item_id = items.item_id)
                           AND NOT EXISTS (SELECT 1 FROM file_links WHERE file_links.item_id = items.item_id)
                           AND NOT EXISTS (SELECT 1 FROM item_comments WHERE item_comments.item_id = items.item_id)
                           AND NOT EXISTS (SELECT 1 FROM item_reactions WHERE item_reactions.item_id = items.item_id)",
                        [(int) $target]
                    );
                    $this->requireAffected($affected, 'The item was no longer orphaned.');
                    $message = "Removed orphan item {$target}.";
                    break;

                case 'cleanup_delete_dangling_item_link':
                    $affected = $this->execute(
                        'DELETE il FROM item_links il LEFT JOIN items i ON i.item_id = il.item_id WHERE il.id = ? AND i.item_id IS NULL',
                        [(int) $target]
                    );
                    $this->requireAffected($affected, 'The item link was no longer dangling.');
                    $message = "Removed dangling item link {$target}.";
                    break;

                case 'cleanup_delete_duplicate_item_link':
                    $affected = $this->execute(
                        'DELETE duplicate_link
                         FROM item_links duplicate_link
                         JOIN item_links keeper
                           ON keeper.individual_id = duplicate_link.individual_id
                          AND keeper.item_id = duplicate_link.item_id
                          AND keeper.id < duplicate_link.id
                         JOIN items i ON i.item_id = duplicate_link.item_id
                         WHERE duplicate_link.id = ?',
                        [(int) $target]
                    );
                    $this->requireAffected($affected, 'The item link was no longer an excess duplicate.');
                    $message = "Removed duplicate item link {$target}; the oldest matching link was retained.";
                    break;

                case 'cleanup_delete_empty_item_group':
                    $affected = $this->execute(
                        'DELETE ig FROM item_groups ig LEFT JOIN items i ON i.item_identifier = ig.item_identifier WHERE ig.id = ? AND i.item_id IS NULL',
                        [(int) $target]
                    );
                    $this->requireAffected($affected, 'The item group was no longer empty.');
                    $message = "Removed empty item group {$target}.";
                    break;

                case 'cleanup_quarantine_unlinked_file':
                    $fileRow = $this->db->fetchOne(
                        'SELECT f.* FROM files f LEFT JOIN file_links fl ON fl.file_id = f.id WHERE f.id = ? AND fl.id IS NULL',
                        [(int) $target]
                    );
                    if (!$fileRow || empty($fileRow['file_path'])) {
                        throw new RuntimeException('The file record is no longer unlinked.');
                    }
                    $moved = $this->moveToQuarantine((string) $fileRow['file_path'], $manifest);
                    try {
                        $affected = $this->execute(
                            'DELETE f FROM files f LEFT JOIN file_links fl ON fl.file_id = f.id WHERE f.id = ? AND fl.id IS NULL',
                            [(int) $target]
                        );
                        $this->requireAffected($affected, 'The file record gained a link before it could be removed.');
                    } catch (Throwable $exception) {
                        $this->restoreMovedFile($moved);
                        throw $exception;
                    }
                    $message = "Quarantined unlinked file record {$target}.";
                    break;

                case 'cleanup_delete_file_link_missing_file':
                    $affected = $this->execute(
                        'DELETE fl FROM file_links fl LEFT JOIN files f ON f.id = fl.file_id WHERE fl.id = ? AND f.id IS NULL',
                        [(int) $target]
                    );
                    $this->requireAffected($affected, 'The file link now has a valid file.');
                    $message = "Removed file link {$target}, whose file record was missing.";
                    break;

                case 'cleanup_detach_file_from_missing_item':
                    $affected = $this->execute(
                        'UPDATE file_links fl
                         JOIN files f ON f.id = fl.file_id
                         LEFT JOIN items i ON i.item_id = fl.item_id
                         SET fl.item_id = NULL
                         WHERE fl.id = ? AND fl.item_id IS NOT NULL AND i.item_id IS NULL',
                        [(int) $target]
                    );
                    $this->requireAffected($affected, 'The file link no longer points to a missing item.');
                    $message = "Preserved file link {$target} as a standalone upload.";
                    break;

                case 'cleanup_delete_duplicate_file_link':
                    $affected = $this->execute(
                        'DELETE duplicate_link
                         FROM file_links duplicate_link
                         JOIN file_links keeper
                           ON keeper.file_id = duplicate_link.file_id
                          AND keeper.individual_id = duplicate_link.individual_id
                          AND keeper.item_id <=> duplicate_link.item_id
                          AND keeper.id < duplicate_link.id
                         JOIN files f ON f.id = duplicate_link.file_id
                         LEFT JOIN items i ON i.item_id = duplicate_link.item_id
                         WHERE duplicate_link.id = ?
                           AND (duplicate_link.item_id IS NULL OR i.item_id IS NOT NULL)',
                        [(int) $target]
                    );
                    $this->requireAffected($affected, 'The file link was no longer an excess duplicate.');
                    $message = "Removed duplicate file link {$target}; the oldest matching link was retained.";
                    break;

                case 'cleanup_remove_stale_key_image':
                    $affected = $this->execute(
                        "DELETE i, il
                         FROM items i
                         JOIN item_links il ON il.item_id = i.item_id
                         LEFT JOIN file_links fl ON fl.item_id = i.item_id
                         LEFT JOIN item_comments ic ON ic.item_id = i.item_id
                         LEFT JOIN item_reactions ir ON ir.item_id = i.item_id
                         WHERE i.item_id = ?
                           AND i.detail_type = 'Key Image'
                           AND i.item_identifier IS NULL
                           AND fl.id IS NULL AND ic.id IS NULL AND ir.id IS NULL",
                        [(int) $target]
                    );
                    $this->requireAffected($affected, 'The key image metadata gained a dependency.');
                    $message = "Removed stale Key Image item {$target} and its individual link metadata.";
                    break;

                case 'cleanup_quarantine_unreferenced_file':
                    $this->moveToQuarantine($target, $manifest);
                    $message = "Moved {$target} to quarantine.";
                    break;

                default:
                    throw new InvalidArgumentException('Unsupported cleanup action.');
            }

            $this->completeManifest($manifest, 'completed', $message);
        } catch (Throwable $exception) {
            $this->completeManifest($manifest, 'failed', $exception->getMessage());
            throw $exception;
        }

        return ['message' => $message, 'manifest' => $manifest];
    }

    /**
     * Apply a reviewed set sequentially. All submitted tokens and initial
     * candidates are validated before the first mutation; every action is then
     * rechecked again by performAction and receives its own recovery manifest.
     */
    public function performActions(array $selections, string $csrfToken, int $actorId): array
    {
        if ($selections === []) {
            throw new RuntimeException('Select at least one reviewed cleanup finding.');
        }
        if (count($selections) > 250) {
            throw new RuntimeException('A maximum of 250 cleanup findings can be processed in one request.');
        }

        $normalizedSelections = [];
        $selectionKeys = [];
        foreach ($selections as $selection) {
            if (!is_array($selection)) {
                throw new RuntimeException('A selected cleanup finding was malformed. Reload the page and review a fresh scan.');
            }

            $action = isset($selection['action']) ? (string) $selection['action'] : '';
            $target = isset($selection['target']) ? (string) $selection['target'] : '';
            $scanToken = isset($selection['scan_token']) ? (string) $selection['scan_token'] : '';
            if ($action === '' || $target === '' || !$this->validateActionToken($scanToken, $action, $target, $csrfToken)) {
                throw new RuntimeException('A selected cleanup finding has expired or is invalid. No cleanup was performed; reload and review a fresh scan.');
            }

            $selectionKey = $action . "\0" . $target;
            if (isset($selectionKeys[$selectionKey])) {
                throw new RuntimeException('The same cleanup finding was selected more than once. No cleanup was performed.');
            }
            $selectionKeys[$selectionKey] = true;
            $normalizedSelections[] = [
                'action' => $action,
                'target' => $target,
                'scan_token' => $scanToken,
            ];
        }

        $currentCandidates = [];
        foreach ($this->scan()['sections'] as $section) {
            foreach ($section['rows'] as $row) {
                if (!empty($row['action'])) {
                    $candidateKey = $row['action']['name'] . "\0" . (string) $row['action']['target'];
                    $currentCandidates[$candidateKey] = true;
                }
            }
        }
        foreach ($selectionKeys as $selectionKey => $_selected) {
            if (!isset($currentCandidates[$selectionKey])) {
                throw new RuntimeException('A selected finding is no longer actionable. No cleanup was performed; review the refreshed report.');
            }
        }

        $results = [];
        $total = count($normalizedSelections);
        foreach ($normalizedSelections as $selection) {
            try {
                $results[] = $this->performAction(
                    $selection['action'],
                    $selection['target'],
                    $selection['scan_token'],
                    $csrfToken,
                    $actorId
                );
            } catch (Throwable $exception) {
                $completed = count($results);
                throw new RuntimeException(
                    "Bulk cleanup stopped after {$completed} of {$total} selected findings. Completed actions were not rolled back and retain their recovery manifests. "
                    . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        }

        return [
            'count' => count($results),
            'messages' => array_column($results, 'message'),
            'manifests' => array_column($results, 'manifest'),
        ];
    }

    private function scanOrphanItems(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT i.*,
                (SELECT COUNT(*) FROM file_links fl WHERE fl.item_id = i.item_id) AS file_link_count,
                (SELECT COUNT(*) FROM item_comments ic WHERE ic.item_id = i.item_id) AS comment_count,
                (SELECT COUNT(*) FROM item_reactions ir WHERE ir.item_id = i.item_id) AS reaction_count,
                (SELECT COUNT(*) FROM item_groups ig WHERE ig.item_identifier = i.item_identifier) AS group_count
             FROM items i
             WHERE NOT EXISTS (SELECT 1 FROM item_links il WHERE il.item_id = i.item_id)
             ORDER BY i.item_id'
        );

        $findings = [];
        foreach ($rows as $row) {
            $safe = $row['item_identifier'] === null
                && (int) $row['file_link_count'] === 0
                && (int) $row['comment_count'] === 0
                && (int) $row['reaction_count'] === 0;
            $findings[] = $this->finding(
                'Item ' . $row['item_id'] . ' - ' . $row['detail_type'],
                [
                    'Value' => $this->shorten((string) $row['detail_value']),
                    'Updated' => $row['updated'],
                    'Item identifier' => $row['item_identifier'] ?? 'None',
                    'Files / comments / reactions' => $row['file_link_count'] . ' / ' . $row['comment_count'] . ' / ' . $row['reaction_count'],
                ],
                $safe ? $this->action('cleanup_delete_orphan_item', (string) $row['item_id'], 'Remove orphan item', 'Delete this unreferenced item row? A recovery manifest will be written first.') : null,
                $safe ? null : 'Review only: this item still has grouped or dependent data.'
            );
        }
        return $findings;
    }

    private function scanDanglingItemLinks(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT il.* FROM item_links il LEFT JOIN items i ON i.item_id = il.item_id WHERE i.item_id IS NULL ORDER BY il.id'
        );
        return array_map(fn(array $row): array => $this->finding(
            'Item link ' . $row['id'],
            ['Individual ID' => $row['individual_id'], 'Missing item ID' => $row['item_id']],
            $this->action('cleanup_delete_dangling_item_link', (string) $row['id'], 'Remove broken link', 'Remove this link to a missing item?'),
            null,
            ['individual_ids' => [$row['individual_id']]]
        ), $rows);
    }

    private function scanDuplicateItemLinks(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT duplicate_link.*, grouped.keep_id, grouped.link_count
             FROM item_links duplicate_link
             JOIN (
                SELECT individual_id, item_id, MIN(id) AS keep_id, COUNT(*) AS link_count
                FROM item_links
                GROUP BY individual_id, item_id
                HAVING COUNT(*) > 1
             ) grouped
               ON grouped.individual_id = duplicate_link.individual_id
              AND grouped.item_id = duplicate_link.item_id
             JOIN items i ON i.item_id = duplicate_link.item_id
             WHERE duplicate_link.id <> grouped.keep_id
             ORDER BY duplicate_link.individual_id, duplicate_link.item_id, duplicate_link.id'
        );
        return array_map(fn(array $row): array => $this->finding(
            'Duplicate item link ' . $row['id'],
            [
                'Individual ID' => $row['individual_id'],
                'Item ID' => $row['item_id'],
                'Retained link ID' => $row['keep_id'],
                'Links in set' => $row['link_count'],
            ],
            $this->action('cleanup_delete_duplicate_item_link', (string) $row['id'], 'Remove duplicate', 'Remove only this excess link and retain the oldest matching link?'),
            null,
            ['individual_ids' => [$row['individual_id']]]
        ), $rows);
    }

    private function scanItemGroupIntegrity(): array
    {
        $findings = [];
        foreach ($this->db->fetchAll(
            'SELECT ig.* FROM item_groups ig LEFT JOIN items i ON i.item_identifier = ig.item_identifier WHERE i.item_id IS NULL ORDER BY ig.id'
        ) as $row) {
            $findings[] = $this->finding(
                'Empty item group ' . $row['id'],
                ['Identifier' => $row['item_identifier'], 'Group type' => $row['item_group_name']],
                $this->action('cleanup_delete_empty_item_group', (string) $row['id'], 'Remove empty group', 'Remove this group after a fresh dependency check?')
            );
        }

        foreach ($this->db->fetchAll(
            'SELECT i.item_id, i.item_identifier, i.detail_type,
                    GROUP_CONCAT(DISTINCT il.individual_id ORDER BY il.individual_id) AS individual_ids
             FROM items i
             LEFT JOIN item_groups ig ON ig.item_identifier = i.item_identifier
             LEFT JOIN item_links il ON il.item_id = i.item_id
             WHERE i.item_identifier IS NOT NULL AND ig.id IS NULL
             GROUP BY i.item_id, i.item_identifier, i.detail_type
             ORDER BY i.item_identifier, i.item_id'
        ) as $row) {
            $findings[] = $this->finding(
                'Item ' . $row['item_id'] . ' has no group record',
                [
                    'Identifier' => $row['item_identifier'],
                    'Detail type' => $row['detail_type'],
                    'Individuals' => $row['individual_ids'] ?: 'None',
                ],
                null,
                'Review only: reconstructing the group may be preferable to deleting its items.',
                ['individual_ids' => $this->parseIndividualIds($row['individual_ids'] ?? '')]
            );
        }

        foreach (['item_comments' => 'Comment', 'item_reactions' => 'Reaction'] as $table => $label) {
            $rows = $this->db->fetchAll(
                "SELECT source.id, source.item_id, source.item_identifier
                 FROM {$table} source
                 LEFT JOIN items i ON i.item_id = source.item_id
                 LEFT JOIN item_groups ig ON ig.item_identifier = source.item_identifier
                 WHERE i.item_id IS NULL AND ig.id IS NULL
                 ORDER BY source.id"
            );
            foreach ($rows as $row) {
                $findings[] = $this->finding(
                    $label . ' ' . $row['id'] . ' has no item or group',
                    ['Item ID' => $row['item_id'], 'Item identifier' => $row['item_identifier'] ?? 'None'],
                    null,
                    'Review only: user-generated content is never discarded automatically.'
                );
            }
        }
        return $findings;
    }

    private function scanUnlinkedFileRecords(int $scanTime): array
    {
        $rows = $this->db->fetchAll(
            'SELECT f.* FROM files f LEFT JOIN file_links fl ON fl.file_id = f.id WHERE fl.id IS NULL ORDER BY f.id'
        );
        $findings = [];
        foreach ($rows as $row) {
            $path = (string) $row['file_path'];
            $absolute = $this->absoluteUploadPath($path);
            $exists = $absolute !== null && is_file($absolute);
            $otherReferences = $this->findOtherPathReferences($path, (int) $row['id']);
            $timestamp = $exists ? (int) filemtime($absolute) : (int) (strtotime((string) $row['upload_date']) ?: 0);
            $oldEnough = $timestamp > 0 && $timestamp <= $scanTime - self::FILE_GRACE_PERIOD;
            $safe = $exists && $oldEnough && $otherReferences === [];
            $note = null;
            if (!$exists) {
                $note = 'Review only: the physical file is already missing.';
            } elseif ($otherReferences !== []) {
                $note = 'Review only: this path is also referenced by ' . implode(', ', $otherReferences) . '.';
            } elseif (!$oldEnough) {
                $note = 'Protected by the 24-hour recent-file grace period.';
            }

            $findings[] = $this->finding(
                'File record ' . $row['id'],
                [
                    'Path' => $path,
                    'Type / format' => $row['file_type'] . ' / ' . $row['file_format'],
                    'Description' => $this->shorten((string) ($row['file_description'] ?? '')),
                    'Uploaded' => $row['upload_date'],
                    'Disk status' => $exists ? $this->formatBytes((int) filesize($absolute)) : 'Missing',
                    'Other references' => $otherReferences !== [] ? implode(', ', $otherReferences) : 'None',
                ],
                $safe ? $this->action('cleanup_quarantine_unlinked_file', (string) $row['id'], 'Quarantine file', 'Move this upload outside the web tree and remove its unlinked files row?') : null,
                $note,
                ['file_path' => $exists ? $path : null]
            );
        }
        return $findings;
    }

    private function findOtherPathReferences(string $path, int $fileId): array
    {
        $references = [];
        foreach ($this->db->fetchAll('SELECT id FROM files WHERE file_path = ? AND id <> ?', [$path, $fileId]) as $row) {
            $references[] = 'file record #' . $row['id'];
        }
        foreach ($this->db->fetchAll('SELECT id FROM discussion_files WHERE file_path = ?', [$path]) as $row) {
            $references[] = 'discussion file #' . $row['id'];
        }
        foreach ($this->db->fetchAll('SELECT id FROM users WHERE avatar = ?', [$path]) as $row) {
            $references[] = 'user avatar #' . $row['id'];
        }
        foreach ($this->db->fetchAll('SELECT id FROM discussions WHERE LOCATE(?, content) > 0', [$path]) as $row) {
            $references[] = 'discussion #' . $row['id'];
        }
        foreach ($this->db->fetchAll('SELECT item_id FROM items WHERE LOCATE(?, detail_value) > 0', [$path]) as $row) {
            $references[] = 'item #' . $row['item_id'];
        }
        foreach ($this->db->fetchAll('SELECT name FROM site_settings WHERE LOCATE(?, value) > 0', [$path]) as $row) {
            $references[] = 'site setting ' . $row['name'];
        }
        return array_values(array_unique($references));
    }

    private function scanBrokenFileLinks(): array
    {
        $findings = [];
        $missingFiles = $this->db->fetchAll(
            'SELECT fl.* FROM file_links fl LEFT JOIN files f ON f.id = fl.file_id WHERE f.id IS NULL ORDER BY fl.id'
        );
        foreach ($missingFiles as $row) {
            $findings[] = $this->finding(
                'File link ' . $row['id'] . ' has no file record',
                ['Missing file ID' => $row['file_id'], 'Individual ID' => $row['individual_id'], 'Item ID' => $row['item_id'] ?? 'Standalone'],
                $this->action('cleanup_delete_file_link_missing_file', (string) $row['id'], 'Remove broken link', 'Remove this link to a missing file record?'),
                null,
                ['individual_ids' => [$row['individual_id']]]
            );
        }

        $missingItems = $this->db->fetchAll(
            'SELECT fl.*, f.file_path
             FROM file_links fl
             JOIN files f ON f.id = fl.file_id
             LEFT JOIN items i ON i.item_id = fl.item_id
             WHERE fl.item_id IS NOT NULL AND i.item_id IS NULL
             ORDER BY fl.id'
        );
        foreach ($missingItems as $row) {
            $findings[] = $this->finding(
                'File link ' . $row['id'] . ' has no item',
                ['File ID' => $row['file_id'], 'Path' => $row['file_path'], 'Individual ID' => $row['individual_id'], 'Missing item ID' => $row['item_id']],
                $this->action('cleanup_detach_file_from_missing_item', (string) $row['id'], 'Preserve as standalone', 'Clear only the missing item reference and preserve this file for the individual?'),
                null,
                ['individual_ids' => [$row['individual_id']], 'file_path' => $row['file_path']]
            );
        }

        $duplicates = $this->db->fetchAll(
            'SELECT duplicate_link.*, f.file_path, grouped.keep_id, grouped.link_count
             FROM file_links duplicate_link
             JOIN (
                SELECT file_id, individual_id, item_id, MIN(id) AS keep_id, COUNT(*) AS link_count
                FROM file_links
                GROUP BY file_id, individual_id, item_id
                HAVING COUNT(*) > 1
             ) grouped
               ON grouped.file_id = duplicate_link.file_id
              AND grouped.individual_id = duplicate_link.individual_id
              AND grouped.item_id <=> duplicate_link.item_id
             JOIN files f ON f.id = duplicate_link.file_id
             LEFT JOIN items i ON i.item_id = duplicate_link.item_id
             WHERE duplicate_link.id <> grouped.keep_id
               AND (duplicate_link.item_id IS NULL OR i.item_id IS NOT NULL)
             ORDER BY duplicate_link.id'
        );
        foreach ($duplicates as $row) {
            $findings[] = $this->finding(
                'Duplicate file link ' . $row['id'],
                ['File ID' => $row['file_id'], 'Path' => $row['file_path'], 'Individual ID' => $row['individual_id'], 'Item ID' => $row['item_id'] ?? 'Standalone', 'Retained link ID' => $row['keep_id']],
                $this->action('cleanup_delete_duplicate_file_link', (string) $row['id'], 'Remove duplicate', 'Remove only this excess file link?'),
                null,
                ['individual_ids' => [$row['individual_id']], 'file_path' => $row['file_path']]
            );
        }
        return $findings;
    }

    private function scanStaleKeyImages(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT i.*, GROUP_CONCAT(DISTINCT il.individual_id ORDER BY il.individual_id) AS individual_ids,
                COUNT(DISTINCT ic.id) AS comment_count, COUNT(DISTINCT ir.id) AS reaction_count
             FROM items i
             JOIN item_links il ON il.item_id = i.item_id
             LEFT JOIN file_links fl ON fl.item_id = i.item_id
             LEFT JOIN item_comments ic ON ic.item_id = i.item_id
             LEFT JOIN item_reactions ir ON ir.item_id = i.item_id
             WHERE i.detail_type = 'Key Image' AND fl.id IS NULL
             GROUP BY i.item_id
             ORDER BY i.item_id"
        );

        $findings = [];
        foreach ($rows as $row) {
            $safe = $row['item_identifier'] === null
                && (int) $row['comment_count'] === 0
                && (int) $row['reaction_count'] === 0;
            $findings[] = $this->finding(
                'Key Image item ' . $row['item_id'],
                [
                    'Individuals' => $row['individual_ids'],
                    'Value' => $this->shorten((string) $row['detail_value']),
                    'Updated' => $row['updated'],
                    'Comments / reactions' => $row['comment_count'] . ' / ' . $row['reaction_count'],
                ],
                $safe ? $this->action('cleanup_remove_stale_key_image', (string) $row['item_id'], 'Remove stale metadata', 'Remove this Key Image item and its individual links? No physical file will be deleted.') : null,
                $safe ? null : 'Review only: grouped or user-generated data still depends on this item.',
                ['individual_ids' => $this->parseIndividualIds($row['individual_ids'] ?? '')]
            );
        }
        return $findings;
    }

    private function scanFilesystem(int $scanTime): array
    {
        $referenceMap = [];
        foreach ($this->db->fetchAll('SELECT id, file_path FROM files') as $row) {
            $this->addStructuredReference($referenceMap, (string) $row['file_path'], 'files #' . $row['id']);
        }
        foreach ($this->db->fetchAll('SELECT id, file_path FROM discussion_files') as $row) {
            $this->addStructuredReference($referenceMap, (string) $row['file_path'], 'discussion_files #' . $row['id']);
        }
        foreach ($this->db->fetchAll("SELECT id, avatar FROM users WHERE avatar IS NOT NULL AND avatar <> ''") as $row) {
            $this->addStructuredReference($referenceMap, (string) $row['avatar'], 'users.avatar #' . $row['id']);
        }

        $richText = [];
        foreach ($this->db->fetchAll("SELECT id, content FROM discussions WHERE content LIKE '%uploads/%'") as $row) {
            $richText[] = ['source' => 'discussion #' . $row['id'], 'content' => (string) $row['content']];
        }
        foreach ($this->db->fetchAll("SELECT item_id, detail_value FROM items WHERE detail_value LIKE '%uploads/%'") as $row) {
            $richText[] = ['source' => 'item #' . $row['item_id'], 'content' => (string) $row['detail_value']];
        }
        foreach ($this->db->fetchAll("SELECT name, value FROM site_settings WHERE value LIKE '%uploads/%'") as $row) {
            $richText[] = ['source' => 'site setting ' . $row['name'], 'content' => (string) $row['value']];
        }

        $missing = [];
        foreach ($referenceMap as $path => $sources) {
            $absolute = $this->absoluteUploadPath($path);
            if ($absolute === null || !is_file($absolute)) {
                $missing[] = $this->finding(
                    $path,
                    ['References' => implode(', ', array_unique($sources)), 'Disk status' => $absolute === null ? 'Invalid or unsafe path' : 'Missing'],
                    null,
                    'Review only: restore the file or repair its metadata manually.'
                );
            }
        }

        $unreferenced = [];
        $physicalCount = 0;
        $embeddedReferenceCount = 0;
        $uploadRoot = realpath($this->projectRoot . DIRECTORY_SEPARATOR . 'uploads');
        if ($uploadRoot !== false) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploadRoot, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || str_starts_with($file->getFilename(), '.') || $file->isLink()) {
                    continue;
                }
                $physicalCount++;
                $relative = 'uploads/' . str_replace('\\', '/', substr($file->getPathname(), strlen($uploadRoot) + 1));
                if (isset($referenceMap[$relative])) {
                    continue;
                }

                $embeddedSources = [];
                foreach ($richText as $textRow) {
                    if ($this->contentContainsPath($textRow['content'], $relative)) {
                        $embeddedSources[] = $textRow['source'];
                    }
                }
                if ($embeddedSources !== []) {
                    $embeddedReferenceCount++;
                    continue;
                }

                $modified = (int) $file->getMTime();
                $oldEnough = $modified <= $scanTime - self::FILE_GRACE_PERIOD;
                $unreferenced[] = $this->finding(
                    $relative,
                    [
                        'Size' => $this->formatBytes((int) $file->getSize()),
                        'Modified' => date('Y-m-d H:i:s', $modified),
                        'Reference scan' => 'No structured or rich-text reference found',
                    ],
                    $oldEnough ? $this->action('cleanup_quarantine_unreferenced_file', $relative, 'Move to quarantine', 'Move this unreferenced physical file outside the web upload tree?') : null,
                    $oldEnough ? null : 'Protected by the 24-hour recent-file grace period.',
                    ['file_path' => $relative]
                );
            }
        }

        return [
            'missing' => $missing,
            'unreferenced' => $unreferenced,
            'physical_count' => $physicalCount,
            'structured_count' => count($referenceMap),
            'embedded_reference_count' => $embeddedReferenceCount,
        ];
    }

    private function findCurrentCandidate(string $action, string $target): ?array
    {
        $rows = match ($action) {
            'cleanup_delete_orphan_item' => $this->scanOrphanItems(),
            'cleanup_delete_dangling_item_link' => $this->scanDanglingItemLinks(),
            'cleanup_delete_duplicate_item_link' => $this->scanDuplicateItemLinks(),
            'cleanup_delete_empty_item_group' => $this->scanItemGroupIntegrity(),
            'cleanup_quarantine_unlinked_file' => $this->scanUnlinkedFileRecords(time()),
            'cleanup_delete_file_link_missing_file',
            'cleanup_detach_file_from_missing_item',
            'cleanup_delete_duplicate_file_link' => $this->scanBrokenFileLinks(),
            'cleanup_remove_stale_key_image' => $this->scanStaleKeyImages(),
            'cleanup_quarantine_unreferenced_file' => $this->scanFilesystem(time())['unreferenced'],
            default => [],
        };

        foreach ($rows as $row) {
            if (($row['action']['name'] ?? null) === $action && (string) ($row['action']['target'] ?? '') === $target) {
                return $row;
            }
        }
        return null;
    }

    private function writeManifest(string $action, string $target, int $actorId, array $candidate): string
    {
        $manifestDirectory = $this->archiveRoot . DIRECTORY_SEPARATOR . 'manifests';
        $this->ensurePrivateDirectory($manifestDirectory);
        $safeTarget = trim((string) preg_replace('/[^a-zA-Z0-9._-]+/', '_', $target), '_');
        $safeTarget = $safeTarget !== '' ? substr($safeTarget, 0, 80) : 'target';
        $path = $manifestDirectory . DIRECTORY_SEPARATOR
            . gmdate('Ymd\THis\Z') . '_' . substr($action, 8) . '_' . $safeTarget . '_' . bin2hex(random_bytes(4)) . '.json';
        $payload = [
            'version' => 1,
            'status' => 'prepared',
            'created_at_utc' => gmdate(DATE_ATOM),
            'actor_user_id' => $actorId,
            'action' => $action,
            'target' => $target,
            'candidate' => $candidate,
            'records' => $this->exportRecords($action, $target),
        ];
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('The recovery manifest could not be written; no cleanup was performed.');
        }
        @chmod($path, 0600);
        return $path;
    }

    private function exportRecords(string $action, string $target): array
    {
        $id = (int) $target;
        return match ($action) {
            'cleanup_delete_orphan_item', 'cleanup_remove_stale_key_image' => [
                'items' => $this->db->fetchAll('SELECT * FROM items WHERE item_id = ?', [$id]),
                'item_links' => $this->db->fetchAll('SELECT * FROM item_links WHERE item_id = ?', [$id]),
                'file_links' => $this->db->fetchAll('SELECT * FROM file_links WHERE item_id = ?', [$id]),
                'item_comments' => $this->db->fetchAll('SELECT * FROM item_comments WHERE item_id = ?', [$id]),
                'item_reactions' => $this->db->fetchAll('SELECT * FROM item_reactions WHERE item_id = ?', [$id]),
            ],
            'cleanup_delete_dangling_item_link', 'cleanup_delete_duplicate_item_link' => [
                'item_links' => $this->db->fetchAll('SELECT * FROM item_links WHERE id = ?', [$id]),
            ],
            'cleanup_delete_empty_item_group' => [
                'item_groups' => $this->db->fetchAll('SELECT * FROM item_groups WHERE id = ?', [$id]),
            ],
            'cleanup_quarantine_unlinked_file' => [
                'files' => $this->db->fetchAll('SELECT * FROM files WHERE id = ?', [$id]),
                'file_links' => $this->db->fetchAll('SELECT * FROM file_links WHERE file_id = ?', [$id]),
            ],
            'cleanup_delete_file_link_missing_file', 'cleanup_detach_file_from_missing_item', 'cleanup_delete_duplicate_file_link' => [
                'file_links' => $this->db->fetchAll('SELECT * FROM file_links WHERE id = ?', [$id]),
            ],
            'cleanup_quarantine_unreferenced_file' => [
                'physical_file' => ['path' => $target],
            ],
            default => [],
        };
    }

    private function completeManifest(string $path, string $status, string $message): void
    {
        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload)) {
            return;
        }
        $payload['status'] = $status;
        $payload['completed_at_utc'] = gmdate(DATE_ATOM);
        $payload['result'] = $message;
        @file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            LOCK_EX
        );
    }

    private function moveToQuarantine(string $relativePath, string $manifestPath): array
    {
        $source = $this->absoluteUploadPath($relativePath);
        if ($source === null || !is_file($source) || is_link($source)) {
            throw new RuntimeException('The upload path is missing, unsafe, or is a symbolic link.');
        }

        $quarantineDirectory = $this->archiveRoot . DIRECTORY_SEPARATOR . 'quarantine' . DIRECTORY_SEPARATOR . gmdate('Y') . DIRECTORY_SEPARATOR . gmdate('m');
        $this->ensurePrivateDirectory($quarantineDirectory);
        $destination = $quarantineDirectory . DIRECTORY_SEPARATOR
            . gmdate('Ymd\THis\Z') . '_' . bin2hex(random_bytes(4)) . '_' . basename($source);
        if (!rename($source, $destination)) {
            throw new RuntimeException('The file could not be moved to quarantine; no database row was removed.');
        }

        $this->appendManifestData($manifestPath, [
            'quarantined_file' => [
                'original_relative_path' => $relativePath,
                'original_absolute_path' => $source,
                'quarantine_path' => $destination,
                'size' => filesize($destination),
                'sha256' => hash_file('sha256', $destination),
            ],
        ]);

        return ['source' => $source, 'destination' => $destination];
    }

    private function restoreMovedFile(array $moved): void
    {
        if (isset($moved['source'], $moved['destination']) && is_file($moved['destination']) && !file_exists($moved['source'])) {
            @rename($moved['destination'], $moved['source']);
        }
    }

    private function appendManifestData(string $path, array $data): void
    {
        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload)) {
            return;
        }
        $payload = array_merge($payload, $data);
        @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
    }

    private function execute(string $sql, array $params): int
    {
        $statement = $this->db->query($sql, $params);
        if ($statement === false) {
            throw new RuntimeException('The cleanup query failed. No further cleanup steps were performed.');
        }
        return (int) $statement->rowCount();
    }

    private function requireAffected(int $affected, string $message): void
    {
        if ($affected < 1) {
            throw new RuntimeException($message . ' Reload and review a fresh scan.');
        }
    }

    private function ensurePrivateDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('The cleanup archive directory could not be created. No cleanup was performed.');
        }
        @chmod($directory, 0700);
    }

    private function absoluteUploadPath(string $relativePath): ?string
    {
        $normalized = $this->normalizeRelativePath($relativePath);
        if (!str_starts_with($normalized, 'uploads/') || str_contains($normalized, "\0")) {
            return null;
        }
        if (preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
            return null;
        }

        $uploadRoot = realpath($this->projectRoot . DIRECTORY_SEPARATOR . 'uploads');
        if ($uploadRoot === false) {
            return null;
        }
        $candidate = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $resolved = realpath($candidate);
        if ($resolved !== false) {
            return $this->pathIsInside($resolved, $uploadRoot) ? $resolved : null;
        }

        $parent = realpath(dirname($candidate));
        return $parent !== false && $this->pathIsInside($parent, $uploadRoot) ? $candidate : null;
    }

    private function reviewableUploadPath(string $relativePath): ?string
    {
        $normalized = $this->normalizeRelativePath($relativePath);
        $candidate = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $absolute = $this->absoluteUploadPath($normalized);

        if ($absolute === null || !is_file($absolute) || is_link($candidate)) {
            return null;
        }

        return $absolute;
    }

    private function pathIsInside(string $path, string $root): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $root = strtolower($root);
        }
        return $path === $root || str_starts_with($path, $root . '/');
    }

    private function addStructuredReference(array &$map, string $path, string $source): void
    {
        $normalized = $this->normalizeRelativePath($path);
        if (str_starts_with($normalized, 'uploads/')) {
            $map[$normalized][] = $source;
        }
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = html_entity_decode(trim($path), ENT_QUOTES | ENT_HTML5);
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return ltrim($path, '/');
    }

    private function contentContainsPath(string $content, string $path): bool
    {
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
        $variants = [$path, '/' . $path, rawurlencode($path), str_replace(' ', '%20', $path)];
        foreach ($variants as $variant) {
            if ($variant !== '' && str_contains($decoded, $variant)) {
                return true;
            }
        }
        return false;
    }

    private function section(
        string $id,
        string $title,
        string $description,
        string $visibility,
        string $recommendation,
        array $rows
    ): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'visibility' => $visibility,
            'recommendation' => $recommendation,
            'rows' => $rows,
        ];
    }

    private function finding(
        string $summary,
        array $metadata,
        ?array $action = null,
        ?string $note = null,
        array $review = []
    ): array
    {
        $individualIds = array_values(array_unique(array_filter(
            array_map('intval', $review['individual_ids'] ?? []),
            static fn(int $id): bool => $id > 0
        )));
        $filePath = isset($review['file_path']) && is_string($review['file_path'])
            ? $this->normalizeRelativePath($review['file_path'])
            : null;

        return [
            'summary' => $summary,
            'metadata' => $metadata,
            'action' => $action,
            'note' => $note,
            'review' => [
                'individual_ids' => $individualIds,
                'file_path' => $filePath,
            ],
        ];
    }

    private function action(string $name, string $target, string $label, string $confirmation): array
    {
        return ['name' => $name, 'target' => $target, 'label' => $label, 'confirmation' => $confirmation];
    }

    private function shorten(string $value, int $limit = 180): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if (strlen($value) <= $limit) {
            return $value !== '' ? $value : '(empty)';
        }
        return substr($value, 0, $limit - 3) . '...';
    }

    private function parseIndividualIds(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return preg_split('/\s*,\s*/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }
}
