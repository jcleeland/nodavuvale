<?php
// Initialize variables for the individual, event, and file

//If there's no "event type" or "event detail" provided, set them to empty strings

$individual_id = (int)$data['individual_id']; // Ensure proper integer casting
$events = !empty($data['events']) ? $data['events'] : [];
$file_description = !empty($data['file_description']) ? trim($data['file_description']) : '';
$user_id = (int)$_SESSION['user_id']; // Ensure proper integer casting

// Initialize the response array

$response=array();

// Just in case we may need to create a new event, let's find the next consecutive item_identifier
$sql = "SELECT COALESCE(MAX(item_identifier), 0) + 1 AS new_item_identifier FROM items";
$result = $db->fetchOne($sql);
$new_item_identifier = $result['new_item_identifier'];

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
            $event_count = count($events);

            // Step 0: Check to see if this already has an entry in the items table
            

            // Step 1: Insert the event into the 'items' table (if event data is provided)
            $item_id = null;  // Initialize the item_id to null in case there's no event
            if (!empty($events) && is_array($events)) {
                //how many events are there?
                
                //if there's only one event, then the new_item_identifier should be null
                $event_insert_sql = "INSERT INTO items (detail_type, detail_value, item_identifier) VALUES (?, ?, ?)";
                
                if($event_count < 2) {
                    $new_item_identifier = null;
                }
                $item_ids=[];
                foreach($events as $event) {
                    $event_type = $event['event_type'];
                    $event_detail = $event['event_detail'];
                    $item_identifier = $new_item_identifier;
                    $db->insert($event_insert_sql, [$event_type, $event_detail, $item_identifier]);
                    $item_ids[] = $db->lastInsertId(); // Get the ID of the inserted event
                }

                //If there are multiple events, then also add an entry to "item_groups" linking the $item_identifier with
                // the group event name ($data['event_group_name'])
                if(isset($data['event_group_name']) && $event_count > 1) {
                    $event_group_name = $data['event_group_name'];
                    $event_group_sql = "INSERT INTO item_groups (group_name, item_identifier) VALUES (?, ?)";
                    $db->insert($event_group_sql, [$event_group_name, $new_item_identifier]);
                }
                

                // Step 2: Insert into 'item_links' to link the event to the individual
                $event_link_sql = "INSERT INTO item_links (individual_id, item_id) VALUES (?, ?)";
                foreach($item_ids as $item_id) {
                    $db->insert($event_link_sql, [$individual_id, $item_id]);
                }
            }
            
            // Step 3: Insert the file metadata into the 'files' table
            $file_insert_sql = "INSERT INTO files (file_type, file_path, file_format, file_description, user_id) 
                                VALUES (?, ?, ?, ?, ?)";
            $db->insert($file_insert_sql, [$file_type, $file_path, $file_format, $file_description, $user_id]);
            $file_id = $db->lastInsertId(); // Get the ID of the uploaded file
            
            // Step 4: Link the file to the individual in 'file_links'
            $file_link_sql = "INSERT INTO file_links (file_id, individual_id, item_id) VALUES (?, ?, ?)";
            $db->insert($file_link_sql, [$file_id, $individual_id, $item_id]); // item_id can be null
            
            // Commit transaction
            $db->commit();
            
            // Return success response
            $response= ['status' => 'success', 'filepath' => $file_path, 'message' => 'File uploaded, linked to individual, and '.$event_count.' event(s) recorded successfully.'];
        } catch (Exception $e) {
            // Roll back the transaction in case of error
            $db->rollBack();
            error_log($e->getMessage());
            $response=['status' => 'error', 'message' => 'Error processing the request.'];
        }
    } else {
        $response=['status' => 'error', 'message' => 'Failed to upload file.'];
    }
} else if(!isset($_FILES['file'])) {
    //This is simply the creation of an event (or events) without a file
    try {
        
        // Begin transaction
        $db->beginTransaction();
        
        // Step 1: Insert the event into the 'items' table (if event data is provided)
        $item_id = null;  // Initialize the item_id to null in case there's no event
        if (!empty($events) && is_array($events)) {
            //how many events are there?
            $event_count = count($events);
            //if there's only one event, then the new_item_identifier should be null
            $event_insert_sql = "INSERT INTO items (detail_type, detail_value, item_identifier) VALUES (?, ?, ?)";
            //Now iterate through $events
            if($event_count < 2) {
                $new_item_identifier = null;
            }
            $item_ids=[];
            foreach($events as $event) {
                $event_type = $event['event_type'];
                $event_detail = $event['event_detail'];
                $item_identifier = $new_item_identifier;
                $db->insert($event_insert_sql, [$event_type, $event_detail, $item_identifier]);
                $item_ids[] = $db->lastInsertId(); // Get the ID of the inserted event
            }

            // Step 2: Insert into 'item_links' to link the event to the individual
            $event_link_sql = "INSERT INTO item_links (individual_id, item_id) VALUES (?, ?)";
            foreach($item_ids as $item_id) {
                $db->insert($event_link_sql, [$individual_id, $item_id]);
            }
        }
        
        // Commit transaction
        $db->commit();
        
        // Return success response
        $response= ['status' => 'success', 'message' => $event_count.' event(s) recorded successfully.'];
    } catch (Exception $e) {
        // Roll back the transaction in case of error
        $db->rollBack();
        error_log($e->getMessage());
        $response=['status' => 'error', 'message' => 'Error processing the request.'];
    }
} else {
    $response=['status' => 'error', 'message' => 'No file uploaded or file upload error.'];
}

?>
