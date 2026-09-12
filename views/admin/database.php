<?php
/**
 * Database management page
 *   Check to see if the database matches the current schema (located in settings/nodavuvale.sql)
 *   using the functions in the Database class
 */
    //Check to see if the parent admin page has loaded, and if not then "require" it first
    if (!isset($admin_page) || !$admin_page) {
        $admin_backload=true;
        require_once('views/admin/index.php');
    }

    require_once __DIR__ . '/../../system/admin/DatabaseCleanupService.php';

    if (empty($_SESSION['admin_database_csrf'])) {
        $_SESSION['admin_database_csrf'] = bin2hex(random_bytes(32));
    }
    $databaseAdminCsrf = (string) $_SESSION['admin_database_csrf'];
    $cleanupService = new DatabaseCleanupService($db, dirname(__DIR__, 2));
    $message = null;
    $messageType = 'success';

    $currentSchema = $db->getCurrentDatabaseSchema();
    $nodavuvaleSchema = $db->getNodavuvaleSchema();
    $schemaComparison = $db->compareSchemas($currentSchema, $nodavuvaleSchema);

    // Perform actions based on the form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = (string) $_POST['action'];
        $tableName = $_POST['table_name'] ?? null;
        $columnName = $_POST['column_name'] ?? null;

        try {
            $postedCsrf = (string) ($_POST['csrf_token'] ?? '');
            if ($postedCsrf === '' || !hash_equals($databaseAdminCsrf, $postedCsrf)) {
                throw new RuntimeException('The security token was missing or invalid. Reload this page before trying again.');
            }

            if ($action === 'cleanup_bulk') {
                if (($_POST['confirm_cleanup'] ?? '') !== 'yes') {
                    throw new RuntimeException('Confirm that you reviewed the selected cleanup findings.');
                }

                $encodedSelections = $_POST['cleanup_bulk_selection'] ?? [];
                if (!is_array($encodedSelections)) {
                    throw new RuntimeException('The cleanup selection was malformed. Reload the page and try again.');
                }
                if (count($encodedSelections) > 250) {
                    throw new RuntimeException('A maximum of 250 cleanup findings can be processed in one request.');
                }

                $selections = [];
                foreach ($encodedSelections as $encodedSelection) {
                    if (!is_string($encodedSelection) || strlen($encodedSelection) > 20000) {
                        throw new RuntimeException('A selected cleanup finding was malformed. Reload the page and try again.');
                    }
                    $selection = json_decode($encodedSelection, true);
                    if (!is_array($selection)) {
                        throw new RuntimeException('A selected cleanup finding was malformed. Reload the page and try again.');
                    }
                    $selections[] = $selection;
                }

                $result = $cleanupService->performActions(
                    $selections,
                    $databaseAdminCsrf,
                    (int) $_SESSION['user_id']
                );
                $message = 'Processed ' . $result['count'] . ' selected cleanup finding'
                    . ($result['count'] === 1 ? '' : 's')
                    . '. Each action has its own recovery manifest under ' . $cleanupService->getArchiveRoot() . DIRECTORY_SEPARATOR . 'manifests.';
            } elseif (str_starts_with($action, 'cleanup_')) {
                if (($_POST['confirm_cleanup'] ?? '') !== 'yes') {
                    throw new RuntimeException('Confirm that you reviewed the selected cleanup finding.');
                }
                $target = (string) ($_POST['cleanup_target'] ?? '');
                $scanToken = (string) ($_POST['cleanup_scan_token'] ?? '');
                $result = $cleanupService->performAction(
                    $action,
                    $target,
                    $scanToken,
                    $databaseAdminCsrf,
                    (int) $_SESSION['user_id']
                );
                $message = $result['message'] . ' Recovery manifest: ' . $result['manifest'];
            } else {
                switch ($action) {
                    case 'create_table':
                        if (!$tableName || !isset($schemaComparison['tables_to_create'][$tableName])) {
                            throw new RuntimeException('That table is not in the current missing-table report. Reload and review the schema comparison.');
                        }
                        $sql = $db->getCreateTableSQL($tableName);
                        if (!$db->query($sql)) {
                            throw new RuntimeException("Error creating table '$tableName'.");
                        }
                        $message = "Table '$tableName' created successfully.";
                        break;

                    case 'add_column':
                        if (!$tableName || !$columnName || !isset($schemaComparison['columns_to_create'][$tableName][$columnName])) {
                            throw new RuntimeException('That column is not in the current missing-column report. Reload and review the schema comparison.');
                        }
                        $sql = $db->getAddColumnSQL($tableName, $columnName);
                        if (!$db->query($sql)) {
                            throw new RuntimeException("Error adding column '$columnName'.");
                        }
                        $message = "Column '$columnName' added to table '$tableName' successfully.";
                        break;

                    default:
                        throw new RuntimeException('Unsupported database action. Unexpected tables and columns are review-only and cannot be dropped from this page.');
                }
            }
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $messageType = 'error';
        }

        // Rebuild both reports after any attempted action so stale findings are
        // never rendered with a fresh action token.
        $currentSchema = $db->getCurrentDatabaseSchema();
        $nodavuvaleSchema = $db->getNodavuvaleSchema();
        $schemaComparison = $db->compareSchemas($currentSchema, $nodavuvaleSchema);
    }

    $cleanupScan = $cleanupService->scan();
