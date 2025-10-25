<?php
// system/ajax/react_to_item.php
$response = ['success' => false];

$itemId = isset($data['item_id']) ? (int) $data['item_id'] : 0;
$itemIdentifier = isset($data['item_identifier']) ? (int) $data['item_identifier'] : null;
$reaction = isset($data['reaction']) ? trim((string) $data['reaction']) : '';
$userId = $_SESSION['user_id'] ?? 0;

if ($itemId > 0 && $userId > 0 && $reaction !== '') {
    $existing = $db->fetchOne(
        "SELECT id FROM item_reactions WHERE item_id = ? AND user_id = ?",
        [$itemId, $userId]
    );

    if ($existing) {
        $db->query(
            "UPDATE item_reactions SET reaction_type = ?, reacted_at = NOW() WHERE id = ?",
            [$reaction, $existing['id']]
        );
    } else {
        $db->insert(
            "INSERT INTO item_reactions (item_id, item_identifier, user_id, reaction_type) VALUES (?, ?, ?, ?)",
            [$itemId, $itemIdentifier, $userId, $reaction]
        );
    }

    $response['success'] = true;
} else {
    $response['error'] = 'Invalid reaction request';
}