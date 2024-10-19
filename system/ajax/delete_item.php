<?php
/** 
 * This ajax file will update a file entry
 *  - there's really only one thing to update - and that's file_description
 *  - otherwise it's all about deleting
 */
$response=array();
if(!isset($data['itemId'])) {
    $response['status']='error';
    $response['message']='No item ID provided';
}
// Update the files table with the new description
$itemId = $data['itemId'];

//An item/event can have multiple entries in the items table, so we need to find out all the connected ones.
// it can also have links to files, via the file_links table

//First, let's get all the item_ids that are linked to this item_id. Additional item_ids are connected via the item_identifier field & item_groups table
// - if the item_identifier is null, then it's a single event. So first let's see if the item_identifier is null
$sql = "SELECT item_identifier, item_id FROM items WHERE item_id = ?";
$sqldata = array($itemId);
$item_identifier = $db->fetchOne($sql, $sqldata);
$item_identifier = $item_identifier['item_identifier'];

//If the item_identifier is not null, then we need to find all the item_ids that have the same item_identifier
if($item_identifier !== null) {
    $sql = "SELECT item_id FROM items WHERE item_identifier = ?";
    $sqldata = array($item_identifier);
    $item_ids = $db->fetchAll($sql, $sqldata);
    $item_ids = array_column($item_ids, 'item_id');
    $item_ids = array_merge($item_ids, [$itemId]);
} else {
    $item_ids = [$itemId];
}
//Now we have a list of item_ids to delete. We also now need to find all the file_ids that are linked to these item_ids
$sql = "SELECT file_id FROM file_links WHERE item_id IN (".implode(',', array_fill(0, count($item_ids), '?')).")";
$sqldata = $item_ids;
$file_ids = $db->fetchAll($sql, $sqldata);
$file_ids = array_column($file_ids, 'file_id');

//Now we have a list of file_ids to delete. We can now delete the file_links entries

try {
    // Set up a $db rollback so we can perform all the deletions in one go and cancel if any fail
    $db->beginTransaction();

    // Delete the file_links entries
    $sql = "DELETE FROM file_links WHERE item_id IN (".implode(',', array_fill(0, count($item_ids), '?')).")";
    $sqldata = $item_ids;
    $db->delete($sql, $sqldata);

    // Delete the files entries that connect to the file_links entries
    if(!empty($file_ids)) {
        $sql = "DELETE FROM files WHERE id IN (".implode(',', array_fill(0, count($file_ids), '?')).")";
        $sqldata = $file_ids;
        $db->delete($sql, $sqldata);
    }
    // Now we can delete the item_links entries
    if(!empty($item_ids)) {
        $sql = "DELETE FROM item_links WHERE item_id IN (".implode(',', array_fill(0, count($item_ids), '?')).")";
        $sqldata = $item_ids;
        $db->delete($sql, $sqldata);
    }

    // Now we can delete the items entries
    $sql = "DELETE FROM items WHERE item_id IN (".implode(',', array_fill(0, count($item_ids), '?')).")";
    $sqldata = $item_ids;
    $db->delete($sql, $sqldata);

    // Finally, delete any item_groups entries that are linked to the item_identifier
    if($item_identifier !== null) {
        $sql = "DELETE FROM item_groups WHERE item_identifier = ?";
        $sqldata = array($item_identifier);
        $db->delete($sql, $sqldata);
    }

    // If we've got this far, then we can commit the transaction
    $db->commit();
    $response['status']="success";
    $response['message']="Items deleted successfully";
} catch (Exception $e) {
    // Roll back the transaction if any of the deletions fail
    $db->rollBack();
    // Handle the exception (e.g., log the error, display an error message)
    error_log("Error deleting items: " . $e->getMessage());
    $response['status']="error";
    $response['message']= "An error occurred while deleting items. Please try again later.";
}