?>
<section class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h1 class="text-4xl font-bold mb-6">Database Management</h1>
    <?php if ($message !== null): ?>
        <div class="mb-4 p-4 rounded border <?= $messageType === 'error' ? 'bg-red-100 text-red-800 border-red-300' : 'bg-green-100 text-green-800 border-green-300' ?>" role="alert">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <!-- Show the current database schema -->
    <div class="mb-4 p-2 pb-4 border border-blue-500 rounded">
        <h2 class="text-xl font-bold scrollable">
            Current Database Schema
            <button id="toggle-existing-schema-btn" class="ml-2 px-4 py-2 text-sm float-right bg-blue-400 text-white rounded" onclick="toggleVisibility('existing-schema-section', 'toggle-existing-schema-btn')">Show</button>
        </h2>
        
        <div class="mt-4 text-xs max-h-96 overflow-auto hidden" id="existing-schema-section" >
            <?php
            // Get the current database schema
            $currentSchema = $db->getCurrentDatabaseSchema();
            //Show the schema in a pretty way (it's a keyed multilevel array with table names as keys and columns as subkeys)
            echo '<div class="schema-container">';
            foreach ($currentSchema as $table => $columns) {
                echo '<div class="schema-table-container">';
                    echo '<h2 class="schema-table-header">' . htmlspecialchars($table) . '</h2>';
                    echo '<div class="schema-columns-container">';
                    foreach ($columns as $column => $type) {
                        echo '<div class="schema-column">';
                            echo '<span class="schema-column-name">' . htmlspecialchars($column) . '</span> ';
                            echo '<span class="schema-column-type">' . htmlspecialchars($type) . '</span>';
                        echo '</div>';
                    }
                    echo '</div>';
                echo '</div>';
            }
            echo '</div>';               
            ?>
        </div>
    </div>
    <!-- show the schema from the settings/nodavuvale.sql file -->
    <div class="mb-4 p-2 pb-4 border border-green-500 rounded">
        <h2 class="text-xl font-bold scrollable">
            NodaVuvale Database Schema
            <button id="toggle-nodavuvale-schema-btn" class="ml-2 px-4 py-2 text-sm float-right bg-green-400 text-white rounded" onclick="toggleVisibility('nodavuvale-schema-section', 'toggle-nodavuvale-schema-btn')">Show</button>
        </h2>
        <div class="mt-4 text-xs max-h-96 overflow-auto hidden" id="nodavuvale-schema-section">
            <?php
            // Show the NodaVuvale schema in a pretty way
            echo '<div class="schema-container">';
            foreach ($nodavuvaleSchema as $table => $columns) {
                echo '<div class="schema-table-container">';
                    echo '<h2 class="schema-table-header">' . htmlspecialchars($table) . '</h2>';
                    echo '<div class="schema-columns-container">';
                    foreach ($columns as $column => $type) {
                        echo '<div class="schema-column">';
                            echo '<span class="schema-column-name">' . htmlspecialchars($column) . '</span> ';
                            echo '<span class="schema-column-type">' . htmlspecialchars($type) . '</span>';
                        echo '</div>';
                    }
                    echo '</div>';
                echo '</div>';
            }
            echo '</div>';
            ?>
        </div>
    </div>
    <?php

    // The comparison checks table/column presence and SQL column types. Unknown
    // objects are deliberately review-only; this page never drops schema data.
    ?>
    <div class="mb-4 p-2 pb-4 border border-gray-500 rounded">
        <h2 class="text-xl font-bold">
            Schema Comparison
        </h2>
        <div class="mt-4" id="schema-comparison-section">
            <?php
            // Show the schema comparison
            echo '<div class="">';
            $showComparison = false;
            if(count($schemaComparison['tables_to_create']) == 0 && count($schemaComparison['columns_to_create']) == 0 && count($schemaComparison['redundant_tables']) == 0 && count($schemaComparison['redundant_columns']) == 0 && count($schemaComparison['column_type_mismatches']) == 0) {
                echo '<p class="text-center text-xl text-green-600">All official tables, columns, and column types are present, with no unrecognised tables or columns.</p>';
            } else if(count($schemaComparison['tables_to_create']) == 0 && count($schemaComparison['columns_to_create']) == 0 && count($schemaComparison['column_type_mismatches']) == 0) {
                echo '<p class="text-center text-xl text-amber-700">All required tables and columns are present. Unrecognised database objects are listed for review but cannot be removed from this page.</p>';
                $showComparison = true;
            } else {
                echo '<p class="text-center text-xl text-red-600">Required tables, columns, or column types differ from the official NodaVuvale schema. Review the details before changing the database.</p>';
                $showComparison = true;
            }
            if($showComparison) {
                // Tables to Create
                if (!empty($schemaComparison['tables_to_create'])) {
                    echo '<h3 class="text-lg font-bold">Tables to Create</h3>';
                    foreach ($schemaComparison['tables_to_create'] as $table => $columns) {
                        echo '<div class="mt-5 mb-5">';
                        echo '<form method="POST" action="index.php?to=admin/&section=database">';
                        echo '<input type="hidden" name="action" value="create_table">';
                        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($databaseAdminCsrf) . '">';
                        echo '<input type="hidden" name="table_name" value="' . htmlspecialchars($table) . '">';
                        echo '<p>';
                        echo '<button type="submit" class="float-right bg-blue-500 text-white px-4 py-2 rounded">Create Table</button>';
                        echo 'Table: <strong>' . htmlspecialchars($table) . '</strong></p>';
                        echo '</p>';
                        echo '</form>';
                        echo '</div>';
                    }
                }

                // Columns to Create
                if (!empty($schemaComparison['columns_to_create'])) {
                    echo '<h3 class="text-lg font-bold">Columns to Create</h3>';
                    foreach ($schemaComparison['columns_to_create'] as $table => $columns) {
                        foreach ($columns as $column => $type) {
                            echo '<div class="mt-5 mb-5">';
                            echo '<form method="POST" action="index.php?to=admin/&section=database">';
                            echo '<input type="hidden" name="action" value="add_column">';
                            echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($databaseAdminCsrf) . '">';
                            echo '<input type="hidden" name="table_name" value="' . htmlspecialchars($table) . '">';
                            echo '<input type="hidden" name="column_name" value="' . htmlspecialchars($column) . '">';
                            echo '<p>';
                            echo '<button type="submit" class="float-right bg-blue-500 text-white px-4 py-2 rounded">Add Column</button>';
                            echo 'Column: <strong>' . htmlspecialchars($column) . '</strong> in table <strong>' . htmlspecialchars($table) . '</strong></p>';
                            echo '</p>';
                            echo '</form>';
                            echo '</div>';
                            }
                    }
                }

                // Unrecognised tables are informational only. The official
                // schema can lag a feature, so deletion is never offered here.
                if (!empty($schemaComparison['redundant_tables'])) {
                    echo '<h3 class="text-lg font-bold">Unrecognised Tables</h3>';
                    foreach ($schemaComparison['redundant_tables'] as $table => $columns) {
                        echo '<div class="mt-3 mb-3 p-3 bg-amber-50 border border-amber-200 rounded">';
                        echo '<p>Table: <strong>' . htmlspecialchars($table) . '</strong></p>';
                        echo '<p class="text-sm text-amber-800">Review its origin and dependencies manually. No drop action is available.</p>';
                        echo '</div>';
                    }
                }

                // Unrecognised columns are also informational only.
                if (!empty($schemaComparison['redundant_columns'])) {
                    echo '<h3 class="text-lg font-bold">Unrecognised Columns</h3>';
                    foreach ($schemaComparison['redundant_columns'] as $table => $columns) {
                        foreach ($columns as $column => $type) {
                            echo '<div class="mt-3 mb-3 p-3 bg-amber-50 border border-amber-200 rounded">';
                            echo '<p>Column: <strong>' . htmlspecialchars($column) . '</strong> in table <strong>' . htmlspecialchars($table) . '</strong></p>';
                            echo '<p class="text-sm text-amber-800">Review its feature history before changing the official schema or database. No drop action is available.</p>';
                            echo '</div>';
                        }
                    }
                }

                if (!empty($schemaComparison['column_type_mismatches'])) {
                    echo '<h3 class="text-lg font-bold">Column Type Differences</h3>';
                    foreach ($schemaComparison['column_type_mismatches'] as $table => $columns) {
                        foreach ($columns as $column => $types) {
                            echo '<div class="mt-3 mb-3 p-3 bg-red-50 border border-red-200 rounded">';
                            echo '<p><strong>' . htmlspecialchars($table . '.' . $column) . '</strong></p>';
                            echo '<p class="text-sm">Installed: <code>' . htmlspecialchars($types['current']) . '</code>; official: <code>' . htmlspecialchars($types['official']) . '</code>.</p>';
                            echo '<p class="text-sm text-red-800">Type changes require a reviewed migration and are not applied automatically.</p>';
                            echo '</div>';
                        }
                    }
                }
            }
            echo '</div>';
            ?>
        </div>
    </div>

    <div class="mb-4 p-4 border border-purple-500 rounded" id="database-cleanup">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold">Database and Upload Cleanup</h2>
                <p class="mt-1 text-sm text-gray-700">
                    Dry-run scan generated <?= htmlspecialchars($cleanupScan['generated_at_display']) ?>.
                    Its action tokens expire at <?= htmlspecialchars($cleanupScan['expires_at_display']) ?> and every action rescans its target before changing anything.
                </p>
            </div>
            <a href="index.php?to=admin/&amp;section=database#database-cleanup" class="shrink-0 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded text-center">
                Run Fresh Scan
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
            <div class="p-3 bg-gray-50 border rounded">
                <div class="text-2xl font-bold"><?= (int) $cleanupScan['finding_count'] ?></div>
                <div class="text-sm text-gray-600">Findings</div>
            </div>
            <div class="p-3 bg-gray-50 border rounded">
                <div class="text-2xl font-bold"><?= (int) $cleanupScan['actionable_count'] ?></div>
                <div class="text-sm text-gray-600">Actionable after recheck</div>
            </div>
            <div class="p-3 bg-gray-50 border rounded">
                <div class="text-2xl font-bold"><?= (int) $cleanupScan['exclusions']['physical_file_count'] ?></div>
                <div class="text-sm text-gray-600">Physical uploads scanned</div>
            </div>
            <div class="p-3 bg-gray-50 border rounded">
                <div class="text-2xl font-bold"><?= (int) $cleanupScan['myisam_tables'] ?></div>
                <div class="text-sm text-gray-600">MyISAM tables</div>
            </div>
        </div>

        <div class="mt-4 p-4 bg-purple-50 border border-purple-200 rounded text-sm text-purple-950">
            <h3 class="font-bold text-base">How to process reviewed findings</h3>
            <ol class="mt-2 ml-5 list-decimal space-y-1">
                <li>Review the finding details and use any linked individual ID or file path to verify the record.</li>
                <li>Tick <strong>Select this reviewed action</strong> for every actionable row you want to handle in that section.</li>
                <li>Use the button at the bottom of the section to process all selected rows together.</li>
                <li>The selected actions run one at a time. Each is rechecked immediately before its change and receives its own recovery manifest.</li>
            </ol>
            <p class="mt-2"><strong>Selections do not cross sections, and a row without a selection checkbox cannot be cleaned automatically.</strong> Follow its review note instead.</p>
        </div>

        <?php if ((int) $cleanupScan['myisam_tables'] > 0): ?>
            <div class="mt-4 p-3 bg-amber-50 border border-amber-300 rounded text-amber-900">
                <strong>Recovery manifests are mandatory.</strong>
                MyISAM tables do not provide transactional rollback. Before each cleanup mutation, the affected rows are exported to
                <code><?= htmlspecialchars($cleanupScan['archive_root']) ?></code>.
                Physical files are moved into that archive's quarantine directory and are never permanently deleted here.
            </div>
        <?php endif; ?>

        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm text-blue-900">
            Intentionally excluded from orphan results:
            <strong><?= (int) $cleanupScan['exclusions']['empty_linked_items'] ?></strong> empty linked event placeholders,
            <strong><?= (int) $cleanupScan['exclusions']['standalone_file_links'] ?></strong> valid standalone file links, and
            <strong><?= (int) $cleanupScan['exclusions']['rich_text_reference_count'] ?></strong> physical file(s) referenced from rich text rather than a file table.
        </div>

        <div class="mt-5 space-y-4">
            <?php foreach ($cleanupScan['sections'] as $cleanupSection): ?>
                <?php
                    $cleanupBulkFormId = 'cleanup-bulk-' . preg_replace('/[^a-z0-9_-]/i', '-', $cleanupSection['id']);
                    $cleanupSectionActions = array_values(array_filter(array_map(
                        static fn(array $row): ?array => $row['action'] ?? null,
                        $cleanupSection['rows']
                    )));
                    $cleanupSectionActionNames = array_column($cleanupSectionActions, 'name');
                    $cleanupAllRemovalActions = $cleanupSectionActionNames !== [] && count(array_filter(
                        $cleanupSectionActionNames,
                        static fn(string $name): bool => str_contains($name, '_delete_') || str_contains($name, '_remove_')
                    )) === count($cleanupSectionActionNames);
                    $cleanupAllQuarantineActions = $cleanupSectionActionNames !== [] && count(array_filter(
                        $cleanupSectionActionNames,
                        static fn(string $name): bool => str_contains($name, '_quarantine_')
                    )) === count($cleanupSectionActionNames);
                    $cleanupBulkButtonLabel = $cleanupAllRemovalActions
                        ? 'Remove selected reviewed items'
                        : ($cleanupAllQuarantineActions ? 'Quarantine selected reviewed files' : 'Apply selected cleanup actions');
                    $cleanupBulkButtonClass = $cleanupAllRemovalActions
                        ? 'bg-red-600 hover:bg-red-700'
                        : ($cleanupAllQuarantineActions ? 'bg-amber-600 hover:bg-amber-700' : 'bg-purple-600 hover:bg-purple-700');
                ?>
                <section class="border rounded bg-white" id="<?= htmlspecialchars($cleanupSection['id']) ?>">
                    <div class="p-3 border-b bg-gray-50 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold"><?= htmlspecialchars($cleanupSection['title']) ?></h3>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($cleanupSection['description']) ?></p>
                            <div class="mt-3 space-y-2 text-sm">
                                <p class="p-2 border border-blue-200 rounded bg-blue-50 text-blue-900">
                                    <strong>Visibility and usefulness:</strong>
                                    <?= htmlspecialchars($cleanupSection['visibility']) ?>
                                </p>
                                <p class="p-2 border border-emerald-200 rounded bg-emerald-50 text-emerald-900">
                                    <strong>Recommended action:</strong>
                                    <?= htmlspecialchars($cleanupSection['recommendation']) ?>
                                </p>
                            </div>
                        </div>
                        <span class="shrink-0 px-2 py-1 rounded bg-gray-200 text-sm font-semibold"><?= count($cleanupSection['rows']) ?></span>
                    </div>

                    <?php if (empty($cleanupSection['rows'])): ?>
                        <p class="p-3 text-green-700">No findings.</p>
                    <?php else: ?>
                        <div class="divide-y max-h-96 overflow-auto">
                            <?php foreach ($cleanupSection['rows'] as $cleanupFindingIndex => $cleanupFinding): ?>
                                <?php
                                    $cleanupReview = $cleanupFinding['review'] ?? [];
                                    $cleanupIndividualIds = array_values(array_filter(
                                        array_map('intval', $cleanupReview['individual_ids'] ?? []),
                                        static fn(int $id): bool => $id > 0
                                    ));
                                    $cleanupFileReviewPath = isset($cleanupReview['file_path']) && is_string($cleanupReview['file_path'])
                                        ? $cleanupReview['file_path']
                                        : null;
                                    $cleanupFileReviewToken = $cleanupFileReviewPath !== null
                                        ? $cleanupService->issueFileReviewToken($cleanupFileReviewPath, $databaseAdminCsrf)
                                        : null;
                                    $cleanupFileReviewUrl = $cleanupFileReviewToken !== null
                                        ? 'admin_cleanup_file.php?path=' . rawurlencode($cleanupFileReviewPath) . '&amp;token=' . rawurlencode($cleanupFileReviewToken)
                                        : null;
                                    $cleanupPathShownInMetadata = array_key_exists('Path', $cleanupFinding['metadata']);
                                ?>
                                <article class="p-3">
                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
                                        <div class="min-w-0">
                                            <h4 class="font-semibold break-words">
                                                <?php if ($cleanupFileReviewUrl !== null && !$cleanupPathShownInMetadata): ?>
                                                    <a href="<?= $cleanupFileReviewUrl ?>" target="_blank" rel="noopener noreferrer" class="text-blue-700 underline hover:text-blue-900" title="Open or download this file in a new tab">
                                                        <?= htmlspecialchars($cleanupFinding['summary']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($cleanupFinding['summary']) ?>
                                                <?php endif; ?>
                                            </h4>
                                            <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-1 text-sm">
                                                <?php foreach ($cleanupFinding['metadata'] as $label => $value): ?>
                                                    <div class="min-w-0">
                                                        <dt class="inline font-semibold text-gray-600"><?= htmlspecialchars((string) $label) ?>:</dt>
                                                        <dd class="inline break-all">
                                                            <?php if (($label === 'Individual ID' || $label === 'Individuals') && $cleanupIndividualIds !== []): ?>
                                                                <?php foreach ($cleanupIndividualIds as $position => $cleanupIndividualId): ?><?= $position > 0 ? ', ' : '' ?><a href="index.php?to=family/individual&amp;individual_id=<?= $cleanupIndividualId ?>" target="_blank" rel="noopener noreferrer" class="text-blue-700 underline hover:text-blue-900" title="Review individual <?= $cleanupIndividualId ?> in a new tab"><?= $cleanupIndividualId ?></a><?php endforeach; ?>
                                                            <?php elseif ($label === 'Path' && $cleanupFileReviewUrl !== null): ?>
                                                                <a href="<?= $cleanupFileReviewUrl ?>" target="_blank" rel="noopener noreferrer" class="text-blue-700 underline hover:text-blue-900" title="Open or download this file in a new tab"><?= htmlspecialchars((string) $value) ?></a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars((string) $value) ?>
                                                            <?php endif; ?>
                                                        </dd>
                                                    </div>
                                                <?php endforeach; ?>
                                            </dl>
                                            <?php if (!empty($cleanupFinding['note'])): ?>
                                                <p class="mt-2 text-sm text-amber-800"><?= htmlspecialchars($cleanupFinding['note']) ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($cleanupFinding['action'])): ?>
                                            <?php
                                                $cleanupAction = $cleanupFinding['action'];
                                                $cleanupScanToken = $cleanupService->issueActionToken(
                                                    $cleanupAction['name'],
                                                    $cleanupAction['target'],
                                                    $databaseAdminCsrf,
                                                    $cleanupScan['generated_at']
                                                );
                                                $cleanupIsDestructiveAction = str_contains($cleanupAction['name'], '_delete_')
                                                    || str_contains($cleanupAction['name'], '_remove_');
                                                $cleanupSelectionValue = json_encode([
                                                    'action' => $cleanupAction['name'],
                                                    'target' => $cleanupAction['target'],
                                                    'scan_token' => $cleanupScanToken,
                                                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                                                $cleanupSelectionId = $cleanupBulkFormId . '-selection-' . $cleanupFindingIndex;
                                            ?>
                                            <div class="lg:w-72 shrink-0 p-3 <?= $cleanupIsDestructiveAction ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200' ?> border rounded">
                                                <h5 class="font-bold <?= $cleanupIsDestructiveAction ? 'text-red-950' : 'text-amber-950' ?>">Available cleanup action</h5>
                                                <p class="mt-1 mb-3 text-xs <?= $cleanupIsDestructiveAction ? 'text-red-900' : 'text-amber-900' ?>">
                                                    <?= htmlspecialchars($cleanupAction['label']) ?> — <?= htmlspecialchars($cleanupAction['confirmation']) ?>
                                                </p>
                                                <label for="<?= htmlspecialchars($cleanupSelectionId) ?>" class="flex items-start gap-2 text-sm font-semibold cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        id="<?= htmlspecialchars($cleanupSelectionId) ?>"
                                                        name="cleanup_bulk_selection[]"
                                                        value="<?= htmlspecialchars($cleanupSelectionValue, ENT_QUOTES) ?>"
                                                        form="<?= htmlspecialchars($cleanupBulkFormId) ?>"
                                                        class="mt-0.5"
                                                    >
                                                    <span>Select this reviewed action</span>
                                                </label>
                                            </div>
                                        <?php else: ?>
                                            <div class="lg:w-64 shrink-0 p-3 bg-gray-50 border border-gray-200 rounded text-sm text-gray-700">
                                                <strong>No automatic cleanup action</strong>
                                                <p class="mt-1 text-xs">This finding is protected or needs a manual repair decision. Review the note and linked details.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($cleanupSectionActions !== []): ?>
                            <div class="p-3 border-t bg-gray-50">
                                <form
                                    id="<?= htmlspecialchars($cleanupBulkFormId) ?>"
                                    method="POST"
                                    action="index.php?to=admin/&amp;section=database#<?= htmlspecialchars($cleanupSection['id']) ?>"
                                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                                    onsubmit="const selected = Array.from(this.elements).filter((element) => element.name === 'cleanup_bulk_selection[]' &amp;&amp; element.checked); if (selected.length === 0) { alert('Select at least one reviewed action in this section.'); return false; } return confirm('Process ' + selected.length + ' selected cleanup action' + (selected.length === 1 ? '' : 's') + '? Each action will be rechecked and receive its own recovery manifest.');"
                                >
                                    <input type="hidden" name="action" value="cleanup_bulk">
                                    <input type="hidden" name="confirm_cleanup" value="yes">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($databaseAdminCsrf) ?>">
                                    <div class="text-sm text-gray-700">
                                        <strong><?= count($cleanupSectionActions) ?> actionable finding<?= count($cleanupSectionActions) === 1 ? '' : 's' ?></strong>
                                        <span class="block text-xs">Only checked rows in this section will be processed.</span>
                                    </div>
                                    <button type="submit" class="<?= $cleanupBulkButtonClass ?> text-white font-semibold px-4 py-2 rounded">
                                        <?= htmlspecialchars($cleanupBulkButtonLabel) ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</section>
