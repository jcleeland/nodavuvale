<?php
/** 
 * This ajax file will update a file entry
 *  - there's really only one thing to update - and that's file_description
 *  - otherwise it's all about deleting
 */
$response=array();
if(!isset($data['fileId'])) {
    $response['status']='error';
    $response['message']='No file ID provided';
}
// Update the files table with the new description
$fileId = $data['fileId'];

//A file can also be connected to an item, and if this is the case, it should never be deleted from this page
// - it should only be deleted as part of deleting the item
$sql = "SELECT * FROM file_links WHERE file_id = ? and item_id IS NOT NULL";
$sqldata = array($fileId);
$itemId = $db->fetchAll($sql, $sqldata);


if(!empty($itemId)) {
    //implode the itemids into a string
    $itemId = array_column($itemId, 'item_id');
    $itemids = implode(',', $itemId);
    $response['status']='error';
    $response['message']='This file is linked to an item and cannot be deleted separately. Look for the associated item instead.';
} else {
    //Now we can delete the file.
    // Find the file path
    $sql = "SELECT file_path FROM files WHERE id = ?";
    $file_path = $db->fetchOne($sql, [$fileId]);
    try {
        // Set up a $db rollback so we can perform all the deletions in one go and cancel if any fail
        $db->beginTransaction();
        // Delete the file_links entries
    
        $sql = "DELETE FROM files WHERE id = ?";
        $db->delete($sql, [$fileId]);

        $sql = "DELETE FROM file_links WHERE file_id = ?";
        $db->delete($sql, [$fileId]);


        //Final step is to delete the actual file from the server
        if(file_exists($file_path['file_path'])) {
            if(!unlink($file_path['file_path'])) {
                throw new Exception("Failed to delete file from server (".$file_path['file_path'].")");
            }
        } else {
            throw new Exception("File does not exist on the server  (".$file_path['file_path'].")");
        }

        // If we've got this far, then we can commit the transaction
        $db->commit();


        $response['status']="success";
        $response['message']="File has been deleted successfully";

    } catch (Exception $e) {
        // Roll back the transaction if any of the deletions fail
        $db->rollBack();
        // Handle the exception (e.g., log the error, display an error message)
        error_log("Error deleting items: " . $e->getMessage());
        $response['status']="error";
        $response['message']= "An error occurred while deleting items. Please try again later. (".$e->getMessage().")";
    }    
}



