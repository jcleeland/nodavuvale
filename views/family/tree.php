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
    include("helpers/update_individual.php");
}




// Fetch all confirmed individuals and their relationships
$individuals = $db->fetchAll("SELECT * FROM individuals");
$relationships = $db->fetchAll("SELECT * FROM relationships");

$rootId=Web::getRootId();

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
            debug: false,
            width: windowWidth,
            height: windowHeight,
            nodeWidth: 240,
            styles: {
                node: 'node',
                linage: 'linage',
                marriage: 'marriage',
                text: 'nodeText',
            },
            connectors: {
                type: 'curve',  // straight, curve, step, elbow
                style: {
                    stroke: '#555',
                    strokeWidth: 1.5,
                    strokeLinecap: 'round'
                },
                curveRadius: 10,
                curveFactor: 0.7,
            },
            callbacks: {
                nodeClick: function(name, extra) {
                    //console.log(name);
                },
                textRenderer: function (name, extra, textClass) {
                    return "<div style='min-height: 100px'>"+name+"</div>";
                }
            }
        });

    </script>



    <?php include("helpers/quickedit.php"); ?>



    <?php include("helpers/add_relationship.php"); ?>



</section>
