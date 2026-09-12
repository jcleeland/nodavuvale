<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/system/config.php';
require __DIR__ . '/system/nodavuvale_database.php';
require __DIR__ . '/system/nodavuvale_auth.php';
require __DIR__ . '/system/nodavuvale_web.php';
require __DIR__ . '/system/admin/DatabaseCleanupService.php';

Web::startSession();

$db = Database::getInstance();
$auth = new Auth($db);

function cleanupFileReviewError(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
    cleanupFileReviewError(403, 'Administrator access is required.');
}

$relativePath = isset($_GET['path']) ? (string) $_GET['path'] : '';
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$csrfToken = isset($_SESSION['admin_database_csrf']) ? (string) $_SESSION['admin_database_csrf'] : '';
$cleanupService = new DatabaseCleanupService($db, __DIR__);
$absolutePath = $cleanupService->resolveFileReviewPath($relativePath, $token, $csrfToken);

if ($absolutePath === null) {
    cleanupFileReviewError(404, 'The file is missing, unsafe, or the review link is no longer valid. Reload the cleanup page and try again.');
}

$mimeType = 'application/octet-stream';
if (class_exists('finfo')) {
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedType = $fileInfo->file($absolutePath);
    if (is_string($detectedType) && $detectedType !== '') {
        $mimeType = $detectedType;
    }
}

$inlineTypes = [
    'application/pdf',
    'image/avif',
    'image/bmp',
    'image/gif',
    'image/jpeg',
    'image/png',
    'image/webp',
    'text/plain',
];
$disposition = in_array($mimeType, $inlineTypes, true) ? 'inline' : 'attachment';
$filename = basename(str_replace('\\', '/', $relativePath));
$fallbackFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'cleanup-file';

session_write_close();
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: ' . $disposition . '; filename="' . $fallbackFilename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Cache-Control: private, no-store');
header('Content-Security-Policy: sandbox; default-src \'none\'');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

readfile($absolutePath);
exit;
