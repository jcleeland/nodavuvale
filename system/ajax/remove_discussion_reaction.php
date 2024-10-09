<?php
// system/ajax/remove_discussion_reactions.php
$response = ['success' => false];

$discussion_id = $data['discussion_id'] ?? 0;
$user_id = $data['user_id'] ?? 0;

if ($discussion_id && $user_id) {
    // Remove the user's reaction to the discussion
    $result = $db->query("DELETE FROM discussion_reactions WHERE discussion_id = ? AND user_id = ?", [$discussion_id, $user_id]);

    if ($result) {
        $response['success'] = true;
    } else {
        $response['error'] = 'Failed to remove reaction';
    }
} else {
    $response['error'] = 'Invalid discussion ID or user ID';
}