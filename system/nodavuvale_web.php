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
    
    // Add more utility functions, e.g., session validation, CSRF protection, etc.
}
