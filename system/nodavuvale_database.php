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
}
