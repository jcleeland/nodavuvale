<?php
$response=array();
/**
 * Get item details
 *  Depending on the data supplied, this could return a list of items or a single item
 * 
 * If the data array contains an item_id, then the function will return the details of that individual item
 * If the data array contains an item_identifer number, then the function will return the details of that individual item group
 * If the data array doesn't contain an item_id, or item_identifier, then the function will return a list of items, however it needs 
 * to have an individual_id in these circumstances in order to limit the list to items belonging to an individual
 * 
 * The items management in nodavuvale centres on two tables, the "items" table and the "item_links" table
 *  - The "items" table contains the details of the items (detail_type and detail_value), and the item_links table
 *    contains the links between the items and the individuals - linking the "item_id" in items to "item_id" in item_links.
 *    It also then links to an individual via the "individual_id" field in the item_links table
 * 
 * Additional parameters allow for selecting either a simply list of items and their details and links
 * or a list of items with the details of the individual they are linked to
 * or a list of items with the details of the individual they are linked to and the details of the item including file links
 */

//Check if the data array contains an item_id
if(isset($data['item_id'])) {
    //Get the item details
    $item_id = $data['item_id'];
    $sql = "SELECT * 
                FROM items 
                JOIN item_links ON items.item_id=item_links.item_id 
                WHERE items.item_id = ?";
    $response['sql']=$sql;
    $response['data']=array($item_id);
    try {
        $item = $db->fetch($sql, array($item_id));
        $response['sql']=$sql;
        $response['sqldata']=array($item_id);
        $response['status']='success';
        $response['message']='Item details retrieved successfully';
        $response['item']=$item;
    } catch (Exception $e) {
        $response['status']='error';
        $response['message']='Error retrieving item details';
    }
} else if(isset($data['item_identifier'])) {
    //Get the item details
    $item_identifier = $data['item_identifier'];
    $sql = "SELECT items.* 
                FROM items 
                JOIN item_links ON items.item_id = item_links.item_id 
                WHERE items.item_identifier = ?";
    $response['sql']=$sql;
    $response['data']=array($item_identifier);
    try {
        $item = $db->fetch($sql, array($item_identifier));
        //Searching by an item id means there will only ever be one response
        $response['status']='success';
        $response['message']='Item details retrieved successfully';
        $response['item']=$item;
    } catch (Exception $e) {
        $response['status']='error';
        $response['message']='Error retrieving item details';
    }
} else {
    //Check if the data array contains an individual_id
    if(!isset($data['individual_id'])) {
        $response['status']='error';
        $response['message']='No individual ID provided';
    } else {
        //Get the list of items for the individual
        $individual_id = $data['individual_id'];
        $sql = "SELECT items.*, item_links.id as item_link_id, files.*, file_links.id as file_link_id, file_links.file_id
                    FROM items 
                    JOIN item_links ON items.item_id = item_links.item_id 
                    LEFT JOIN file_links ON items.item_id = file_links.item_id
                    LEFT JOIN files ON file_links.file_id = files.id
                    WHERE item_links.individual_id = ?";
        $response['sql']=$sql;
        $response['data']=array($individual_id);
        try {
            $items = $db->fetchAll($sql, array($individual_id));
            $response['status']='success';
            $response['message']='Items retrieved successfully';
            $response['items']=$items;
        } catch (Exception $e) {
            $response['status']='error';
            $response['message']='Error retrieving items';
        }
    }
}