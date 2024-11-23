<?php
// system/ajax/get_discussion_reactions.php
$response = ['success' => false];
$response['status'] = 'error';

$discussion_id = $data['discussion_id'] ?? 0;
//$response['data'] = $data;
$response['discussion_id'] = $discussion_id;

if ($discussion_id) {
    $response['sql']="SELECT * FROM discussions WHERE id = ?";
    $discussion = $db->fetchAll("SELECT * FROM discussions WHERE id = ?", [$discussion_id]);
    $response['status'] = 'success';
    $response['success'] = true;
    $response['discussion'] = $discussion[0];
}

