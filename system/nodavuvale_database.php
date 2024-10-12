<?php
// system/nodavuvale_database.php

class Database {
    private $pdo;
    private static $instance = null;

    // Singleton pattern to avoid multiple instances
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    private function __construct() {
        $host = DB_HOST;
        $db = DB_NAME;
        $user = DB_USER;
        $pass = DB_PASS;
        
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // Log error (in a real app, avoid exposing the error message to users)
            error_log($e->getMessage());
            die("Database connection failed.");
        }
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            // Log query errors
            error_log($e->getMessage());
            return false;
        }
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        if ($stmt) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }    

    // Transaction handling methods
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollBack() {
        return $this->pdo->rollBack();
    }

    // Insert helper method
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId(); // Return the last inserted ID
    }

    // Update helper method
    public function update($sql, $params = []) {
        return $this->query($sql, $params)->rowCount(); // Return number of affected rows
    }

    public function getSiteSettings() {
        $settings = $this->fetchAll("SELECT * FROM site_settings");
        $site_settings = [];
        foreach ($settings as $setting) {
            $site_settings[$setting['name']] = $setting['value'];
        }
        return $site_settings;
    }

    public function updateSiteSettings($site_name, $site_description, $email_server, $email_username, $email_password, $email_port) {
        $this->update("UPDATE site_settings SET value = ? WHERE name = 'site_name'", [$site_name]);
        $this->update("UPDATE site_settings SET value = ? WHERE name = 'site_description'", [$site_description]);
        $this->update("UPDATE site_settings SET value = ? WHERE name = 'email_server'", [$email_server]);
        $this->update("UPDATE site_settings SET value = ? WHERE name = 'email_username'", [$email_username]);
        $this->update("UPDATE site_settings SET value = ? WHERE name = 'email_password'", [$email_password]);
        $this->update("UPDATE site_settings SET value = ? WHERE name = 'email_port'", [$email_port]);
    }

    // Function to read MySQL dump file and extract schema
    public function readDumpFile($filePath) {
        $fileContents = file_get_contents($filePath);

        $tables = [];
        preg_match_all('/CREATE TABLE `(.+?)` \((.+?)\);/s', $fileContents, $matches);

        for ($i = 0; $i < count($matches[1]); $i++) {
            $tableName = $matches[1][$i];
            $columns = [];

            // Extract columns
            preg_match_all('/`(.+?)` ([^,]+),/', $matches[2][$i], $colMatches);
            for ($j = 0; $j < count($colMatches[1]); $j++) {
                $columns[$colMatches[1][$j]] = trim($colMatches[2][$j]);
            }

            $tables[$tableName] = $columns;
        }

        return $tables;
    }

    /*************************************************************
     * Database Management Functions
    **************************************************************/
    // Read NodaVuvale database setup file and extract table and column information
    public function getNodaVuvaleSchema($filePath = "system/nodavuvale.sql") {
        // Step 1: Load the file contents
        $fileContents = file_get_contents($filePath);
    
        // Step 2: Normalize line endings (replace all types of newlines with "\n")
        $fileContents = str_replace(["\r\n", "\r"], "\n", $fileContents);
    
        // Step 3: Extract CREATE TABLE blocks
        $tables = [];
        
        // Use a simple regex to capture CREATE TABLE and everything inside parentheses
        preg_match_all('/CREATE TABLE `(.+?)` \(([\s\S]+?)\)\s*ENGINE=[^;]+;/', $fileContents, $matches, PREG_SET_ORDER);
    
        // Step 4: Process each CREATE TABLE block
        foreach ($matches as $match) {
            $tableName = $match[1]; // Extracted table name
            $tableBody = $match[2]; // Extracted table column definitions
            
            $columns = [];
            
            // Step 5: Break the table body into lines and process each line
            $lines = explode("\n", $tableBody);
            foreach ($lines as $line) {
                $line = trim($line);
    
                // Match each line that defines a column
                if (preg_match('/^`(.+?)`\s+(.+)$/', $line, $colMatch)) {
                    $columnName = $colMatch[1];
                    $columnDefinition = rtrim($colMatch[2], ','); // Remove trailing comma if present
                    $columns[$columnName] = $columnDefinition;
                }
            }
    
            // Store the table and its columns
            $tables[$tableName] = $columns;
        }
    
        // Step 6: Return the structured array of tables and columns
        return $tables;
    }
    
    // Function to get current database schema
    public function getCurrentDatabaseSchema() {
        $tables = [];

        $result = $this->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $tableName = array_values($row)[0];
            $tables[$tableName] = $this->getTableColumns($tableName);
        }

        return $tables;
    }

    // Function to get table columns
    public function getTableColumns($tableName) {
        $columns = [];
        $result = $this->query("SHOW COLUMNS FROM `$tableName`");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $columns[$row['Field']] = $row['Type'];
        }

        return $columns;
    }



    // Function to compare two schemas and generate SQL
    public function compareSchemas($currentSchema, $dumpSchema) {
        $differences = [
            'tables_to_create' => [],
            'columns_to_create' => [],
            'redundant_tables' => [],
            'redundant_columns' => []
        ];

        // 1. Identify tables to create and missing columns in existing tables
        foreach ($dumpSchema as $table => $columns) {
            if (!isset($currentSchema[$table])) {
                // Table is missing, generate SQL to create the whole table
                $createTableSQL = $this->generateCreateTableSQL($table, $columns);
                $differences['tables_to_create'][$table] = $createTableSQL;
            } else {
                // Table exists, check for missing columns
                foreach ($columns as $colName => $colType) {
                    if (!isset($currentSchema[$table][$colName])) {
                        // Column is missing, generate SQL to add the column
                        $addColumnSQL = $this->generateAddColumnSQL($table, $colName, $colType);
                        $differences['columns_to_create'][$table][$colName] = $addColumnSQL;
                    }
                }
            }
        }

        // 2. Identify redundant tables and columns
        foreach ($currentSchema as $table => $columns) {
            if (!isset($dumpSchema[$table])) {
                // Table exists in current schema but not in dump, mark as redundant
                $dropTableSQL = $this->generateDropTableSQL($table);
                $differences['redundant_tables'][$table] = $dropTableSQL;
            } else {
                // Check for redundant columns
                foreach ($columns as $colName => $colType) {
                    if (!isset($dumpSchema[$table][$colName])) {
                        // Column exists in current schema but not in dump, mark as redundant
                        $dropColumnSQL = $this->generateDropColumnSQL($table, $colName);
                        $differences['redundant_columns'][$table][$colName] = $dropColumnSQL;
                    }
                }
            }
        }

        return $differences;
    }

    // Function to generate the SQL for creating a table
    function getCreateTableSQL($tableName) {
        global $nodavuvaleSchema;
        $columns = $nodavuvaleSchema[$tableName];
        $columnDefs = [];
        foreach ($columns as $column => $definition) {
            $columnDefs[] = "`$column` $definition";
        }
        return "CREATE TABLE `$tableName` (" . implode(", ", $columnDefs) . ")";
    }

    // Function to generate the SQL for adding a column
    function getAddColumnSQL($tableName, $columnName) {
        global $nodavuvaleSchema;
        $columnDefinition = $nodavuvaleSchema[$tableName][$columnName];
        return "ALTER TABLE `$tableName` ADD COLUMN `$columnName` $columnDefinition";
    }

    // Helper function to generate SQL for creating a new table
    private function generateCreateTableSQL($table, $columns) {
        $sql = "CREATE TABLE `$table` (\n";
        $colDefinitions = [];

        foreach ($columns as $colName => $colType) {
            $colDefinitions[] = "`$colName` $colType";
        }

        $sql .= implode(",\n", $colDefinitions);
        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"; // Adjust based on your schema's default engine/charset
        return $sql;
    }

    // Helper function to generate SQL for adding a new column to an existing table
    private function generateAddColumnSQL($table, $column, $definition) {
        return "ALTER TABLE `$table` ADD `$column` $definition;";
    }

    // Helper function to generate SQL for dropping a redundant table
    private function generateDropTableSQL($table) {
        return "DROP TABLE IF EXISTS `$table`;";
    }

    // Helper function to generate SQL for dropping a redundant column
    private function generateDropColumnSQL($table, $column) {
        return "ALTER TABLE `$table` DROP COLUMN `$column`;";
    }


    // Function to export database structure
    public function exportDatabaseStructure($filePath) {
        $tables = $this->getCurrentDatabaseSchema();
        $sqlDump = '';

        foreach ($tables as $table => $columns) {
            $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
            $sqlDump .= "CREATE TABLE `$table` (\n";
            $colDefinitions = [];
            foreach ($columns as $colName => $colType) {
                $colDefinitions[] = "`$colName` $colType";
            }
            $sqlDump .= implode(",\n", $colDefinitions) . "\n";
            $sqlDump .= ");\n\n";
        }

        file_put_contents($filePath, $sqlDump);
    }

    // Function to export database data only
    public function exportDatabaseData($filePath) {
        $tables = $this->fetchAll("SHOW TABLES");
        $sqlDump = '';

        foreach ($tables as $tableRow) {
            $table = array_values($tableRow)[0];
            $rows = $this->fetchAll("SELECT * FROM `$table`");

            if (!empty($rows)) {
                $sqlDump .= "INSERT INTO `$table` VALUES\n";

                $rowStrings = [];
                foreach ($rows as $row) {
                    $values = array_map(function ($value) {
                        return is_null($value) ? "NULL" : $this->pdo->quote($value);
                    }, $row);

                    $rowStrings[] = "(" . implode(", ", $values) . ")";
                }

                $sqlDump .= implode(",\n", $rowStrings) . ";\n\n";
            }
        }

        file_put_contents($filePath, $sqlDump);
    }
}
