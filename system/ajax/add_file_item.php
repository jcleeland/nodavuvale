<?php
// Initialize variables for the individual, event, and file

$individual_id = (int)$data['individual_id']; // Ensure proper integer casting
$event_type = trim($data['event_type']);
$event_detail = trim($data['event_detail']);
$file_description = trim($data['file_description']);

$response=array();

// Process the uploaded file
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    //Find out if this is an image or a document
    $file_type = $data['file']['type'];
    $file_type = explode('/', $file_type);
    $file_type = $file_type[0];

    // if the file is an image, save it to uploads/images, otherwise to uploads/documents
    $upload_dir = 'uploads/' . ($file_type === 'image' ? 'images/' : 'documents/');
    
    // Generate a unique file name and save the file
    $file_name = basename($_FILES['file']['name']);
    $file_path = $upload_dir . uniqid() . '_' . $file_name;
    $file_format = pathinfo($file_name, PATHINFO_EXTENSION);
    
    // Move the file to the designated directory
    if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Step 1: Insert the event into the 'items' table (if event data is provided)
            $item_id = null;  // Initialize the item_id to null in case there's no event
            if (!empty($event_type) && !empty($event_detail)) {
                $event_insert_sql = "INSERT INTO items (detail_type, detail_value) VALUES (?, ?)";
                $db->insert($event_insert_sql, [$event_type, $event_detail]);
                $item_id = $db->lastInsertId(); // Get the ID of the inserted event

                // Step 2: Insert into 'item_links' to link the event to the individual
                $event_link_sql = "INSERT INTO item_links (individual_id, item_id) VALUES (?, ?)";
                $db->insert($event_link_sql, [$individual_id, $item_id]);
            }
            
            // Step 3: Insert the file metadata into the 'files' table
            $file_insert_sql = "INSERT INTO files (file_type, file_path, file_format, file_description) 
                                VALUES (?, ?, ?, ?)";
            $db->insert($file_insert_sql, [$file_type, $file_path, $file_format, $file_description]);
            $file_id = $db->lastInsertId(); // Get the ID of the uploaded file
            
            // Step 4: Link the file to the individual in 'file_links'
            $file_link_sql = "INSERT INTO file_links (file_id, individual_id, item_id) VALUES (?, ?, ?)";
            $db->insert($file_link_sql, [$file_id, $individual_id, $item_id]); // item_id can be null
            
            // Commit transaction
            $db->commit();
            
            // Return success response
            $response= ['status' => 'success', 'filepath' => $file_path, 'message' => 'File uploaded, linked to individual, and event recorded successfully.'];
        } catch (Exception $e) {
            // Roll back the transaction in case of error
            $db->rollBack();
            error_log($e->getMessage());
            $response=['status' => 'error', 'message' => 'Error processing the request.'];
        }
    } else {
        $response=['status' => 'error', 'message' => 'Failed to upload file.'];
    }
} else {
    $response=['status' => 'error', 'message' => 'No file uploaded or file upload error.'];
}

?>
