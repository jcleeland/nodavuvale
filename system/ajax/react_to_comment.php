<?php
// system/ajax/react_to_comment.php
$response = ['success' => false];

$reaction = $data['reaction'] ?? '';
$user_id = $data['user_id'] ?? 0;
$comment_id = $data['comment_id'] ?? 0;

if ($reaction && $user_id && $comment_id) {
    // Check if the user has already reacted
    $existingReaction = $db->fetchOne("SELECT * FROM comment_reactions WHERE user_id = ? AND comment_id = ?", [$user_id, $comment_id]);

    if ($existingReaction) {
        // Update the reaction
        $db->query("UPDATE comment_reactions SET reaction_type = ? WHERE id = ?", [$reaction, $existingReaction['id']]);
    } else {
        // Add a new reaction
        $db->insert("INSERT INTO comment_reactions (comment_id, user_id, reaction_type) VALUES (?, ?, ?)", [$comment_id, $user_id, $reaction]);
    }

    $response['success'] = true;
}


