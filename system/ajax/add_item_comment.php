<?php
// system/ajax/add_item_comment.php
$response = ['success' => false];

$itemId = isset($data['item_id']) ? (int) $data['item_id'] : 0;
$itemIdentifier = isset($data['item_identifier']) ? (int) $data['item_identifier'] : null;
$comment = isset($data['comment']) ? trim((string) $data['comment']) : '';
$userId = $_SESSION['user_id'] ?? 0;

if ($itemId > 0 && $userId > 0 && $comment !== '') {
    $commentId = $db->insert(
        "INSERT INTO item_comments (item_id, item_identifier, user_id, comment, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
        [$itemId, $itemIdentifier, $userId, $comment]
    );

    if ($commentId) {
        $user = Utils::getUser($userId);
        $response['success'] = true;
        $response['comment'] = [
            'id'             => $commentId,
            'item_id'        => $itemId,
            'item_identifier'=> $itemIdentifier,
            'user_id'        => $userId,
            'first_name'     => $user['first_name'] ?? '',
            'last_name'      => $user['last_name'] ?? '',
            'avatar'         => $user['avatar'] ?? 'images/default_avatar.webp',
            'comment'        => $comment,
            'created_at'     => date('Y-m-d H:i:s'),
        ];
    } else {
        $response['error'] = 'Failed to create comment';
    }
} else {
    $response['error'] = 'Invalid comment submission';
}
