<?php
// system/ajax/update_discussion.php
$response = ['success' => false];

if(!isset($data['fileDescription'])) {
    $response['message'] = 'No file description.';
    echo json_encode($response);
    exit;
}

if(!isset($data['fileId'])) {
    $response['message'] = 'No file ID.';
    echo json_encode($response);
    exit;
}


// Get the discussion ID from the request data
$fileId = $data['fileId'];
$fileDescription = $data['fileDescription'];

// Do it all in a try/catch
try {
    // Update the discussion with the new title and content
    $db->query("UPDATE discussion_files 
                SET file_description = ? 
                WHERE id = ?", 
                [$fileDescription, $fileId]
            );

    $response['success'] = true;
    $response['status'] = 'success';
    $response['message'] = 'File description updated successfully.';
} catch (Exception $e) {
    $response['message'] = 'An error occurred while updating the file description.';
}


