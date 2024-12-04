<?php
/**
 * Handle new individuals and/or new relationships
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && ( strpos($_POST['action'], 'add_relationship') === 0 || $_POST['action'] == 'link_relationship'))) {
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
    $oktoadd=true;
    if($_POST['action'] == 'add_individual' || empty($_POST['findindividual_connect_to'])) {
        //Checks to see if there is a matching record already in the individuals table
        if(isset($_POST['confirmed']) && $_POST['confirmed'] == 1) {
            //We've already checked
            $oktoadd=true;
        } elseif (isset($_POST['confirmed']) && $_POST['confirmed'] == 'denied') {
            //The user has checked and decided this is the same person
            ?>
            <script type="text/javascript">
                window.location.href = "index.php?".$_SESSION['QUERY_STRING'];
            </script>
            <?php
            exit;
        } else {
            //Do the check
            $existing_individual = $db->fetchAll("SELECT * FROM individuals WHERE first_names = ? AND last_name = ?", [$first_names, $last_name]);
            if($existing_individual) {
                $oktoadd=false; //Start by assuming that we can't add this individual
                //Now lets do some checks to see if we can reasonably assume that this is a different person
                $existing_individual = $existing_individual[0];
                // Lets see if the existing individual has parents already, and if so - whether they are different from the parents being submitted
                $existing_parents = $db->fetchAll("SELECT * FROM relationships WHERE individual_id_2 = ? AND relationship_type = 'parent'", [$existing_individual['id']]);
                if($existing_parents) {
                    //echo "This individual already has parents";
                    //echo "<pre>"; print_r($existing_parents); echo "</pre>";
                    //echo "<pre>"; print_r($_POST); echo "</pre>";
                    //die();
                    //If the existing individual has parents, then check to see if the parents being submitted are different. If the existing person has different parents,
                    // then we can assume that this is a different person
                    if($existing_parents[0]['individual_id_1'] != $_POST['related_individual'] && $existing_parents[0]['individual_id_1'] != $_POST['second-parent']) {
                        $oktoadd=true;
                    }
                }
                
                // then check the birth year and see if they are within 5 years of each other
                if($existing_individual['birth_year'] && $birthyear) {
                    if(abs($existing_individual['birth_year'] - $birthyear) > 5) {
                        $oktoadd=true;
                    }
                }
            } 
        }
        
        if(!$oktoadd) {
            //If there is reason to think this could be a duplicate, let's give the submitter the final say by asking them to confirm.
            // we can show them the other person's details and ask them to confirm that this is a different person
            
            //Get the current URL query string
            $query = $_SERVER['QUERY_STRING'];
            ?>
                <div class="modal" id="checking-to-confirm" style="display: block">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Confirm adding this person</h2>
                            It looks like a person with the same name already exists in the database. Please confirm that this is a different person.
                        </div>
                        <div class="modal-body">
                            <pre><?php print_r($existing_individual); ?></pre>
                            <form id="add-relationship-form" action="index.php?<?= $query ?>" method="POST">
                                <input type="hidden" name="confirmed" value="1">
                                <?php foreach($_POST as $key=>$value): ?>
                                    <input type='hidden' name='<?= $key ?>' value='<?= $value ?>'>
                                <?php endforeach; ?>
                                <button type="submit" name="confirmed" value="1" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                                    Yes, add this person
                                </button>
                                <button type="submit" name="confirmed" value="denied" class="bg-red-500 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                                    No, cancel
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php
            die();

        } else {
            $sql = "INSERT INTO individuals (first_names, aka_names, last_name, birth_prefix, birth_year, birth_month, birth_date, death_prefix, death_year, death_month, death_date, gender, is_deceased, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params=[$first_names, $aka_names, $last_name, $_POST['birth_prefix'], $birthyear, $birthmonth, $birthdate, $_POST['death_prefix'], $deathyear, $deathmonth, $deathdate, $_POST['gender'], $isdeceased, $user_id];
            //replace the ? in $sql with values from the $params array
            $insertsql = str_replace("?", "'%s'", $sql);
            $insertsql = vsprintf($insertsql, $params);
            
            $db->query(
                $sql,
                $params
            );
            $new_individual_id = $db->query("SELECT LAST_INSERT_ID()")->fetchColumn();
        
        }
    } 

    
    //If there is a value in $_POST['relationship'] add relationship to the database
    if(!empty($_POST['relationship'])) {
        $proceed=true; //Set a value for making the change, which can be turned off if there is a problem
        $errormessage="";

        // Quick hack to make adding a relationship easier whether it is a new person added, or a connect_to person who already exists
        if(!empty($_POST['findindividual_connect_to'])) {
            $new_individual_id=$_POST['findindividual_connect_to'];
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
            echo "Proceeding with db update";
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


    // After the changes, reload the page to show the updated tree and avoid resubmission of the form
    ?>
    <script type="text/javascript">
        window.location.href = "index.php?<?= $_SERVER['QUERY_STRING'] ?>";
    </script>
    <?php
    exit;
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

$parents=[];
if(isset($individual_id)) {
    $parents=Utils::getParents($individual_id);
}
$spouses=[];
if(isset($individual_id)) {
    $spouses=Utils::getSpouses($individual_id);
}
?>
<!-- "Add new relationship" Modal Popup Form -->
<div id="popupForm" class="modal" style="display: none;">
        <div class="modal-content">
            <div id="modal-header" class="modal-header">
                <span class="close-btn">&times;</span>
                <h2 id="modal-title">Add New Relationship <span class='adding_relationship_to_name'></span></h2>
            </div>
            
            <form id="add-relationship-form" action="?to=family/tree" method="POST" class="modal-body overflow-y-hidden">
                <input type="hidden" name="action" value="" id="relationship-form-action">
                <input type="hidden" name="related_individual" value="" id="related-individual">
                <input type="hidden" id="root_id" name="root_id" value="<?= $rootId; ?>">

                <!-- Who is being connected ? -->
                <div id="add-relationship-choice" class="mb-4 text-sm text-center mt-2 whitespace-nowrap">
                    <span>Connect</span>&nbsp;
                    <select id="new-individual-type" name="new-individual-type" class="px-4 py-2 border rounded-lg">
                        <option value="">Please choose..</option>
                        <option value="existing">someone already in the tree</option>
                        <option value="new">a new person</option>
                    </select>
                </div>


                <!-- Relationship -->
                <div id="relationships" class="mb-4 text-sm text-center mt-2 whitespace-nowrap">
                    <div id="primary-relationship">
                        <label for="relationship">to <span class='adding_relationship_to_firstname italic'></span> as their</label>
                        <select id="relationship" name="relationship" class="px-4 py-2 border rounded-lg">
                            <option value="">Select Relationship...</option>
                            <option value='parent'>Parent</option>
                            <option value='child'>Child</option>
                            <option value='spouse'>Spouse</option>
                        </select>
                    </div>
                </div>

                <!-- optional second parent for children -->
                <div id="choose-second-parent" style="display: none" class="mb-4 text-sm text-center mt-2 whitespace-nowrap">
                    <div>
                        <label for="second-parent">with their other parent</label>
                        <select id="second-parent" name="second-parent" class="px-4 py-2 border rounded-lg">
                            <option value="">Not known..</option>
                        </select>
                    </div>
                </div>                    

                <!-- Lookup field to select an existing individual -->
                <div id="existing-individuals" class="mb-4" style='display: none'>
                    <?= Web::showFindIndividualLookAhead($individuals, 'lookup') ?>
                </div>

                <!-- New Individual Form -->
                <div id="additional-fields" style='display: none'>
                    <div class="mb-4">
                        <label for="first_names" class="block text-gray-700 mr-2">
                            First Name(s)
                            <i class="hint fas fa-question-circle text-gray-500 ml-2 cursor-pointer" title="Put all the 'official' names of this person, other than their last/family name, into this section - but don't include any nicknames or non-formal ones. Instead, you can add them to the 'AKA' ('Also Known As') section."></i>
                        </label>
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
                        <label for="last_name" class="block text-gray-700">
                            Last Name
                            <i class="hint fas fa-question-circle text-gray-500 ml-2 cursor-pointer" title="Use each individual's last name at birth, rather than a married or otherwise changed name. This ensure consistency across the tree. You can store information about name changes in the Facts/Events section"></i>
                            <?php foreach($parents as $parent): ?>
                                <div title='Use this surname' class='inline text-xs text-gray-500 text-opacity-50 rounded-lg nv-bg-opacity-50 bg-cream-800 px-2 mx-2 pb-1 -mt-1 cursor-pointer' onclick='document.getElementById("last_name").value="<?= $parent['last_name'] ?>"'><?= $parent['last_name'] ?></div>
                            <?php endforeach ?>
                            <?php foreach($spouses as $spouse): ?>
                                <div title='Use this surname' class='inline text-xs text-gray-500 text-opacity-50 rounded-lg nv-bg-opacity-50 bg-cream-800 px-2 mx-2 pb-1 -mt-1 cursor-pointer' onclick='document.getElementById("last_name").value="<?= $spouse['last_name'] ?>"'><?= $spouse['last_name'] ?></div>
                            <?php endforeach ?>
                        </label>

                        <input type="text" id="last_name" name="last_name" class="w-full px-4 py-2 border rounded-lg" required>
                    </div>

                    <!-- Birth -->
                    <div class="mb-4 grid grid-cols-4 gap-4">
                        <div>
                            <label for="birth_date" class="block text-gray-700">Date</label>
                            <input type="number" min="1" max="31" id="birth_date" name="birth_date" class="w-full px-4 py-2 border rounded-lg">
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
                            <label for="birth_year" class="block text-gray-700">Year</label>
                            <input type="number" min="1000" max="<?= date("Y") ?>" id="birth_year" name="birth_year" class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label for="birth_prefix" class="block text-gray-700 whitespace-nowrap">
                                Birth prefix
                                <i class="hint fas fa-question-circle text-gray-500 ml-2 cursor-pointer" title="Only select one of these options if there is some uncertainty about the date you've entered. For example if you have just the year of birth, and you know it's correct, enter the year and leave the rest blank - but don't select an option from this list."></i>
                            </label>
                            <select id="birth_prefix" name="birth_prefix" class="w-full px-4 py-2 border rounded-lg">
                                <option value=""></option>
                                <option value="about">About</option>
                                <option value="after">After</option>
                                <option value="before">Before</option>
                            </select>
                        </div>                            
                    </div>
                    <div class="mb-4 grid grid-cols-4 gap-4">
                        <div>
                            <label for="death_date" class="block text-gray-700">Date</label>
                            <input type="number" min="1" max="31" id="death_date" name="death_date" class="w-full px-4 py-2 border rounded-lg">
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
                            <label for="death_year" class="block text-gray-700">Year</label>
                            <input type="number" min="1000" max="<?= date("Y") ?>" id="death_year" name="death_year" class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label for="death_prefix" class="block text-gray-700 whitespace-nowrap">
                                Death Prefix
                                <i class="hint fas fa-question-circle text-gray-500 ml-2 cursor-pointer" title="Only select one of these options if there is some uncertainty about the date you've entered. For example if you have just the year of death, and you know it's correct, enter the year and leave the rest blank - but don't select an option from this list."></i>
                            </label>
                            <select id="death_prefix" name="death_prefix" class="w-full px-4 py-2 border rounded-lg">
                                <option value=""></option>
                                <option value="exactly">Exactly</option>
                                <option value="about">About</option>
                                <option value="after">After</option>
                                <option value="before">Before</option>
                            </select>
                        </div>                            
                    </div>


                    <!-- Gender -->
                    <div class="mb-4">
                        <div class="grid grid-cols-2 justify-around">
                            <div class="text-left">
                                <label for="gender" class="text-gray-700">Gender</label>
                                <select id="gender" name="gender" class="px-4 py-2 border rounded-lg">
                                    <option value="">Select...</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="text-right">
                                <input type="checkbox" id="is_deceased" name="is_deceased" value="1">
                                <label for="is_deceased" class="text-gray-700">
                                    Deceased
                                    <i class="hint fas fa-question-circle text-gray-500 ml-2 cursor-pointer" title="This checkbox is to register when a family member is deceased, even if we don't know the actual date. NodaVuvale (this websystem) uses this information to determine if privacy rules should be applied to an individual or not."></i>
                                </label>
                            </div>
                        </div>
                    </div>                            

                </div>

                <div id="submit_add_relationship_btn" class="text-center" style='display: none'>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Submit
                    </button>
                    &nbsp;
                </div>
            </form>
        </div>
    </div>
    <script>
        //Add a listener to all items with the class "hint" and show the title attribute as a tooltip when clicked
        document.querySelectorAll('.hint').forEach(item => {
            item.addEventListener('click', event => {
                alert(item.getAttribute('title'));
            })
        });
    </script>

