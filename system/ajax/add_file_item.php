<?php
// Initialize variables for the individual, event, and file
//   - If there's no "event type" or "event detail" provided, set them to empty strings

$individual_id = (int)$data['individual_id']; // Ensure proper integer casting
$events = !empty($data['events']) ? $data['events'] : [];
$file_description = !empty($data['file_description']) ? trim($data['file_description']) : '';
$user_id = (int)$_SESSION['user_id']; // Ensure proper integer casting
$group_id = !empty($data['item_identifier']) ? $data['item_identifier'] : null;
$new_item_identifier = Utils::getNextItemIdentifier();
$item_styles=Utils::getItemStyles();

$mediaDateRaw = $data['media_date'] ?? null;
if (is_string($mediaDateRaw)) {
    $decoded = json_decode($mediaDateRaw, true);
    if (is_array($decoded)) {
        $mediaDateRaw = $decoded;
    }
}
if (!is_array($mediaDateRaw)) {
    $mediaDateRaw = [
        'year' => $data['media_date_year'] ?? null,
        'month' => $data['media_date_month'] ?? null,
        'day' => $data['media_date_day'] ?? null,
        'approx' => $data['media_date_is_approximate'] ?? null,
    ];
}
[$mediaDateValue, $mediaDatePrecision, $mediaDateApprox] = Utils::prepareFlexibleDateFromArray($mediaDateRaw);

$linkDateRaw = $data['link_date'] ?? null;
if (is_string($linkDateRaw)) {
    $decoded = json_decode($linkDateRaw, true);
    if (is_array($decoded)) {
        $linkDateRaw = $decoded;
    }
}
if (!is_array($linkDateRaw)) {
    $linkDateRaw = [
        'year' => $data['link_date_year'] ?? null,
        'month' => $data['link_date_month'] ?? null,
        'day' => $data['link_date_day'] ?? null,
        'approx' => $data['link_date_is_approximate'] ?? null,
    ];
}
if (empty(array_filter($linkDateRaw))) {
    $linkDateRaw = $mediaDateRaw;
}
[$linkDateValue, $linkDatePrecision, $linkDateApprox] = Utils::prepareFlexibleDateFromArray($linkDateRaw);

/**
 * Initialize 
 *  - the response array which is returned at the end of this process
 *  - the filelink_item_ids array which is used to store the relevant file links after they're created
 *  - the item_ids array which is used to store the relevant item ids after they're created
 */
$response=[];
$filelink_item_ids=[];
$item_ids=[];

/**
 * Set up basic SQL queries used in later process
 */
$event_insert_sql = "INSERT INTO items (detail_type, detail_value, item_identifier, user_id) VALUES (?, ?, ?, ?)";
$event_update_sql = "UPDATE items SET detail_value = ? WHERE item_id = ?";
$checksql = "SELECT item_id FROM items WHERE item_identifier = ? AND detail_type = ?";


