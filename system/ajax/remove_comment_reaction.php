<?php
// system/ajax/remove_comment_reaction.php
$response = ['success' => false];

$comment_id = $data['comment_id'] ?? 0;
$user_id = $data['user_id'] ?? 0;

if ($comment_id && $user_id) {
    // Remove the user's reaction to the comment
    $result = $db->query("DELETE FROM comment_reactions WHERE comment_id = ? AND user_id = ?", [$comment_id, $user_id]);

    if ($result) {
        $response['success'] = true;
    } else {
        $response['error'] = 'Failed to remove reaction';
    }
} else {
    $response['error'] = 'Invalid comment ID or user ID';
}
