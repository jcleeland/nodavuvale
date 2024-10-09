<?php
// system/ajax/get_discussion_reactions.php
$response = ['success' => false];

$discussion_id = $data['discussion_id'] ?? 0;

if ($discussion_id) {
    $reactions = $db->fetchAll("SELECT reaction_type, COUNT(*) as count FROM discussion_reactions WHERE discussion_id = ? GROUP BY reaction_type", [$discussion_id]);

    $reactionSummary = [
        'like' => 0,
        'love' => 0,
        'haha' => 0,
        'wow' => 0
    ];

    foreach ($reactions as $reaction) {
        $reactionSummary[$reaction['reaction_type']] = $reaction['count'];
    }

    $response['success'] = true;
    $response['reactions'] = $reactionSummary;
}

