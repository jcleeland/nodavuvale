<?php
/**
 * This file is used to display the add/edit item form
 * 
 * Items may be comprised of a single entry in the items table,
 * or they may be grouped.
 * 
 * for example, a marriage would be a group of items, one item being 
 * the marriage date, the next being the marriage location, the next being a document/file
 * such as the marriage certificate, and another being a photo of the wedding
 * 
 * This php file will contain both the add/update process after the form is submitted
 * and the form itself, and will load relevant data if it already exists or present
 * a blank form if it does not.
 */

// Check if the form has been submitted
if (isset($_POST['action'])) {
    // Check if the action is to add or update an item
    if ($_POST['action'] == 'add_item') {
        // Add the item

    } elseif ($_POST['action'] == 'update_item') {
        // Update the item
    }
}

// Check if the item_id is set
if (isset($_GET['item_id'])) {
    // Load the item(s) from the database
    // First retrive this item by its id number
    $sql = "SELECT * FROM items WHERE id = :id";
}

// Check if the item is a group
