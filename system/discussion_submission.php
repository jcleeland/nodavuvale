<?php
// Run before the shared header so a successful POST can issue one HTTP redirect.
if (!in_array($page, ['communications/discussions', 'communications/newdiscussion'], true)) { return; }
$discussionError = '';
$discussionNotice = $_SESSION['discussion_notice'] ?? '';
unset($_SESSION['discussion_notice']);
$discussionDraft = ['title' => '', 'content' => '', 'event_date' => '', 'event_date_finish' => '',
    'event_location' => '', 'is_sticky' => false, 'is_event' => false, 'is_historical_event' => false, 'is_news' => false];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { return; }
if (!$auth->isLoggedIn()) { http_response_code(403); exit('Member access required.'); }
if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $discussionError = 'The server could not receive this submission. It may exceed the upload size limit. Please try a smaller attachment.';
    http_response_code(413);
    return;
}
if ($page !== 'communications/newdiscussion' && !isset($_POST['new_discussion'])) { return; }
foreach ($discussionDraft as $field => $default) {
    $discussionDraft[$field] = is_bool($default) ? isset($_POST[$field])
        : (is_string($_POST[$field] ?? null) ? $_POST[$field] : '');
}
if (!RequestSecurity::validToken($_POST['csrf_token'] ?? null)) {
    $discussionError = 'Your form has expired. Your text is still here; please submit it again.';
    http_response_code(403);
    return;
}
require_once __DIR__ . '/DiscussionCreation.php';
try {
    $discussionId = (new DiscussionCreation($db->connection()))->create(
        $discussionDraft, (int) $_SESSION['user_id'], $page === 'communications/newdiscussion');
} catch (InvalidArgumentException $error) {
    $discussionError = $error->getMessage();
    http_response_code(422);
    return;
} catch (Throwable $error) {
    $reference = bin2hex(random_bytes(4));
    error_log('Discussion creation [' . $reference . ']: ' . $error->getMessage());
    $discussionError = 'The discussion could not be saved. Your text is still here. Please try again or contact an administrator (reference ' . $reference . ').';
    http_response_code(500);
    return;
}

// Attachments are attempted only after a confirmed insert. A failed upload must not prompt a duplicate post.
$discussionNotice = 'Discussion posted.';
$files = $_FILES['discussion_files'] ?? null;
if (is_array($files) && is_array($files['name'] ?? null)) {
    $expectedUploads = count(array_filter($files['error'] ?? [], static fn($error) => $error !== UPLOAD_ERR_NO_FILE));
    if ($expectedUploads > 0) {
        try {
            $uploaded = $web->handleDiscussionFileUpload($files, $discussionId, (int) $_SESSION['user_id']);
            if (count($uploaded) !== $expectedUploads) {
                $discussionNotice .= ' Some attachments could not be uploaded. Add them to the saved discussion; do not submit it again.';
            }
        } catch (Throwable $error) {
            error_log('Discussion attachment upload: ' . $error->getMessage());
            $discussionNotice .= ' Attachments could not be uploaded. Add them to the saved discussion; do not submit it again.';
        }
    }
}
$_SESSION['discussion_notice'] = $discussionNotice;
header('Location: index.php?to=communications/discussions&discussion_id=' . $discussionId, true, 303);
exit;
