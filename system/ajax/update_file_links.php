<?php
/**
 * This file updates an individual file_links entry in the database
 * it requires at least the file_link_id to be provided
 * 
 * It then iterates through the $data['updates'] array to set  the new values
 */
$response=array();
if(!isset($data['file_link_id'])) {
    $response['status']='error';
    $response['message']='No file link ID provided';
} else {
    $file_link_id = $data['file_link_id'];
    $updates = $data['updates'];
    $sqldata = array();
    //iterate through the data array for the keys and values
    foreach($updates as $key => $value) {
        //If the value is "null", then set it to null
        if($value === 'null') {
            $updates[$key] = null;
        } else {
            $updates[$key] = trim($value);
        }
    }
    //Generate the SQL query to update the individual's details
    $sql = "UPDATE file_links SET ";
    $set = [];
    foreach($updates as $key => $value) {
        if($key !== 'file_link_id') {
            $set[] = "$key = ?";
            $sqldata[] = $value;
        }
    }
    $sql .= implode(', ', $set);
    $sql .= " WHERE id = ?";
    $sqldata[]=$file_link_id;
    //Execute the query
    $response['sql']=$sql;
    $response['data']=$sqldata;
    try {
        $db->update($sql, array_values($sqldata));
        $response['status']='success';
        $response['message']='File link details updated successfully';
    } catch (Exception $e) {
        $response['status']='error';
        $response['message']='Error updating file link details';
    }

}