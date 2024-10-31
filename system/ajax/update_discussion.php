<?php
// system/ajax/update_discussion.php
$response = ['success' => false];

if(!isset($_SESSION['user_id'])) {
    $response['message'] = 'You must be logged in to view reactions.';
    echo json_encode($response);
    exit;
}

if(!isset($data['discussion_id'])) {
    $response['message'] = 'No discussion ID provided.';
    echo json_encode($response);
    exit;
}

// Get the discussion ID from the request data
$discussion_id = $data['discussion_id'];

// Get the user_id of the discussion frm the database
$discussion = $db->fetchOne("SELECT user_id FROM discussions WHERE id = ?", [$discussion_id]);

//Check that the current user matches the user_id of the discussion, or is an admin
if($discussion['user_id'] != $_SESSION['user_id'] && $auth->getUserRole() != 'admin') {
    $response['message'] = 'You do not have permission to modify this discussion.';
    echo json_encode($response);
    exit;
}

// Do it all in a try/catch
try {
    // Update the discussion with the new title and content
    $db->query("UPDATE discussions 
                SET title = ?, content = ? 
                WHERE id = ?", 
                [$data['title'], $data['content'], $discussion_id]
            );

    $response['success'] = true;
    $response['message'] = 'Discussion updated successfully.';
} catch (Exception $e) {
    $response['message'] = 'An error occurred while updating the discussion.';
}


