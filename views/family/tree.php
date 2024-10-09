<?php

// Check if the user is logged in and their role
$is_admin = ($auth->isLoggedIn() && $auth->getUserRole() === 'admin');


// Handle form submission for adding individuals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //echo "<pre>"; print_r($_POST); echo "</pre>";
}

/**
 * Handle new individuals and/or new relationships
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && ( strpos($_POST['action'], 'add_') === 0 || $_POST['action'] == 'link_relationship'))) {
    $first_names = $_POST['first_names'];
    $aka_names = $_POST['aka_names'];
    $last_name = $_POST['last_name'];
    $submitted_by = $_SESSION['user_id'];
    $photo_path = null;

    // Handle file upload (if photo is uploaded)
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/photos/';
        $photo_name = basename($_FILES['photo']['name']);
        $photo_path = $upload_dir . $photo_name;
        
        // Move the uploaded file to the designated folder
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
            echo "Failed to upload photo.";
            exit;
        }
    }

    // Save the new individual to `individuals` or `temp_individuals`
    
    if($_POST['action'] == 'add_individual' || empty($_POST['connect_to'])) {
        //Check to see if there is a matching record already in the individuals table
        $existing_individual = $db->fetchAll("SELECT * FROM individuals WHERE first_names = ? AND last_name = ?", [$first_names, $last_name]);
        if($existing_individual) {
            echo "This individual already exists in the database.";
            $new_individual_id = $existing_individual['id'];
        } else {
            if ($is_admin) {
                // Admin can directly add to the `individuals` table
                
                if(1==1) {
                    $db->query(
                        "INSERT INTO individuals (first_names, aka_names, last_name, birth_prefix, birth_year, birth_month, birth_date, death_prefix, death_year, death_month, death_date, gender, photo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$first_names, $aka_names, $last_name, $_POST['birth_prefix'], $_POST['birth_year'], $_POST['birth_month'], $_POST['birth_date'], $_POST['death_prefix'], $_POST['death_year'], $_POST['death_month'], $_POST['death_date'], $_POST['gender'], $photo_path]
                    );
                    $new_individual_id = $db->query("SELECT LAST_INSERT_ID()")->fetchColumn();

                    
                }
            } else {
                
                if(1==1) {
                // Non-admins save to `temp_individuals` for admin review
                    $db->query(
                        "INSERT INTO temp_individuals (first_names, aka_names, last_name, birth_prefix, birth_year, birth_month, birth_date, death_prefix, death_year, death_month, death_date, gender, photo, submitted_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$first_names, $aka_names, $last_name, $_POST['birth_prefix'], $_POST['birth_year'], $_POST['birth_month'], $_POST['birth_date'], $_POST['death_prefix'], $_POST['death_year'], $_POST['death_month'], $_POST['death_date'], $_POST['gender'], $photo_path, $submitted_by]
                    );
                    
                }
            }
        }
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

//Handle form submission for updating individuals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_individual') {
    $individual_id = $_POST['individual_id'];
    $first_names = $_POST['first_names'];
    $aka_names = $_POST['aka_names'];
    $last_name = $_POST['last_name'];
    $birth_prefix = $_POST['birth_prefix'];
    $birth_year = isset($_POST['birth_year']) ? $_POST['birth_year'] : null;
    $birth_month = isset($_POST['birth_month']) ? $_POST['birth_month'] : null;
    $birth_date = isset($_POST['birth_date']) && !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $death_prefix = $_POST['death_prefix'];
    $death_year = isset($_POST['death_year']) ? $_POST['death_year'] : null;
    $death_month = isset($_POST['death_month']) ? $_POST['death_month'] : null;
    $death_date = isset($_POST['death_date']) && !empty($_POST['death_date']) ? $_POST['death_date'] : null;
    $gender = $_POST['gender'];

    // Update the individual in the database
    $db->query(
        "UPDATE individuals SET first_names = ?, aka_names = ?, last_name = ?, birth_prefix = ?, birth_year = ?, birth_month = ?, birth_date = ?, death_prefix = ?, death_year = ?, death_month = ?, death_date = ?, gender = ? WHERE id = ?",
        [$first_names, $aka_names, $last_name, $birth_prefix, $birth_year, $birth_month, $birth_date, $death_prefix, $death_year, $death_month, $death_date, $gender, $individual_id]
    );



}




// Fetch all confirmed individuals and their relationships
$individuals = $db->fetchAll("SELECT * FROM individuals");
$relationships = $db->fetchAll("SELECT * FROM relationships");

// Set the root id for the tree
// If none has been set, default to 1
$rootId=1;

// If the user has a set preferred root id, use that instead
if(isset($_SESSION['preferred_root_id'])) {
    $rootId = $_SESSION['preferred_root_id'];
}
// If the user has requested a different root id, use that instead
if(isset($_GET['root_id'])) {
    $rootId = $_GET['root_id'];
}
if(isset($_POST['root_id'])) {
    $rootId = $_POST['root_id'];
}

$tree_data = Utils::buildTreeData($rootId, $individuals, $relationships);

// Insert a little button that will allow the user to "copy" the $tree_data to the clipboard just at the beginning of the next section

?>

<section class="mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <button class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 ml-1 rounded-lg float-right" onclick="navigator.clipboard.writeText(JSON.stringify(tree))">&#128203;</button>
    <button class="add-new-btn bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg float-right" title="Add new individual">+</button>
    <h1 class="text-3xl font-bold mb-6">Family Tree</h1>
    
    <!-- Family Tree Display -->
    <div id="family-tree" class="familytree"></div>

    <script>
        var tree = <?= $tree_data; ?>;
        //Get the width of the current page
        var windowWidth = window.innerWidth;
        //get the height of the window
        var windowHeight = window.innerHeight;
        dTree.init(tree, {
            target: "#family-tree",
            debug: true,
            width: windowWidth,
            height: windowHeight,
            nodeWidth: 200,
            nodeHeight: 80,
            zoom: 0.7,
            connectors: {
                type: 'curve',  // straight, curve, step, elbow
                style: {
                    stroke: '#555',
                    strokeWidth: 1.5,
                    strokeLinecap: 'round'
                },
                curveRadius: 10,
                curveFactor: 0.7,
            }
        });

    </script>




    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="edit-close-btn">&times;</span>
            <h2>Edit <span='individual_name'>Individual</span></h2>
            <form id="editForm" action="?to=family/tree" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_individual">
                <input type="hidden" id="edit-individual-id" name="individual_id">
                <input type="hidden" id="root_id" name="root_id" value="<?= $rootId; ?>">
                
                <div class="mb-4">
                    <label for="first_name" class="block">First Name</label>
                    <div class="flex items-center mt-0">
                            <input type="text" id="edit-first-names" name="first_names" class="flex-grow px-4 py-2 border rounded-lg" required>
                            <button type="button" title="Add other names for this person" id="toggle-aka" class="ml-2 px-2 py-1 bg-gray-300 rounded text-xs">AKA</button>
                        </div>                    
                </div>
                <div id="aka" class="mb-4" style="display: none">
                        <label for="aka_names" class="block text-gray-700">Other name(s) used</label>
                        <input type="text" id="edit-aka-names" name="aka_names" class="w-full px-4 py-2 border rounded-lg">
                </div>                
                <div class="mb-4">
                    <label for="last_name" class="block">Last Name</label>
                    <input type="text" id="edit-last-name" name="last_name" class="w-full px-4 py-2 border rounded-lg" required>
                </div>




                <!-- Birth -->
                <div class="mb-4 grid grid-cols-4 gap-4">
                    <div>
                        <label for="birth_prefix" class="block text-gray-700">Birth</label>
                        <select id="edit-birth-prefix" name="birth_prefix" class="w-full px-4 py-2 border rounded-lg">
                            <option value=""></option>
                            <option value="exactly">Exactly</option>
                            <option value="about">About</option>
                            <option value="after">After</option>
                            <option value="before">Before</option>
                        </select>
                    </div>
                    <div>
                        <label for="birth_year" class="block text-gray-700">Year</label>
                        <input type="text" id="edit-birth-year" name="birth_year" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="birth_month" class="block text-gray-700">Month</label>
                        <select id="edit-birth-month" name="birth_month" class="w-full px-4 py-2 border rounded-lg">
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
                        <input type="text" id="edit-birth-date" name="birth_date" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="mb-4 grid grid-cols-4 gap-4">
                    <div>
                        <label for="death_prefix" class="block text-gray-700">Death</label>
                        <select id="edit-death-prefix" name="death_prefix" class="w-full px-4 py-2 border rounded-lg">
                            <option value=""></option>
                            <option value="exactly">Exactly</option>
                            <option value="about">About</option>
                            <option value="after">After</option>
                            <option value="before">Before</option>
                        </select>
                    </div>
                    <div>
                        <label for="death_year" class="block text-gray-700">Year</label>
                        <input type="text" id="edit-death-year" name="death_year" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="death_month" class="block text-gray-700">Month</label>
                        <select id="edit-death-month" name="death_month" class="w-full px-4 py-2 border rounded-lg">
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
                        <input type="text" id="edit-death-date" name="death_date" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>

                <!-- Gender -->
                <div class="mb-4">
                    <label for="gender" class="block text-gray-700">Gender</label>
                    <select id="edit-gender" name="gender" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Select gender...</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="text-center">
                    <button type="submit" class="bg-green-500 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- "Add new relationship" Modal Popup Form -->
    <div id="popupForm" class="modal" style="display: none;">
        <div class="modal-content">
            <div id="modal-header" class="modal-header">
                <span class="close-btn">&times;</span>
                <h2 id="modal-title">Add New Relationship <span id='adding_relationship_to'></span></h2>
            </div>
            <form id="dynamic-form" action="?to=family/tree" method="POST" enctype="multipart/form-data">
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
                        <?php foreach ($individuals as $individual): ?>
                            <option value="<?php echo $individual['id']; ?>">
                                <?php echo $individual['first_names'] . ' ' . $individual['last_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- New Individual Form -->
                <div id="additional-fields" style='display: none'>
                    <div class="mb-4">
                        <label for="first_names" class="block text-gray-700 mr-2">First Name(s)</label>
                        <div class="flex items-center mt-0">
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
                    
                    <!-- Photo -->
                    <div class="mb-4">
                        <label for="photo" class="block text-gray-700">Upload Photo</label>
                        <input type="file" id="photo" name="photo" class="w-full px-4 py-2 border rounded-lg">
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

    <script>
    document.getElementById('lookup').addEventListener('input', function() {
        var input = this.value.toLowerCase();
        var select = document.getElementById('connect_to');
        var options = select.options;
        var hasMatch = false;

        for (var i = 0; i < options.length; i++) {
            var option = options[i];
            var text = option.text.toLowerCase();
            if (text.includes(input)) {
                option.style.display = '';
                hasMatch = true;
            } else {
                option.style.display = 'none';
            }
        }

        select.style.display = hasMatch ? '' : 'none';
    });

    document.getElementById('connect_to').addEventListener('change', function() {
        var selectedValue = this.value;
        if (selectedValue) {
            document.getElementById('form-action').value = 'link_relationship';
            document.getElementById('first_names').removeAttribute('required');
            document.getElementById('last_name').removeAttribute('required');
            document.getElementById('lookup').value = this.options[this.selectedIndex].text;
            this.style.display = 'none';
            document.getElementById('additional-fields').style.display = 'none';
        }
    });
    </script>


</section>
