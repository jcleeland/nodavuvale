
<?php
include('system/config.php');
require 'vendor/autoload.php';

$classMap = [
    'Database' => __DIR__ . '/system/nodavuvale_database.php',
    'Auth' => __DIR__ . '/system/nodavuvale_auth.php',
    'Web' => __DIR__ . '/system/nodavuvale_web.php',
    'Utils' => __DIR__ . '/system/nodavuvale_utils.php',
];

spl_autoload_register(function($class) use ($classMap) {
    if (isset($classMap[$class])) {
        require_once $classMap[$class];
    }
});

$db = Database::getInstance();
$web = new Web($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $discussion_id = $_POST['discussion_id'] ?? null;
    $comment_id = $_POST['comment_id'] ?? null;

    $uploadedFiles = $web->handleFileUpload($_FILES['files'], $discussion_id, $comment_id);

    if ($uploadedFiles) {
        echo json_encode(['success' => true, 'files' => $uploadedFiles]);
    } else {
        echo json_encode(['success' => false, 'error' => 'File upload failed.']);
    }
}
?>