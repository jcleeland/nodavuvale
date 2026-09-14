<?php
// The web server must route /uploads/* here; see docs/public-access.md.
require __DIR__ . '/system/member_bootstrap.php';
header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox; default-src 'none'");
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
$requestPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = realpath(__DIR__ . '/uploads');
$file = str_starts_with($requestPath, '/uploads/') && !str_contains($requestPath, "\0")
    ? realpath(__DIR__ . $requestPath) : false;
if (!$root || !$file || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('File not found.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
$inline = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true);
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode(basename($file)));
header('Content-Length: ' . filesize($file));
session_write_close();
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') { readfile($file); }
