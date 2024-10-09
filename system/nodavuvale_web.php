<?php
// system/Web.php

class Web {
    
    public static function redirect($url) {
        header("Location: $url");
        exit();
    }

    public static function startSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function checkLogin() {
        self::startSession();
        if (!isset($_SESSION['user_id'])) {
            self::redirect('index.php?page=login');
        }
    }

    public static function getRootId() {
        // Set the root id for the tree
        // If none has been set, default to 1
        $rootId=1;

        // If the user has a set preferred root id, use that instead
        if(isset($_SESSION['preferred_root_id'])) {
            $rootId = $_SESSION['preferred_root_id'];
        }
        // If the user has requested a different root id, use that instead
        if(isset($_GET['root_id'])) {
            $rootId = $_GET['root_id'];
        }
        if(isset($_POST['root_id'])) {
            $rootId = $_POST['root_id'];
        }
        return $rootId;
    }   
    
    // Add more utility functions, e.g., session validation, CSRF protection, etc.
}
