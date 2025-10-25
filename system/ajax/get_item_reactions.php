<?php
// system/ajax/get_item_reactions.php
$response = ['success' => false];

$itemId = isset($data['item_id']) ? (int) $data['item_id'] : 0;

$reactionSummary = [
    'like'  => 0,
    'love'  => 0,
    'haha'  => 0,
    'wow'   => 0,
    'sad'   => 0,
    'angry' => 0,
    'care'  => 0,
];

if ($itemId > 0) {
    $rows = $db->fetchAll(
        "SELECT reaction_type, COUNT(*) AS reaction_count FROM item_reactions WHERE item_id = ? GROUP BY reaction_type",
        [$itemId]
    );

    foreach ($rows as $row) {
        $type = strtolower((string) $row['reaction_type']);
        if ($type === '') {
            continue;
        }
        if (!array_key_exists($type, $reactionSummary)) {
            $reactionSummary[$type] = 0;
        }
        $reactionSummary[$type] = (int) $row['reaction_count'];
    }

    $response['success'] = true;
    $response['reactions'] = $reactionSummary;
} else {
    $response['error'] = 'Invalid item ID';
    $response['reactions'] = $reactionSummary;
}
