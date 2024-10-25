<?php
/** 
 * This ajax file will delete an item entry, or a group of item entries, from the database.
 */
$response=array();
if(!isset($data['itemId']) || !isset($data['itemIdentifier'])) {
    $response['status']='error';
    $response['message']='No item ID or item Identifier provided';
}
// Update the files table with the new description
$itemId = isset($data['itemId']) ? $data['itemId'] : null;
$itemIdentifier = isset($data['itemIdentifier']) ? $data['itemIdentifier'] : null;

$item_types=Utils::getItemTypes();
$item_styles=Utils::getItemStyles();

//An item/event can have multiple entries in the items table, so we need to find out all the connected ones.
// it can also have links to files, via the file_links table

//First, let's get all the item_ids that are linked to this item_id. Additional item_ids are connected via the item_identifier field & item_groups table
// - if the item_identifier is null, then it's a single event. So first let's see if the item_identifier is null

$item_group_name = null;

//If the item_identifier is not null, then we need to find all the item_ids that have the same item_identifier, and also the item_group_name from the item_groups tabe
$last_item_in_group = false;

if($itemIdentifier !== null) {
    $sql = "SELECT item_id, detail_type, item_group_name FROM items INNER JOIN item_groups ON item_groups.item_identifier=items.item_identifier WHERE items.item_identifier = ?";
    $sqldata = array($itemIdentifier);
    $item_ids_results = $db->fetchAll($sql, $sqldata);
    $item_group_name=$item_ids_results[0]['item_group_name'];
    //Create a keyed array where the detail_type is the key and the item_id is the value
    $item_ids=array();
    foreach($item_ids_results as $item) {
        $item_ids[]=$item['item_id'];
        $item_keyed_ids[$item['detail_type']] = $item['item_id'];
    }
} else {
    $item_ids = [$itemId];
    $item_keyed_ids=['File'=>$itemId]; //We'll just set this to check if there is a file
    //Just in case, lets see if this item is part of a group
    $sql = "SELECT detail_type, item_identifier FROM items WHERE item_id = ?";
    $sqldata = array($itemId);
    $tempItemIdResponse = $db->fetchOne($sql, $sqldata);
    if(!empty($tempItemIdResponse)) {
        $tempItemId=$tempItemIdResponse['item_identifier'];
        $tempDetailType=$tempItemIdResponse['detail_type'];
    } else {
        $tempItemId=null;
        $tempDetailType=null;
    }

    if($tempItemId !== null) {
        //Now lets count how many other items are in this group
        $sql = "SELECT COUNT(*) as count FROM items WHERE item_identifier = ? AND detail_value != '' AND detail_value is not null";
        $sqldata = array($tempItemId);
        $count = $db->fetchOne($sql, $sqldata);
        $response['count']=json_encode($count);
        if($count['count'] == 1) {
            $last_item_in_group = true;
        } else {
            $response['groupItemType']=$tempDetailType;
            $response['groupItemIdentifier']=$tempItemId;
        }
    }
}

if($item_group_name) {
    //Find out the standard items in this group
    $items=$item_types[$item_group_name];
}

$file_item_ids=array();
$files_to_delete=[];
//Get a list of the items that are also files
foreach($item_keyed_ids as $key=>$val) {
    if($item_styles[$key] == 'file') {
        $file_item_ids[]=$val;
        //These are the item_ids that could have files linked to them, so we need to make sure we also delete entries from the files & file_links tables
        $sql = "SELECT * FROM files, file_links WHERE file_links.item_id = ? AND files.id = file_links.file_id";
        $sqldata = array($val);
        $file_results = $db->fetchAll($sql, $sqldata);
        //Gather the names of the files to delete
        foreach($file_results as $fileresult) {
            $files_to_delete[]=$fileresult['file_path'];
        }
    }
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
    if($itemIdentifier !== null) {
        $sql = "DELETE FROM item_groups WHERE item_identifier = ?";
        $sqldata = array($itemIdentifier);
        $db->delete($sql, $sqldata);
    }

    //And as a final last step, if this is a single item deletion, but it's the last item in a group
    // then we need to delete the item_group_name from the item_groups table and also delete all the other 
    // items in the items table that have the same item_identifier
    if($last_item_in_group) {
        $sql = "DELETE FROM item_groups WHERE item_identifier = ?";
        $sqldata = array($tempItemId);
        $db->delete($sql, $sqldata);
        $sql = "DELETE FROM items WHERE item_identifier = ?";
        $sqldata = array($tempItemId);
        $db->delete($sql, $sqldata);
        $response['itemIdentifier']=$tempItemId;
    }

    //Now we can sensibly delete the files
    //Delete any actual files
    if(!empty($files_to_delete)) {
        foreach($files_to_delete as $filepath) {
            unlink($filepath);
        }
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
    $response['message']= "An error occurred while deleting items. Please try again later.\n".$e->getMessage();
}
