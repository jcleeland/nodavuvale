<?php
// system/ajax/delete_discussion_comment.php
$response = ['success' => false];

$commentId = isset($data['comment_id']) ? (int) $data['comment_id'] : 0;
$userId = $_SESSION['user_id'] ?? 0;
$userRole = $auth->getUserRole();

if ($commentId > 0 && $userId > 0) {
    $comment = $db->fetchOne(
        "SELECT user_id FROM discussion_comments WHERE id = ?",
        [$commentId]
    );

    if ($comment && ($comment['user_id'] == $userId || $userRole === 'admin')) {
        $db->beginTransaction();
        try {
            $db->query("DELETE FROM comment_reactions WHERE comment_id = ?", [$commentId]);
            $db->query("DELETE FROM discussion_comments WHERE id = ?", [$commentId]);
            $db->commit();
            $response['success'] = true;
        } catch (Exception $e) {
            $db->rollBack();
            $response['error'] = 'Failed to delete comment';
        }
    } else {
        $response['error'] = 'Not authorised to delete this comment';
    }
} else {
    $response['error'] = 'Invalid comment deletion request';
}
