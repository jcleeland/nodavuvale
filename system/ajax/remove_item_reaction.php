<?php
// system/ajax/remove_item_reaction.php
$response = ['success' => false];

$itemId = isset($data['item_id']) ? (int) $data['item_id'] : 0;
$userId = $_SESSION['user_id'] ?? 0;

if ($itemId > 0 && $userId > 0) {
    $db->query(
        "DELETE FROM item_reactions WHERE item_id = ? AND user_id = ?",
        [$itemId, $userId]
    );
    $response['success'] = true;
} else {
    $response['error'] = 'Invalid reaction removal request';
}
*** End Patch
