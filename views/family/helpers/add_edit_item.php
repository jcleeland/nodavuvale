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

// Get a list of pre-defined item types
$item_types = Utils::getItemTypes();
$item_styles= Utils::getItemStyles();

?>
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div id="modal-header" class="modal-header">
                <span class="close-event-btn">&times;</span>
                <h2 id="modal-title">Add a Fact or Event<span id='adding_event_to'></span></h2>
            </div>
            <div class="modal-body">
                <div class='event-content'>
                    <form method="POST">
                        <input type="hidden" name="formActionInput" id="event-action" value="add_item">
                        <input type="hidden" name="individual_id" value="<?= $individual_id ?>" id="event-individual_id">
                        <input type="hidden" name="user_id" value="<?= $user_id ?>"> <!-- Assuming user is logged in -->

                        <select name="item_type" id="item_type" class="w-full border rounded-lg p-2 mb-2" required onChange="updateEventContents(this.value)">
                            <option value="">Select an event type</option>
                            <?php foreach ($item_types as $key=>$type) : ?>
                                <option value="<?= $key ?>"><?= $key ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div id="event-fields" style="display: block">

<?php
//Iterate through each item type and display the relevant form fields - but reuse the same form fields for each type
// Do this by using the key value, removing that (and a space) from the beginning of the $type string
// so that you effectively have a generic form field name.
// put the $key value into the class of the input field so that you can hide/show the relevant fields
$fieldlist=array();
foreach ($item_types as $key=>$eventitems) :
    foreach($eventitems as $item) :
        $type = $item;
        $type = str_replace(" ", "_", $type);
        $fieldlist[$type][]=$key;
    endforeach;
endforeach; 
//Sort the fieldlist alphabetically

//Now iterate through the fieldlist and display the fields.
// Reference each field by the key value, and check for an entry in $item_styles to see how it should be displayed
// and add the relevant class to the input field using the values
// The "style" will be "date", "text", "file", "textarea" and so on - indicating what type of input field it is
// each field will have a class of "hidden" so that it can be shown/hidden as needed and also have the classes inside the $types array
// added to it so that it can be shown/hidden as needed

foreach ($fieldlist as $field=>$types) :
    $style = $item_styles[$field];
    $class = "event-field ";
    $class .= implode(" ", $types);
    $class .= " hidden";
    
    if($style == "date") :
        $input = "<div class='$class'><label for='$field'>$field</label><input type='date' name='$field' class='w-full border rounded-lg p-2 mb-2'></div>";
    elseif($style == "text") :
        $input = "<div class='$class'><label for='$field'>$field</label><input type='text' name='$field' class='w-full border rounded-lg p-2 mb-2'></div>";
    elseif($style == "file") :
        $input = "<div class='$class'><label for='$field'>$field</label><input type='file' name='$field' class='w-full border rounded-lg p-2 mb-2'></div>";
    elseif($style == "textarea") :
        $input = "<div class='$class'><label for='$field'>$field</label><textarea name='$field' class='w-full border rounded-lg p-2 mb-2'></textarea></div>";
    endif;
    echo "".$input;
endforeach;
?>


                        </div>

                        <button type="submit" name="new_individual_story" class="bg-deep-green text-white py-2 px-4 rounded-lg hover:bg-burnt-orange float-right" title="Submit story">
                            <i class="fa fa-paper-plane"></i>
                        </button>                    
                    </form>
                </div>
            </div>
        </div>
    </div>
?>
                        </div>

                        
                        
                        <button type="submit" name="new_individual_story" class="bg-deep-green text-white py-2 px-4 rounded-lg hover:bg-burnt-orange float-right" title="Submit story">
                            <i class="fa fa-paper-plane"></i>
                        </button>                    
                    </form>
                </div>
            </div>
        </div>
    </div>