/**
 * If there is a file attachment, first make sure that everything has arrived OK,
 * then move the file to the appropriate directory and save the file path to the database
 * and create the relevant links to the individual and any events that are provided.
 * 
 */
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $response['log'][] = 'File upload detected';
    //Find out if this is an image or a document
    $file_type = $data['file']['type'];
    $file_type = explode('/', $file_type);
    $file_type = $file_type[0];
    if($file_type !== 'image' && $file_type !== 'document') {
        $file_type = 'document';
    }
    $response['log'][] = 'File type detected as '.$file_type;
    $upload_dir = 'uploads/' . ($file_type === 'image' ? 'images/' : 'documents/');
    
    // Generate a unique file name and save the file
    $file_name = basename($_FILES['file']['name']);
    $file_path = $upload_dir . uniqid() . '_' . $file_name;
    $file_format = pathinfo($file_name, PATHINFO_EXTENSION);
    $response['log'][] = 'File path set: '.$file_path;
    
    // Move the file to the designated directory
    if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
        $response['log'][] = 'File moved to '.$file_path;
        try {
            // Begin transaction
            $db->beginTransaction();
            $event_count = count($events);
            $item_id = null;  // Initialize the item_id to null in case there's no event

            if (!empty($events) && is_array($events)) {
                $response['log'][] = 'Events array detected';
                if($event_count < 2) {
                    $new_item_identifier = null; // Default scenario for a single item is that there is no need for a new item_identifier
                }
                
                foreach($events as $event) {
                    $response['log'][] = 'Processing event: '.json_encode($event);
                    $event_type = $event['event_type'];         //eg "Date"
                    $event_detail = $event['event_detail'];     //eg "2000-01-01"
                    
                    if(isset($event['item_identifier']) && !empty($event['item_identifier'])) {
                        $response['log'][] = 'Item identifier detected so this is part of a group: '.$event['item_identifier'];
                        /** This is a new item for an existing event/items group
                         *  - so we need to check if this item_identifier already exists in the database
                         *  - if it does, we update the item with the new event_detail
                         *  - if it doesn't, we create a new item
                         */
                        $checkparams = [$event['item_identifier'], $event_detail];
                        $checkresults = $db->fetchOne($checksql, $checkparams); //Have a look and see if there is already an item with this item_identifier and event_type
                        
                        if(is_array($checkresults) && count($checkresults) > 0) {
                            $updateItemId = $checkresults['item_id'];
                            $db->update($event_update_sql, [$file_description, $updateItemId]);
                            $filelink_item_ids[]=$updateItemId;                            
                        } else {
                            $db->insert($event_insert_sql, [$event_type, $file_description, $event['item_identifier'], $user_id]);
                            $this_item_id = $db->lastInsertId();
                            $item_ids[]=$this_item_id;
                            $filelink_item_ids[]=$this_item_id;
                        }
                    } else {
                        $response['log'][] = 'No item identifier detected so this is a single item event';
                        $item_identifier = $new_item_identifier;
                        $db->insert($event_insert_sql, [$event_type, $event_detail, $item_identifier, $user_id]);
                        $this_item_id = $db->lastInsertId(); // Get the ID of the inserted event
                        $item_ids[]=$this_item_id;
                        $response['log'][] = 'A new Item ID has been generated: '.$this_item_id;
                        if($item_styles[str_replace(" ", "_", $event_type)] == "file") {
                            $response['log'][] = 'This is a file type event so we need to link the file to this item';
                            $filelink_item_ids[]=$this_item_id;
                            $response['log'][] = 'File link added to filelink_item_ids array ('.$this_item_id.')';
                        }
                    }
                }

                //If there are multiple events, then also add an entry to "item_groups" linking the $item_identifier with
                // the group event name ($data['event_group_name'])
                if(isset($data['event_group_name']) && $event_count > 1 && !isset($event['item_identifier'])) {
                    $event_group_name = $data['event_group_name'];
                    $event_group_sql = "INSERT INTO item_groups (group_name, item_identifier) VALUES (?, ?)";
                    $db->insert($event_group_sql, [$event_group_name, $new_item_identifier]);
                }
                

                // Finally, add a record into 'item_links' to link the event to the individual
                $event_link_sql = "INSERT INTO item_links (individual_id, item_id) VALUES (?, ?)";
                foreach($item_ids as $item_id) {
                    $db->insert($event_link_sql, [$individual_id, $item_id]);
                }
            }
            
            // Insert the file metadata into the 'files' table
            $response['log'][] = 'Inserting file metadata into the files table';
            $file_insert_sql = "INSERT INTO files (file_type, file_path, file_format, file_description, user_id, media_date, media_date_precision, media_date_is_approximate) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $db->insert($file_insert_sql, [$file_type, $file_path, $file_format, $file_description, $user_id, $mediaDateValue, $mediaDatePrecision, $mediaDateApprox]);
            $file_id = $db->lastInsertId(); // Get the ID of the uploaded file
            $response['sql']['file_insert_sql'][] = "INSERT INTO files (file_type, file_path, file_format, file_description, user_id, media_date, media_date_precision, media_date_is_approximate) VALUES ('$file_type', '$file_path', '$file_format', '$file_description', $user_id, " . ($mediaDateValue ? "'$mediaDateValue'" : 'NULL') . ", " . ($mediaDatePrecision ? "'$mediaDatePrecision'" : 'NULL') . ", $mediaDateApprox)";
            $file_link_sql = "INSERT INTO file_links (file_id, individual_id, item_id, link_date, link_date_precision, link_date_is_approximate) VALUES (?, ?, ?, ?, ?, ?)";
            if(empty($filelink_item_ids)) { //If this is a file that isn't associated with an event, better create a file link
                $response['log'][] = 'No file link items detected so this is a standalone file - creating a file link anyway with the file_id as '.$file_id.' and the individual_id as '.$individual_id.'';
                $response['sql']['file_link_sql'][] = "INSERT INTO file_links (file_id, individual_id, item_id, link_date, link_date_precision, link_date_is_approximate) VALUES ($file_id, $individual_id, NULL, " . ($linkDateValue ? "'$linkDateValue'" : 'NULL') . ", " . ($linkDatePrecision ? "'$linkDatePrecision'" : 'NULL') . ", $linkDateApprox)";
                $db->insert($file_link_sql, [$file_id, $individual_id, null, $linkDateValue, $linkDatePrecision, $linkDateApprox]);
            } else {
                // Link the file to the individual in 'file_links'
                foreach($filelink_item_ids as $filelink_item_id) {
                    $response['sql']['file_link_sql'][] = "INSERT INTO file_links (file_id, individual_id, item_id, link_date, link_date_precision, link_date_is_approximate) VALUES ($file_id, $individual_id, $filelink_item_id, " . ($linkDateValue ? "'$linkDateValue'" : 'NULL') . ", " . ($linkDatePrecision ? "'$linkDatePrecision'" : 'NULL') . ", $linkDateApprox)";
                    $db->insert($file_link_sql, [$file_id, $individual_id, $filelink_item_id, $linkDateValue, $linkDatePrecision, $linkDateApprox]); // item_id can be null
                }
            }
            
            // Commit transaction
            $db->commit();
            
            // Return success response
            $response['status'] = 'success';
            $response['filepath'] = $file_path;
            $response['message'] = 'File uploaded, linked to individual, and '.$event_count.' event(s) recorded successfully.';
        } catch (Exception $e) {
            // Roll back the transaction in case of error
            $db->rollBack();
            error_log($e->getMessage());
            $response['status'] = 'error';
            $response['message'] = 'Error processing the request. ('.$e->getMessage().')';
        }
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Failed to upload file.';
    }
} 


