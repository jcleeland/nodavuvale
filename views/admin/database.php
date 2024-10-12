<?php
/**
 * Database management page
 *   Check to see if the database matches the current schema (located in settings/nodavuvale.sql)
 *   using the functions in the Database class
 */

    $currentSchema = $db->getCurrentDatabaseSchema();
    $nodavuvaleSchema = $db->getNodavuvaleSchema();
    // Perform actions based on the form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $tableName = $_POST['table_name'] ?? null;
        $columnName = $_POST['column_name'] ?? null;

        switch ($action) {
            case 'create_table':
                if ($tableName) {
                    $sql = $db->getCreateTableSQL($tableName); // Generate SQL to create table
                    if ($db->query($sql)) {
                        $message = "Table '$tableName' created successfully.";
                    } else {
                        $message = "Error creating table '$tableName'.";
                    }
                }
                break;

            case 'add_column':
                if ($tableName && $columnName) {
                    $sql = $db->getAddColumnSQL($tableName, $columnName); // Generate SQL to add column
                    if ($db->query($sql)) {
                        $message = "Column '$columnName' added to table '$tableName' successfully.";
                    } else {
                        $message = "Error adding column '$columnName'.";
                    }
                }
                break;

            case 'remove_table':
                if ($tableName) {
                    $sql = "DROP TABLE IF EXISTS `$tableName`";
                    if ($db->query($sql)) {
                        $message = "Table '$tableName' removed successfully.";
                    } else {
                        $message = "Error removing table '$tableName'.";
                    }
                }
                break;

            case 'remove_column':
                if ($tableName && $columnName) {
                    $sql = "ALTER TABLE `$tableName` DROP COLUMN `$columnName`";
                    if ($db->query($sql)) {
                        $message = "Column '$columnName' removed from table '$tableName' successfully.";
                    } else {
                        $message = "Error removing column '$columnName'.";
                    }
                }
                break;

            default:
                $message = "Invalid action.";
                break;
        }

        // Store the message in the session or directly display it on the page
        echo '<div class="text-center p-4 bg-green-100 text-green-700 rounded">' . htmlspecialchars($message) . '</div>';
    }
?>
<section class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h1 class="text-4xl font-bold mb-6">Database Management</h1>
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

    //Now compare the two schemas using the Database class "compareSchemas" method
    $schemaComparison = $db->compareSchemas($currentSchema, $nodavuvaleSchema);
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
            if(count($schemaComparison['tables_to_create']) == 0 && count($schemaComparison['columns_to_create']) == 0 && count($schemaComparison['redundant_tables']) == 0 && count($schemaComparison['redundant_columns']) == 0) {
                echo '<p class="text-center text-xl text-green-500">The database schema completely matches the current schema</p>';
            } else if(count($schemaComparison['tables_to_create']) == 0 && count($schemaComparison['columns_to_create']) == 0) {
                echo '<p class="text-center text-xl text-green-500">The database schema matches the current schema enough to operate however there are some redudant database tables or columns that can be removed.</p>';
                $showComparison = true;
            } else {
                echo '<p class="text-center text-xl text-red-500">The database schema does not match the current schema and your NodaVuvale site will not work correctly until you correct this problem.</p>';
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

                // Redundant Tables
                if (!empty($schemaComparison['redundant_tables'])) {
                    echo '<h3 class="text-lg font-bold">Tables to Remove</h3>';
                    foreach ($schemaComparison['redundant_tables'] as $table => $columns) {
                        echo '<div class="mt-5 mb-5">';
                        echo '<form method="POST" action="index.php?to=admin/&section=database">';
                        echo '<input type="hidden" name="action" value="remove_table">';
                        echo '<input type="hidden" name="table_name" value="' . htmlspecialchars($table) . '">';
                        echo '<p>';
                        echo '<button type="submit" class="float-right bg-red-500 text-white px-4 py-2 rounded">Remove Table</button>';
                        echo 'Table: <strong>' . htmlspecialchars($table) . '</strong>';
                        echo '</p>';
                        echo '</form>';
                        echo '</div>';
                    }
                }

                // Redundant Columns
                if (!empty($schemaComparison['redundant_columns'])) {
                    echo '<h3 class="text-lg font-bold">Columns to Remove</h3>';
                    foreach ($schemaComparison['redundant_columns'] as $table => $columns) {
                        foreach ($columns as $column => $type) {
                            echo '<div class="mt-5 mb-5">';
                            echo '<form method="POST" action="index.php?to=admin/&section=database">';
                            echo '<input type="hidden" name="action" value="remove_column">';
                            echo '<input type="hidden" name="table_name" value="' . htmlspecialchars($table) . '">';
                            echo '<input type="hidden" name="column_name" value="' . htmlspecialchars($column) . '">';
                            echo '<p>';
                            echo '<button type="submit" class="float-right bg-red-500 text-white px-4 py-2 rounded">Remove Column</button>';
                            echo 'Column: <strong>' . htmlspecialchars($column) . '</strong> in table <strong>' . htmlspecialchars($table) . '</strong></p>';
                            echo '</p>';
                            echo '</form>';
                            echo '</div>';
                            }
                    }
                }
            }
            echo '</div>';
            ?>
        </div>
    </div>
</section>
