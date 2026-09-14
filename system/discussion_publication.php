<?php
if ($page !== 'communications/discussions' || $_SERVER['REQUEST_METHOD'] !== 'POST'
    || ($_POST['action'] ?? '') !== 'discussion_publication') { return; }
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
    http_response_code(403); exit('Administrator access required.');
}
if (!RequestSecurity::validToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403); exit('The form expired. Reload the page and try again.');
}
$id = filter_var($_POST['discussion_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$publish = ($_POST['published'] ?? '') === '1';
if (!$id || !in_array($_POST['published'] ?? '', ['0', '1'], true)
    || ($publish && ($_POST['privacy_review'] ?? '') !== '1')) {
    http_response_code(422); exit('Review the article and images for privacy implications before publishing.');
}
try {
    $publicDiscussions->publish($id, $publish, (int) $_SESSION['user_id']);
    $_SESSION['discussion_notice'] = $publish ? 'Article published publicly. Future edits and images are also public.' : 'Article is now private.';
} catch (Throwable $error) {
    error_log('Discussion publication: ' . $error->getMessage());
    $_SESSION['discussion_notice'] = 'Publication could not be changed. Check pending database migrations and the server log.';
}
header('Location: index.php?to=communications/discussions&discussion_id=' . $id . '#discussion_id_' . $id, true, 303);
exit;