else if(!isset($_FILES['file'])) {
    $response['log'][] = 'No file detected in the $_FILES array';
    try {
        // Begin transaction
        $db->beginTransaction();
        $event_count = count($events);

        /**
         * There are some different ways of submitting the data through AJAX
         * so this next line is just to make sure that either way, it is presented
         * in the same format for this script to process.
         */
        $response['POSTData']=json_encode($_POST);
        $response['log'][] = "POST data received: ".json_encode($_POST);
        
        $item_id = null;  // Initialize the item_id to null in case there's no event
        
        if (!empty($events) && is_array($events)) {
            if($event_count < 2) {
                $new_item_identifier = null; // Default scenario for a single item is that there is no need for a new item_identifier
            }

            foreach($events as $event) {
                $response['eventArray'][]=json_encode($event);
                $event_type = $event['event_type'];
                $event_detail = $event['event_detail'];
                
                if(isset($event['item_identifier']) && !empty($event['item_identifier'])) {
                    $checkparams = [$event['item_identifier'], $event_type];
                    $checkresults = $db->fetchOne($checksql, $checkparams);
                    if(is_array($checkresults) && count($checkresults) > 0) {
                        $updateItemId = $checkresults['item_id'];
                        $db->update($event_update_sql, [$event_detail, $updateItemId]);
                        $item_ids[]=$updateItemId;                            
                    } else {
                        $db->insert($event_insert_sql, [$event_type, $event_detail, $event['item_identifier'], $user_id]);
                        $item_ids[]=$db->lastInsertId();
                    }
                } else {
                    $item_identifier = $new_item_identifier;
                    $db->insert($event_insert_sql, [$event_type, $event_detail, $item_identifier, $user_id]);
                    $item_ids[] = $db->lastInsertId();
                }
            }

            // Insert into 'item_links' to link the event to the individual
            $event_link_sql = "INSERT INTO item_links (individual_id, item_id) VALUES (?, ?)";
            foreach($item_ids as $item_id) {
                $db->insert($event_link_sql, [$individual_id, $item_id]);
            }
        }
        
        // Commit transaction
        $db->commit();
        
        // Return success response
        $response['status'] = 'success';
        $response['message'] = $event_count.' event(s) recorded successfully.';

    } catch (Exception $e) {
        
        // Roll back the transaction in case of error
        $db->rollBack();
        error_log($e->getMessage());
        $response['status'] = 'error';
        $response['message'] = 'Error processing the request. ('.$e->getMessage().')';
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'No file uploaded or file upload error.';
}

?>
