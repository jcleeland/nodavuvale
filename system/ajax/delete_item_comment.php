<?php
// system/ajax/delete_item_comment.php
$response = ['success' => false];

$commentId = isset($data['comment_id']) ? (int) $data['comment_id'] : 0;
$userId = $_SESSION['user_id'] ?? 0;
$userRole = $auth->getUserRole();

if ($commentId > 0 && $userId > 0) {
    $comment = $db->fetchOne(
        "SELECT user_id FROM item_comments WHERE id = ?",
        [$commentId]
    );

    if ($comment && ($comment['user_id'] == $userId || $userRole === 'admin')) {
        $db->query("DELETE FROM item_comments WHERE id = ?", [$commentId]);
        $response['success'] = true;
    } else {
        $response['error'] = 'Not authorised to delete this comment';
    }
} else {
    $response['error'] = 'Invalid comment deletion request';
}
