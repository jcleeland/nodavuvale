<?php
// system/ajax/react_to_discussion.php
$response = ['success' => false];

$reaction = $data['reaction'] ?? '';
$user_id = $data['user_id'] ?? 0;
$discussion_id = $data['discussion_id'] ?? 0;

if ($reaction && $user_id && $discussion_id) {
    // Check if the user has already reacted
    $existingReaction = $db->fetchOne("SELECT * FROM discussion_reactions WHERE user_id = ? AND discussion_id = ?", [$user_id, $discussion_id]);

    if ($existingReaction) {
        // Update the reaction
        $db->query("UPDATE discussion_reactions SET reaction_type = ? WHERE id = ?", [$reaction, $existingReaction['id']]);
    } else {
        // Add a new reaction
        $db->insert("INSERT INTO discussion_reactions (discussion_id, user_id, reaction_type) VALUES (?, ?, ?)", [$discussion_id, $user_id, $reaction]);
    }

    $response['success'] = true;
}


