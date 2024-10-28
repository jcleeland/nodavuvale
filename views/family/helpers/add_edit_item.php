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

/*
    @uses Database class
    @uses Auth class
    @uses Web class
    @uses Utils class
*/

// Get a list of pre-defined item types
$item_types = Utils::getItemTypes();
$item_styles= Utils::getItemStyles();


// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_individual_item'])) {
    //echo "<pre>"; print_r($_POST); echo "</pre>";
    //echo "<pre>"; print_r($_FILES); echo "</pre>";


    // Check if the action is to add or update an item
    if ($_POST['action'] == 'add_item') {
        // Add the item
        // First, get the list of possible fields for this item type
        $item_type = $_POST['item_type'];
        $fields = $item_types[$item_type];
        $values = [];
        foreach ($fields as $field) {
            if($item_styles[$field] !== "file") {
                $values[$field] = $_POST[$field];
            } else {
                $values[$field] = "";
            }
        }
        //echo "<pre><b>".$item_type."</b><br />VALUES:"; print_r($values); echo "</pre>";
        //Generate an item_identifer to use for this group of items
        $item_identifier = null;
        if(count($values) > 1) {
            $item_identifier = Utils::getNextItemIdentifier($individual_id, $item_type);
        }
        foreach($values as $key=>$value) {
            //echo "Doing $key => $value";
            //echo " which is a [".$item_styles[$key]."]<br />";
            //For each of the values, add a new item to the "items" table, including the fields "detail_type" which will be the $key, "detail_value" which will be the $value
            // and "item_identifier" which will be the $item_identifier. Also add the user_id in the "user_id" field
            // retrieve the item_id, and add it to an array, which will be used to add the item_id to the item_links table, and also to the item_group table
            // - note that there is no function specifically for generating the SQL, but you can use the $db class for in "nodavuvale_db.php" for processing 
            if($item_styles[$key]=="file") {
                //Check to see if a file has actually been uploaded for this:
                if(isset($_FILES[$key]) && !empty($_FILES[$key]['name'] )) {
                    //echo "<pre>"; print_r($_FILES[$key]); echo "</pre>";
                    $value = $_POST['item_type']." ".$key. " file";
                }
            }
            $item_insert_sql = "INSERT INTO items (detail_type, detail_value, item_identifier, user_id) VALUES (?, ?, ?, ?)";


            if(1==1) {
                $db->insert($item_insert_sql, [$key, $value, $item_identifier, $user_id]);
                $item_id = $db->lastInsertId();

                $item_link_insert_sql = "INSERT INTO item_links (individual_id, item_id) VALUES (?, ?)";
                $db->insert($item_link_insert_sql, [$individual_id, $item_id]);

                
                //Now, if any of the $item_types are files, we'll need to upload the file and add them to the "files" and "file_links" table, and get their file_id for the item_links table
                // - the $item_types which are files are available in the $item_styles array, where the key is the $item_type, and the value is the style of the item. Any of these
                //   where the value is "file" are files
                if($item_styles[$key]=="file" && isset($_FILES[$key]) && !empty($_FILES[$key]['name'] )) {
                    //echo "Doing the whole file thing!<br />";
                    $file=$_FILES[$key];
                    $file_description = $_POST['item_type']." ".$key;

                    $file_type = $file['type'];
                    $file_type = explode('/', $file_type);
                    $file_type = $file_type[0];
                    //echo "File type is $file_type<br />";
                    // if the file is an image, save it to uploads/images, otherwise to uploads/documents
                    $upload_dir = 'uploads/' . ($file_type === 'image' ? 'images/' : 'documents/');
                    
                    
                    // Generate a unique file name and save the file
                    $file_name = basename($file['name']);
                    $file_path = $upload_dir . uniqid() . '_' . $file_name;
                    $file_format = pathinfo($file_name, PATHINFO_EXTENSION);

                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        //echo "Moved the file to $file_path<br />";
                        $file_insert_sql = "INSERT INTO files (file_type, file_path, file_format, file_description, user_id) 
                        VALUES (?, ?, ?, ?, ?)";
                        $db->insert($file_insert_sql, [$file_type, $file_path, $file_format, $file_description, $user_id]);
                        $file_id = $db->lastInsertId(); // Get the ID of the uploaded file

                        $file_link_sql = "INSERT INTO file_links (file_id, individual_id, item_id) VALUES (?, ?, ?)";
                        $db->insert($file_link_sql, [$file_id, $individual_id, $item_id]);
                    }                
                }
                //Finally, insert an entry into the item_groups table, linking the item_identifier with the group name
                // - this is only necessary if there is more than one item in the group
                if(count($values) > 1) {
                    $group_name = $item_type;
                    $group_sql = "INSERT INTO item_groups (item_group_name, item_identifier) VALUES (?, ?)";
                    $db->insert($group_sql, [$group_name, $item_identifier]);
                }
            }
        }




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

