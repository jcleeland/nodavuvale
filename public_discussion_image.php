<?php
require_once __DIR__ . '/system/config.php';
require_once __DIR__ . '/system/nodavuvale_database.php';
require_once __DIR__ . '/system/PublicDiscussions.php';
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox; default-src 'none'");
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    http_response_code(405); header('Allow: GET, HEAD'); exit;
}
$id = filter_var($_GET['discussion_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
// Use the configured virtual-host name, not a caller-supplied Host header, for absolute upload URLs.
$host = $_SERVER['SERVER_NAME'] ?? '';
$path = is_string($_GET['path'] ?? null) ? PublicArticleHtml::uploadPath($_GET['path'], $host) : null;
$access = new PublicDiscussions(Database::getInstance()->connection());
$article = $id ? $access->article($id) : null;
$image = $article && $path !== null && in_array($path, $access->images($article, $host), true)
    ? PublicDiscussions::imageFile($path, __DIR__) : null;
if (!$image) { http_response_code(404); exit('Image not found.'); }
header('Content-Type: ' . $image['mime']);
header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode(basename($image['file'])));
header('Content-Length: ' . filesize($image['file']));
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') { readfile($image['file']); }
