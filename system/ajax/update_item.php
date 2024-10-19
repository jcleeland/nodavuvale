<?php
/** 
 * This ajax file will update a file entry
 *  - there's really only one thing to update - and that's file_description
 *  - otherwise it's all about deleting
 */
$response=array();
if(!isset($data['itemId']) || !isset($data['itemDescription'])) {
    $response['status']='error';
    $response['message']='No file ID or file Description provided';
}

// Update the files table with the new description
$itemId = $data['itemId'];
$itemDescription = $data['itemDescription'];
$sql = "UPDATE items SET detail_value = ? WHERE item_id = ?";
$sqldata = array($itemDescription, $itemId);
$response['sql']=$sql;
$response['data']=$sqldata;
try {
    $db->update($sql, array_values($sqldata));
    $response['status']='success';
    $response['message']='Item description updated successfully';
} catch (Exception $e) {
    $response['status']='error';
    $response['message']='Error updating item description';
}