?>
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div id="modal-header" class="modal-header">
                <span class="close-event-btn">&times;</span>
                <h2 id="modal-title">Add a Fact or Event<span id='adding_event_to'></span></h2>
            </div>
            <div class="modal-body">
                <div class='event-content'>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" id="event-action" value="add_item">
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
                            
                            //echo "<pre style='overflow-y: scroll; max-height: 500px'>"; print_r($fieldlist); echo "</pre>";

                            //Now iterate through the fieldlist and display the fields.
                            // Reference each field by the key value, and check for an entry in $item_styles to see how it should be displayed
                            // and add the relevant class to the input field using the values
                            // The "style" will be "date", "text", "file", "textarea", "individual" and so on - indicating what type of input field it is
                            // each field will have a class of "hidden" so that it can be shown/hidden as needed and also have the classes inside the $types array
                            // added to it so that it can be shown/hidden as needed
                            $fieldinputs=array();
                            //Create the fieldinputs array in the same order as the $item_styles array
                            foreach($item_styles as $iskey=>$isval) {
                                $fieldinputs[$iskey]="";
                            }
                            foreach ($fieldlist as $field=>$types) :
                                $style = $item_styles[$field];
                                $class = "event-field ";
                                $class .= implode(" ", $types);
                                $class .= " hidden";
                                
                                if($style == "individual") :
                                    $fieldinputs[$field] = "<div class='$class'><label for='{$field}_display'>$field</label><input type='text' placeholder='Find another individual..' id='{$field}_name' name='{$field}_name' class='w-full border rounded-lg p-2 mb-2' oninput='showSuggestions(this.value)'><div id='{$field}_suggestions' class='autocomplete-suggestions'></div></div>";
                                    ?>
                                    <script type='text/javascript'>
                                    const individuals = [
                                        <?php foreach($individuals as $ind): ?>
                                            { id: <?= $ind['id'] ?>, name: "<?= $ind['first_names'] . ' ' . $ind['last_name'] ?>" },
                                        <?php endforeach; ?>
                                    ];

                                    function showSuggestions(value) {
                                        const suggestionsContainer = document.getElementById('<?= $field ?>_suggestions');
                                        suggestionsContainer.innerHTML = '';
                                        if (value.length === 0) {
                                            return;
                                        }

                                        const filteredIndividuals = individuals.filter(ind => ind.name.toLowerCase().includes(value.toLowerCase()));
                                        filteredIndividuals.forEach(ind => {
                                            const suggestion = document.createElement('div');
                                            suggestion.className = 'autocomplete-suggestion';
                                            suggestion.textContent = ind.name;
                                            suggestion.onclick = () => selectSuggestion(ind);
                                            suggestionsContainer.appendChild(suggestion);
                                        });
                                    }

                                    function selectSuggestion(individual) {
                                        const input = document.getElementById('<?= $field ?>_name');
                                        input.value = individual.name;
                                        const hiddenInput = document.createElement('input');
                                        hiddenInput.type = 'hidden';
                                        hiddenInput.name = '<?= $field ?>';
                                        hiddenInput.value = individual.id;
                                        input.parentNode.appendChild(hiddenInput);
                                        document.getElementById('<?= $field ?>_suggestions').innerHTML = '';
                                    }                                        
                                    </script>
                                    <?php
                                elseif($style == "date") :
                                    $fieldinputs[$field] = "<div class='$class'><label for='$field'>$field</label><input type='text' name='$field' placeholder='YYYY-MM-DD' pattern='\\d{4}(-\\d{2})?(-\\d{2})?' class='w-full border rounded-lg p-2 mb-2'></div>";
                                elseif($style == "text") :
                                    $fieldinputs[$field] = "<div class='$class'><label for='$field'>$field</label><input type='text' name='$field' class='w-full border rounded-lg p-2 mb-2'></div>";
                                elseif($style == "file") :
                                    $fieldinputs[$field] = "<div class='$class'><label for='$field'>$field</label><input type='file' name='$field' class='w-full border rounded-lg p-2 mb-2'></div>";
                                elseif($style == "textarea") :
                                    $fieldinputs[$field] = "<div class='$class'><label for='$field'>$field</label><textarea name='$field' class='w-full border rounded-lg p-2 mb-2'></textarea></div>";
                                endif;
                            endforeach;
                            foreach($fieldinputs as $fieldinput) {
                                echo $fieldinput;
                            }
                            ?>


                        </div>
                        <div class="grid grid-cols-1">
                            <div>
                                <button type="submit" name="new_individual_item" value="submit" class="bg-deep-green text-white py-2 px-4 rounded-lg hover:bg-burnt-orange float-right" title="Submit fact or event">
                                    <i class="fa fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>                    
                    </form>
                </div>
            </div>
        </div>
    </div>
