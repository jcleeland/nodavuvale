<?php
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/nodavuvale_database.php';
require_once __DIR__ . '/nodavuvale_auth.php';
require_once __DIR__ . '/nodavuvale_web.php';
require_once __DIR__ . '/nodavuvale_utils.php';
Web::startSession();
$db = Database::getInstance();
$auth = new Auth($db);
$web = new Web($db);
if (!$auth->isLoggedIn()) {
    http_response_code(403);
    header('Cache-Control: private, no-store');
    exit('Member access required.');
}
