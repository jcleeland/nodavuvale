<?php

// Check if the user is logged in and their role
$is_admin = ($auth->isLoggedIn() && $auth->getUserRole() === 'admin');




//Handle form submission for updating individuals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_individual') {
    include("helpers/update_individual.php");
}




// Fetch all confirmed individuals
$individuals = $db->fetchAll("SELECT individuals.*, 
                                COALESCE(
                                    (SELECT files.file_path 
                                        FROM file_links 
                                        JOIN files ON file_links.file_id = files.id 
                                        JOIN items ON items.item_id = file_links.item_id 
                                        WHERE file_links.individual_id = individuals.id 
                                        AND items.detail_type = 'Key Image'
                                        LIMIT 1), 
                                    '') AS keyimagepath
                            FROM individuals");
$relationships = $db->fetchAll("SELECT * FROM relationships");

$quickindividuallist = $db->fetchAll("SELECT id, first_names, last_name FROM individuals ORDER BY last_name, first_names");
$quicklist=array();
foreach($quickindividuallist as $individualperson) {
    $quicklist[$individualperson['id']] = $individualperson;
}

$rootId=isset($_SESSION['rootId']) ? $_SESSION['rootId'] : Web::getRootId();


include("helpers/quickedit.php");

include("helpers/add_relationship.php");

$tree_data = Utils::buildTreeData($rootId, $individuals, $relationships);

// Insert a little button that will allow the user to "copy" the $tree_data to the clipboard just at the beginning of the next section

?>
    <!-- shared modal prompt for all pages --> 
    <div id="findOnTree" class="modal">
        <div class="modal-content min-w-sm max-h-screen my-5 overflow-y-auto">
            <div class="modal-header">
                <span id="findOnTreeClose" class="close-story-btn">&times;</span>
                <h2 id="findOnTreeTitle" class="text-xl font-bold mb-4 text-center">Find someone on the tree</h2>
            </div>
            <div class="modal-body">
                <?= Web::showFindIndividualLookAhead($quicklist, 'lookup', 'findOnTree') ?>
                <br />&nbsp;<br />
            </div>
        </div>
    </div>
    
    <script>
        //When the select with the name "findOnTree" changes, zoom to the selected individual
        document.querySelector('select[name="findOnTree"]').addEventListener('change', function() {
            //hide the div
            document.getElementById('findOnTree').style.display = 'none';
            findNodeForIndividualId(this.value)
                .then(nodeId => {
                    //Now remove the characters "node" from the front of the nodeId
                    nodeId = nodeId.replace("node", "");
                    //Now make sure it's a number not a string
                    nodeId = parseInt(nodeId);
                    console.log('Found node: ' + nodeId);
                    tree.zoomToNode(nodeId, 2, 500);
                    // Delay the highlighting feature by 500ms to ensure it runs after the zoom is complete
                    setTimeout(function() {
                        // Now make the div's parent element briefly grow and then shrink
                        var node = document.getElementById("node" + nodeId);
                        var parent = node.parentNode;
                        parent.style.transition = "all 0.5s";
                        parent.style.transformOrigin = "bottom left";
                        parent.style.transform = "scale(1.2)";
                        setTimeout(function() {
                            parent.style.transform = "scale(1)";
                        }, 600);
                    }, 600);
                })
                .catch(error => {
                    console.error(error);
                });
        });
    </script>

<section class="mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <button class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 ml-1 rounded-lg float-right" title="Find person in tree" onclick="viewTreeSearch()"><i class="fas fa-search"></i></button>
    <button class="hidden bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 ml-1 rounded-lg float-right" onclick="navigator.clipboard.writeText(JSON.stringify(tree))">&#128203;</button>
    <button class="add-new-btn bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg float-right" title="Add new individual">+</button>
    <h1 class="text-3xl font-bold mb-6">Family Tree</h1>
    
    <!-- Family Tree Display -->
    <div id="family-tree" class="familytree"></div>

    <script>
        var tree = <?= $tree_data; ?>;
        // Get the width of the current page
        var windowWidth = window.innerWidth;
        // Get the height of the window
        var windowHeight = window.innerHeight-200;
        tree=dTree.init(tree, {
            target: "#family-tree",
            debug: false,
            width: windowWidth,
            height: windowHeight,
            nodeWidth: 100,
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
                    return "<div style='height: 170px'>"+name+"</div>";
                }
            }
        });

        <?php
            if(isset($_GET['zoom'])) {
        ?>
        window.onload = function() {
            findNodeForIndividualId(<?= $_GET['zoom'] ?>)
                .then(nodeId => {
                    //Now remove the characters "node" from the front of the nodeId
                    nodeId = nodeId.replace("node", "");
                    //Now make sure it's a number not a string
                    nodeId = parseInt(nodeId);
                    console.log('Found node: ' + nodeId);
                    tree.zoomToNode(nodeId, 2, 500);
                    // Delay the highlighting feature by 500ms to ensure it runs after the zoom is complete
                    setTimeout(function() {
                        // Now make the div's parent element briefly grow and then shrink
                        var node = document.getElementById("node" + nodeId);
                        var parent = node.parentNode;
                        parent.style.transition = "all 0.5s";
                        parent.style.transformOrigin = "bottom left";
                        parent.style.transform = "scale(1.2)";
                        setTimeout(function() {
                            parent.style.transform = "scale(1)";
                        }, 600);
                    }, 600);


                })
                .catch(error => {
                    console.error(error);
                });
        };
        <?php } ?>
    </script>






</section>
