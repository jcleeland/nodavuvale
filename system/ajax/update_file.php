<?php
/** 
 * This ajax file will update a file entry
 *  - there's really only one thing to update - and that's file_description
 *  - otherwise it's all about deleting
 */
$response=array();
if(!isset($data['fileId']) || !isset($data['fileDescription'])) {
    $response['status']='error';
    $response['message']='No file ID or file Description provided';
}

// Update the files table with the new description
$fileId = $data['fileId'];
$fileDescription = $data['fileDescription'];
$sql = "UPDATE files SET file_description = ? WHERE id = ?";
$sqldata = array($fileDescription, $fileId);
$response['sql']=$sql;
$response['data']=$sqldata;
try {
    $db->update($sql, array_values($sqldata));
    $response['status']='success';
    $response['message']='File description updated successfully';
} catch (Exception $e) {
    $response['status']='error';
    $response['message']='Error updating file description';
}