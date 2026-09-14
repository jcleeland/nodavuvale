<?php
// Included by index.php before any HTML, title lookup, or member-page handlers.
require_once __DIR__ . '/PublicAccess.php';
require_once __DIR__ . '/RequestSecurity.php';
require_once __DIR__ . '/PublicDiscussions.php';
$publicDiscussions = new PublicDiscussions($db->connection());
$publicArticle = null;
$publicAccess = new PublicAccess($db->connection());
$publicView = false;
$publicPreview = false;
$publicPerson = null;
if ($page === 'public/discussion') {
    $publicView = true;
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie');
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        http_response_code(405); header('Allow: GET, HEAD'); exit('This page is read-only.');
    }
    $id = filter_var($_GET['discussion_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $publicArticle = $id ? $publicDiscussions->article($id) : null;
    $pagePath = $publicArticle ? 'views/public/discussion.php' : 'views/public/unavailable.php';
    if (!$publicArticle) { http_response_code(404); }
}
if ($page === 'home' && !$auth->isLoggedIn()) {
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie');
}
$publicRoute = in_array($page, ['public/ancestors', 'public/individual'], true)
    || ($page === 'family/individual' && !$auth->isLoggedIn());
if ($publicRoute) {
    $publicView = true;
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie');
    $publicPreview = isset($_GET['preview']) && $_GET['preview'] === '1' && $auth->getUserRole() === 'admin';
    if ($publicPreview) { header('X-Robots-Tag: noindex, nofollow'); }
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        http_response_code(405);
        header('Allow: GET, HEAD');
        exit('This page is read-only.');
    }
    $publicUnavailable = !$publicAccess->ready() || (!$publicPreview && !$publicAccess->enabled());
    if ($page !== 'public/ancestors' && !$publicUnavailable) {
        $id = filter_var($_GET['individual_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $publicPerson = $id ? $publicAccess->person($id, $publicPreview) : null;
        $publicUnavailable = $publicPerson === null;
    }
    if ($publicUnavailable) {
        http_response_code(404);
        $pagePath = 'views/public/unavailable.php';
    } else {
        $pagePath = $page === 'public/ancestors' ? 'views/public/ancestors.php' : 'views/public/individual.php';
    }
}
