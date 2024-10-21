<?php
/**
 * Handle new individuals and/or new relationships
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && ( strpos($_POST['action'], 'add_') === 0 || $_POST['action'] == 'link_relationship'))) {
    $first_names = $_POST['first_names'];
    $aka_names = $_POST['aka_names'];
    $last_name = $_POST['last_name'];
    $submitted_by = $_SESSION['user_id'];
    $birthyear = !empty($_POST['birth_year']) ? $_POST['birth_year'] : null;
    $birthmonth = !empty($_POST['birth_month']) ? $_POST['birth_month'] : null;
    $birthdate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $deathyear = !empty($_POST['death_year']) ? $_POST['death_year'] : null;
    $deathmonth = !empty($_POST['death_month']) ? $_POST['death_month'] : null;
    $deathdate = !empty($_POST['death_date']) ? $_POST['death_date'] : null;
    //Mark as isdeceased if there is a $_POST['death_year'] or a $POST['is_deceased'] value of 1
    $isdeceased = !empty($_POST['death_year']) || !empty($_POST['is_deceased']) ? 1 : 0;

    // Save the new individual to `individuals` or `temp_individuals`
    
    if($_POST['action'] == 'add_individual' || empty($_POST['connect_to'])) {
        //echo "Adding new individual";
        //Check to see if there is a matching record already in the individuals table
        $existing_individual = $db->fetchAll("SELECT * FROM individuals WHERE first_names = ? AND last_name = ?", [$first_names, $last_name]);
        if($existing_individual) {
            echo "This individual already exists in the database.";
        } else {
            $sql = "INSERT INTO individuals (first_names, aka_names, last_name, birth_prefix, birth_year, birth_month, birth_date, death_prefix, death_year, death_month, death_date, gender, is_deceased) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params=[$first_names, $aka_names, $last_name, $_POST['birth_prefix'], $birthyear, $birthmonth, $birthdate, $_POST['death_prefix'], $deathyear, $deathmonth, $deathdate, $_POST['gender'], $isdeceased];
            //replace the ? in $sql with values from the $params array
            $insertsql = str_replace("?", "'%s'", $sql);
            $insertsql = vsprintf($insertsql, $params);
            
            //echo $insertsql;
            $db->query(
                $sql,
                $params
            );
            $new_individual_id = $db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
        }
        //die();
    } 

    
    //If there is a value in $_POST['relationship'] add relationship to the database
    if(!empty($_POST['relationship'])) {
        $proceed=true; //Set a value for making the change, which can be turned off if there is a problem
        $errormessage="";

        // Quick hack to make adding a relationship easier whether it is a new person added, or a connect_to person who already exists
        if(!empty($_POST['connect_to'])) {
            $new_individual_id=$_POST['connect_to'];
        }

        /* Adjust the perspective for child/parent relationships
        All child/parent relationships should be recorded as the perspective of the child. So if someone has posted 
        the values $_POST['related_individual'] = 1 and the $new_individual_id is 2, and the $_POST['relationship'] is 'parent'
        then the relationship should be recorded as individual_id_1 = 2, individual_id_2 = 1, relationship_type = 'child',
        however if $_POST['relationship'] is 'child', then we can leave everything as it is.
        */
        if($_POST['relationship'] == 'parent') {
            $temp = $new_individual_id;
            $new_individual_id = $_POST['related_individual'];
            $_POST['related_individual'] = $temp;
            $_POST['relationship'] = 'child';
        }


        $proceed=checkForDuplicateRelationship($db, $_POST['related_individual'], $new_individual_id, $_POST['relationship']);
        //If the relationship type is "child" thenn also make sure that there aren't more than 2 other parents for this child
        if($_POST['relationship'] == 'child') {
            $proceed=checkFor2Parents($db, $new_individual_id);
        }

        if($proceed) {
            $db->query(
                "INSERT INTO relationships (individual_id_1, individual_id_2, relationship_type) VALUES (?, ?, ?)",
                [$_POST['related_individual'], $new_individual_id, $_POST['relationship']]
            ); 
            //If there is a value in "second-parent", then add this new individual as a child of the second parent
            if(!empty($_POST['second-parent'])) {
                $proceedagain=true;
                $proceedagain=checkForDuplicateRelationship($db, $_POST['second-parent'], $new_individual_id, 'child');
                $proceedagain=checkFor2Parents($db, $new_individual_id);
                if($proceedagain) {
                    $db->query(
                        "INSERT INTO relationships (individual_id_1, individual_id_2, relationship_type) VALUES (?, ?, ?)",
                        [$_POST['second-parent'], $new_individual_id, 'child']
                    );
                }
            }                       
        } 
    }


    // After the changes, reload the page to show the updated tree
    //header("Location: " . $_SERVER['REQUEST_URI']);
    //exit; // Make sure to stop further script execution
}
function checkForDuplicateRelationship($db, $related_id, $new_id, $relationship_type) {
    //Make sure there isn't already a child/parent relationship between these two individuals
    $existing_relationship = $db->fetchAll("SELECT * FROM relationships WHERE individual_id_1 = ? AND individual_id_2 = ? AND relationship_type = ?", [$related_id, $new_id, $relationship_type]);
    if($existing_relationship) {
        return false;
    }
    return true;
}

