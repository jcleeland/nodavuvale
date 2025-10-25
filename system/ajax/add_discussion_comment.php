<?php
// system/ajax/add_discussion_comment.php
$response = ['success' => false];

$discussionId = isset($data['discussion_id']) ? (int) $data['discussion_id'] : 0;
$comment = isset($data['comment']) ? trim((string) $data['comment']) : '';
$userId = $_SESSION['user_id'] ?? 0;

if ($discussionId > 0 && $userId > 0 && $comment !== '') {
    $commentId = $db->insert(
        "INSERT INTO discussion_comments (discussion_id, user_id, comment, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())",
        [$discussionId, $userId, $comment]
    );

    if ($commentId) {
        $user = Utils::getUser($userId);
        $response['success'] = true;
        $response['comment'] = [
            'id'            => $commentId,
            'discussion_id' => $discussionId,
            'user_id'       => $userId,
            'first_name'    => $user['first_name'] ?? '',
            'last_name'     => $user['last_name'] ?? '',
            'avatar'        => $user['avatar'] ?? 'images/default_avatar.webp',
            'comment'       => $comment,
            'created_at'    => date('Y-m-d H:i:s'),
        ];
    } else {
        $response['error'] = 'Failed to create comment';
    }
} else {
    $response['error'] = 'Invalid comment submission';
}
