<?php
/**
 * Database management page
 *   Check to see if the database matches the current schema (located in settings/nodavuvale.sql)
 *   using the functions in the Database class
 */


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
    <?php
    $nodavuvaleSchema = $db->getNodavuvaleSchema();
    ?>    
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
        <div class="mt-4 text-xs" id="schema-comparison-section">
            <?php
            // Show the schema comparison in a pretty way
            echo '<div class="">';
            if(count($schemaComparison['tables_to_create'])==0 && count($schemaComparison['columns_to_create']) == 0) {
                echo '<p class="text-center text-xl text-green-500">The database schema matches the current schema</p>';
            } else {
                echo '<p class="text-xl text-red-500">The database schema does not match the current schema</p>';
                foreach ($schemaComparison as $table => $columns) {
                    echo '<div class="schema-table-container">';
                        echo '<h2 class="schema-table-header">' . htmlspecialchars($table) . '</h2>';
                        echo '<div class="schema-columns-container">';
                        foreach ($columns as $column => $type) {
                            echo '<div class="schema-column">';
                                echo '<span class="schema-column-name">' . $column . '</span> ';
                                echo '<span class="schema-column-type">' . $type . '</span>';
                            echo '</div>';
                        }
                        echo '</div>';
                    echo '</div>';
                }    
            }
            echo '</div>';
            ?>
        </div>
    </div>
</section>