function checkFor2Parents($db, $individual_id) {
    $existing_relationships = $db->fetchAll("SELECT distinct(individual_id_1) FROM relationships WHERE individual_id_2 = ? AND relationship_type = ?", [$individual_id, 'child']);
    if(count($existing_relationships) > 1) {
        return false;
    }
    return true;
}

?>
<!-- "Add new relationship" Modal Popup Form -->
<div id="popupForm" class="modal" style="display: none;">
        <div class="modal-content">
            <div id="modal-header" class="modal-header">
                <span class="close-btn">&times;</span>
                <h2 id="modal-title">Add New Relationship <span id='adding_relationship_to'></span></h2>
            </div>
            <div class="modal-body">
                <form id="add-relationship-form" action="?to=family/tree" method="POST">
                    <input type="hidden" name="action" value="" id="form-action">
                    <input type="hidden" name="related_individual" value="" id="related-individual">
                    <input type="hidden" id="root_id" name="root_id" value="<?= $rootId; ?>">

                    <div id="add-relationship-choice" class="mb-4 text-sm text-center mt-2">
                    Connect to 
                        <input type="radio" id="choice-existing-individual" name="choice-existing-individual" value="existing">
                        <label for="choice-existing-individual" class="mr-3">Existing Individual</label>
                        <input type="radio" id="choice-new-individual" name="choice-new-individual" value="new">
                        <label for="choice-new-individual" class="mr-3">New Individual</label>
                    </div>

                    <!-- Lookup field to select an existing individual -->
                    <div id="existing-individuals" class="mb-4" style='display: none'>
                        <label for="lookup" class="block text-gray-700">Connect to Existing Individual</label>
                        <input type="text" id="lookup" name="lookup" class="w-full px-4 py-2 border rounded-lg" placeholder="Type to search...">
                        <select id="connect_to" name="connect_to" class="w-full px-4 py-2 border rounded-lg mt-2" size="5" style="display: none;">
                            <option value="">Select someone...</option>
                            <?php foreach ($individuals as $indi): ?>
                                <option value="<?php echo $indi['id']; ?>">
                                    <?php echo $indi['first_names'] . ' ' . $indi['last_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- New Individual Form -->
                    <div id="additional-fields" style='display: none'>
                        <div class="mb-4">
                            <label for="first_names" class="block text-gray-700 mr-2">First Name(s)</label>
                            <div class="flex items-center">
                                <input type="text" id="first_names" name="first_names" class="flex-grow px-4 py-2 border rounded-lg" required>
                                <button type="button" title="Add other names for this person" id="toggle-aka" class="ml-2 px-2 py-1 bg-gray-300 rounded text-xs">AKA</button>
                            </div>
                        </div>
                        <div id="aka" class="mb-4" style="display: none">
                            <label for="aka_names" class="block text-gray-700">Other name(s) used</label>
                            <input type="text" id="aka_names" name="aka_names" class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div class="mb-4">
                            <label for="last_name" class="block text-gray-700">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="w-full px-4 py-2 border rounded-lg" required>
                        </div>

                        <!-- Birth -->
                        <div class="mb-4 grid grid-cols-4 gap-4">
                            <div>
                                <label for="birth_prefix" class="block text-gray-700">Birth</label>
                                <select id="birth_prefix" name="birth_prefix" class="w-full px-4 py-2 border rounded-lg">
                                    <option value=""></option>
                                    <option value="exactly">Exactly</option>
                                    <option value="about">About</option>
                                    <option value="after">After</option>
                                    <option value="before">Before</option>
                                </select>
                            </div>
                            <div>
                                <label for="birth_year" class="block text-gray-700">Year</label>
                                <input type="text" id="birth_year" name="birth_year" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                            <div>
                                <label for="birth_month" class="block text-gray-700">Month</label>
                                <select id="birth_month" name="birth_month" class="w-full px-4 py-2 border rounded-lg">
                                    <option value=""></option>
                                    <option value="1">January</option>
                                    <option value="2">February</option>
                                    <option value="3">March</option>
                                    <option value="4">April</option>
                                    <option value="5">May</option>
                                    <option value="6">June</option>
                                    <option value="7">July</option>
                                    <option value="8">August</option>
                                    <option value="9">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>
                            <div>
                                <label for="birth_date" class="block text-gray-700">Date</label>
                                <input type="text" id="birth_date" name="birth_date" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                        </div>
                        <div class="mb-4 grid grid-cols-4 gap-4">
                            <div>
                                <label for="death_prefix" class="block text-gray-700">Death</label>
                                <select id="death_prefix" name="death_prefix" class="w-full px-4 py-2 border rounded-lg">
                                    <option value=""></option>
                                    <option value="exactly">Exactly</option>
                                    <option value="about">About</option>
                                    <option value="after">After</option>
                                    <option value="before">Before</option>
                                </select>
                            </div>
                            <div>
                                <label for="death_year" class="block text-gray-700">Year</label>
                                <input type="text" id="death_year" name="death_year" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                            <div>
                                <label for="death_month" class="block text-gray-700">Month</label>
                                <select id="death_month" name="death_month" class="w-full px-4 py-2 border rounded-lg">
                                    <option value=""></option>
                                    <option value="1">January</option>
                                    <option value="2">February</option>
                                    <option value="3">March</option>
                                    <option value="4">April</option>
                                    <option value="5">May</option>
                                    <option value="6">June</option>
                                    <option value="7">July</option>
                                    <option value="8">August</option>
                                    <option value="9">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>
                            <div>
                                <label for="death_date" class="block text-gray-700">Date</label>
                                <input type="text" id="death_date" name="death_date" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                        </div>

                        <!-- Gender -->
                        <div class="mb-4">
                            <label for="gender" class="block text-gray-700">Gender</label>
                            <select id="gender" name="gender" class="w-full px-4 py-2 border rounded-lg">
                                <option value="">Select gender...</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <!-- Deceased -->
                        <div class="mb-4">
                            <label for="is_deceased" class="block text-gray-700">Deceased</label>
                            <input type="checkbox" id="is_deceased" name="is_deceased" value="1">
                        </div>

                    </div>

                    <!-- Relationship -->
                    <div id="relationships" class="mb-4">
                        <div id="primary-relationship">
                            <label for="relationship" class="block text-gray-700">Relationship to Selected Individual</label>
                            <select id="relationship" name="relationship" class="w-full px-4 py-2 border rounded-lg">
                                <option value="">Select Relationship...</option>
                                <option value='parent'>Parent</option>
                                <option value='child'>Child</option>
                                <option value='spouse'>Spouse</option>
                            </select>
                        </div>
                        <div id="choose-second-parent" style="display: none">
                            <label for="second-parent" class="block text-gray-700">Other parent</label>
                            <select id="second-parent" name="second-parent" class="w-full px-4 py-2 border rounded-lg">
                                <option value="">Not known..</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

