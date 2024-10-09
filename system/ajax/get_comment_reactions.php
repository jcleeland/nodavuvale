<?php
// system/ajax/get_discussion_reactions.php
$response = ['success' => false];

$comment_id = $data['comment_id'] ?? 0;

if ($comment_id) {
    $reactions = $db->fetchAll("SELECT reaction_type, COUNT(*) as count FROM comment_reactions WHERE comment_id = ? GROUP BY reaction_type", [$comment_id]);

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
