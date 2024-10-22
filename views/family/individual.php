<?php

//Handle form submission for updating individuals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_individual') {
    include("helpers/update_individual.php");
}

if(!isset($rootId)) {
    $rootId = Web::getRootId();
}

$is_admin = $auth->getUserRole() === 'admin';

include("helpers/add_relationship.php");

include("helpers/quickedit.php");

include("helpers/add_story.php");

include("helpers/add_edit_item.php");



if ($individual_id) {
    // Fetch the individual details
    //    echo "SELECT individuals.*, files.file_path as keyimagepath FROM individuals LEFT JOIN file_links ON file_links.individual_id=individuals.id LEFT JOIN files ON file_links.file_id=files.id LEFT JOIN items ON items.item_id=file_links.item_id AND items.detail_type='Key Image' WHERE individuals.id =";
    $sql="SELECT individuals.* 
    FROM individuals 
    WHERE individuals.id = ?";
    $individual = $db->fetchOne($sql, [$individual_id]);

    //See if there's a key image
    $sql="SELECT files.file_path as keyimagepath
        FROM files
        INNER JOIN file_links ON file_links.file_id=files.id
        INNER JOIN items ON items.item_id=file_links.item_id
        WHERE file_links.individual_id = ? AND files.file_type = 'image' AND items.detail_type='Key Image'";
    $keyimage = $db->fetchOne($sql, [$individual_id]);
    if(!empty($keyimage)) {
        $individual['keyimagepath']=$keyimage['keyimagepath'];
    } else {
        $individual['keyimagepath']="images/default_avatar.webp";
    }

    // Extract just the first word from $individual['first_names']
    $individual['first_name'] = explode(' ', $individual['first_names'])[0];
    $individual['fullname']=$individual['first_name'] . ' ' . $individual['last_name'];

    //See if there are any discussions about this individual
    $discussions = Utils::getIndividualDiscussions($individual_id);

    //Fetch parents
    $parents = Utils::getParents($individual_id);

    //Fetch children
    $children = Utils::getChildren($individual_id);

    //Fetch spouses
    $spouses = Utils::getSpouses($individual_id);
    
    //Fetch siblings
    $siblings = Utils::getSiblings($individual_id);

    // Fetch associated files (photos and documents)
    $photos = Utils::getFiles($individual_id, 'image');


    $documents = $db->fetchAll("SELECT * FROM files WHERE individual_id = ? AND file_type = 'document'", [$individual_id]);

    $items = Utils::getItems($individual_id);

    // Extract the birth details
    $year = $individual['birth_year'];
    $month = isset($individual['birth_month']) && $individual['birth_month'] > 0 ? $individual['birth_month'] : null;
    $day = isset($individual['birth_date']) && $individual['birth_date'] > 0 ? $individual['birth_date'] : null;

    $birthdate="";
    // Check which parts of the date are available
    if ($day && $month && $year) {
        // If all parts are available, show the full date with the day of the week
        $birthdate=date("l, d M Y", strtotime("$year-$month-$day")); // E.g., "Monday, 01 Jan 1840"
    } elseif ($month && $year) {
        // If only the month and year are available, show the month name and year
        $birthdate=date("F Y", strtotime("$year-$month")); // E.g., "January 1840"
    } elseif ($year) {
        // If only the year is available, show just the year
        $birthdate= $year; // E.g., "1840"
    } else {
        // If no birth information is available, show a default message (optional)
        $birthdate= "Unknown";
    }

    // Extract the death details
    $year = $individual['death_year'];
    $month = isset($individual['death_month']) && $individual['death_month'] > 0 ? $individual['death_month'] : null;
    $day = isset($individual['death_date']) && $individual['death_date'] > 0 ? $individual['death_date'] : null;
    
    $deathdate="";
    // Check which parts of the date are available
    if ($day && $month && $year) {
        // If all parts are available, show the full date with the day of the week
        $deathdate=date("l, d M Y", strtotime("$year-$month-$day")); // E.g., "Monday, 01 Jan 1840"
    } elseif ($month && $year) {
        // If only the month and year are available, show the month name and year
        $deathdate=date("F Y", strtotime("$year-$month")); // E.g., "January 1840"
    } elseif ($year) {
        // If only the year is available, show just the year
        $deathdate= $year; // E.g., "1840"
    } else {
        // If no birth information is available, show a default message (optional)
        $deathdate= "";
    }

}


//Gather a list of individuals for the add relationship modal
$individuals = $db->fetchAll("SELECT id, first_names, last_name FROM individuals ORDER BY last_name, first_names");


?>
<input type='hidden' id='individual_brief_name' value='<?= $individual['fullname'] ?>' />
<section class="hero text-white py-20 relative">
    <div class="container hero-content relative">
        <div class="hero-image">
            <img class='bg-opacity-10 border-2' id='keyImage' src="<?= $individual['keyimagepath'] ?>" alt="Photo of <?= $individual['first_name'] ?>" >
                <button onclick="triggerKeyPhotoUpload()" class="text-white bg-gray-800 bg-opacity-50 rounded-full p-2" title="Change <?= $individual['first_name'] ?>'s Key Image">
                    <i class="fas fa-camera"></i> <!-- FontAwesome icon -->
                </button>
                <input type="file" id="keyPhotoUpload" style="display: none;" onchange="uploadKeyImage('<?= $individual['id'] ?>')">
        </div>    
        <div class="hero-text text-center mx-auto">
            <h2 class="text-4xl font-bold"><?php echo $individual['first_names'] . ' ' . $individual['last_name']; ?></h2>
            <p class="mt-2 text-lg"><?= $individual['birth_prefix']; ?> <?= $birthdate ?> - <?= $individual['death_prefix'] ?> <?= $deathdate ?></p>
        </div>
        <div id="individual-options" class="absolute w-full bottom-0 left-0 rounded-b-lg p-0 m-0">
            <button class="bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-10" title="View <?= $individual['first_name'] ?> on the tree" onclick="window.location.href='index.php?to=family/tree&zoom=<?= $individual['id'] ?>'">
                <i class="fas fa-network-wired"></i> <!-- FontAwesome icon -->
            </button>
            <button class="bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-10" data-individual-id="<?= $individual['id'] ?>" title="<?= $individual['first_name'] ?> is me" onclick="triggerAddIndividual()">
                <i class="fas fa-user-circle"></i> <!-- FontAwesome icon -->
            </button>
            <button class="edit-btn bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-10" title="Edit <?= $individual['first_name'] ?>" data-individual-id="<?= $individual['id'] ?>" >
                <i class="fas fa-edit"></i> <!-- FontAwesome icon -->
            </button>
        </div>
    </div>
    <div class="tabs absolute -bottom-0 text-sm md:text-lg gap-2">
        <div class="tab active px-4 py-2" data-tab="generaltab">General</div>
        <div class="tab px-4 py-2" data-tab="relationshipstab">Relationships</div>
        <div class="tab px-4 py-2" data-tab="mediatab">Media</div>
    </div>
</section>







<div class="tab-content active" id="generaltab">

    <section class="container mx-auto py-6 ">
    <div class="text-center p-2">
            <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                 <!-- Display Stories -->
                <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                    Stories
                    <button class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 -right-2 -top-2 z-10 font-normal text-sm" title="Add a story about <?= $individual['first_name'] ?>" onclick="openStoryModal('<?= $individual['id'] ?>', '<?= $individual['first_name'] ?>');">
                        <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
                    </button>
                </h3>
                <div class="p-6 bg-white shadow-lg rounded-lg text-left">
                    <div class="grid grid-cols-1 gap-8">
                        <?php foreach($discussions as $discussion): ?>
                            <?php $avatar_path=isset($discussion['avatar']) ? $discussion['avatar'] : 'images/default_avatar.webp'; ?>
                            <div class="discussion-item"> 
                                <img src="<?= htmlspecialchars($avatar_path) ?>" alt="User Avatar" class="avatar-img-md avatar-float-left object-cover" title="<?= $discussion['first_name'] ?> <?= $discussion['last_name'] ?>">                
                                <div class='discussion-content'>
                                    <div class="text-sm text-gray-500 relative">
                                        <b><?= $discussion['first_name'] ?> <?= $discussion['last_name'] ?></b><br />
                                        <span title="<?= date('F j, Y, g:i a', strtotime($discussion['created_at'])) ?>"><?= $web->timeSince($discussion['created_at']); ?></span>
                                        <?php if ($is_admin || $_SESSION['user_id'] == $discussion['user_id']): ?>
                                            <button type="button" title="Edit this story" onClick="editStory(<?= $discussion['id'] ?>);" class="absolute text-burnt-orange bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-10 top-2 font-normal text-xs">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" title="Delete this story" onClick="deleteStory(<?= $discussion['id'] ?>);" class="absolute text-burnt-orange bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-2 top-2 font-normal text-xs">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>  
                                    </div>
                                    <h3 class="text-2xl font-bold"><?= htmlspecialchars($discussion['title']) ?></h3>
                                    <p class="mt-2"><?= htmlspecialchars($discussion['content']) ?></p>
                                    <div class="discussion-reactions" data-discussion-id="<?= $discussion['id'] ?>">
                                        <svg alt="Like" class="like-image" viewBox="0 0 32 32" xml:space="preserve" width="18px" height="18px" fill="#000000">
                                            <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                            <g id="SVGRepo_iconCarrier">
                                                <style type="text/css">
                                                    .st0{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:10;}
                                                    .st1{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
                                                    .st2{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:5.2066,0;}
                                                </style>
                                                <path class="st0" d="M11,24V14H5v12h6v-2.4l0,0c1.5,1.6,4.1,2.4,6.2,2.4h6.5c1.1,0,2.1-0.8,2.3-2l1.5-8.6c0.3-1.5-0.9-2.4-2.3-2.4H20V6.4C20,5.1,18.7,4,17.4,4h0C16.1,4,15,5.1,15,6.4v0c0,1.6-0.5,3.1-1.4,4.4L11,13.8"></path>
                                            </g>
                                        </svg>
                                        <div class="reaction-buttons" title="Reactions">
                                            <button class="reaction-btn" data-reaction="like" title="Like">👍</button>
                                            <button class="reaction-btn" data-reaction="love" title="Love">❤️</button>
                                            <button class="reaction-btn" data-reaction="haha" title="Haha">😂</button>
                                            <button class="reaction-btn" data-reaction="wow" title="Wow">😮</button>
                                            <button class="reaction-btn" data-reaction="sad" title="Sad">😢</button>
                                            <button class="reaction-btn" data-reaction="angry" title="Angry">😡</button>
                                            <button class="reaction-btn" data-reaction="care" title="Care">🤗</button>
                                            <button class="reaction-btn" data-reaction="remove" title="Remove">❌</button>
                                        </div>
                                        <div class="reaction-summary-container">
                                            <div class="reaction-summary">
                                                <!-- This will be filled dynamically with AJAX -->
                                            </div>
                                            
                                        </div>                                       
                                    </div>

                                    <div class="comments mt-4">
                                        <!-- Fetch and display comments -->
                                        <?php if (!empty($discusson['comments'])): ?>
                                            <h4 class="font-semibold">Comments:</h4>
                                            <?php foreach ($discussion['comments'] as $comment): ?>
                                                <div class="bg-gray-100 p-4 rounded-lg mt-2">
                                                    <img src="<?= isset($comment['avatar']) ? $comment['avatar'] : 'images/default_avatar.webp' ?>" alt="User Avatar" class="avatar-img-sm avatar-float-left object-cover" title="<?= $comment['first_name'] ?> <?= $comment['last_name'] ?>">
                                                    <div class="comment-content">
                                                        <div class="text-sm text-gray-500 relative">
                                                            <b><?= htmlspecialchars($comment['first_name']) ?> <?= $comment['last_name'] ?></b><br />
                                                            <span title="<?= date('F j, Y, g:i a', strtotime($comment['created_at'])) ?>"><?= $web->timeSince($comment['created_at']); ?></span>
                                                            <?php if ($is_admin || $_SESSION['user_id'] == $comment['user_id']): ?>
                                                                <button type="button" title="Delete this story" onClick="deleteStoryComment(<?= $comment['id'] ?>);" class="absolute text-burnt-orange bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-2 top-2 font-normal text-xs">
                                                                    Delete
                                                                </button>
                                                            <?php endif; ?>                                                        
                                                        </div>
                                                        <p><?= htmlspecialchars($comment['comment']) ?></p>
                                                        <div class="comment-reactions" data-comment-id="<?= $comment['id'] ?>">
                                                            <svg alt="Remove Reaction" class="remove-reaction-image" viewBox="0 0 32 32" xml:space="preserve" width="18px" height="18px" fill="#000000">
                                                                <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                                                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                                                <g id="SVGRepo_iconCarrier">
                                                                    <style type="text/css">
                                                                        .st0{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:10;}
                                                                        .st1{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
                                                                        .st2{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:5.2066,0;}
                                                                    </style>
                                                                    <path class="st0" d="M11,24V14H5v12h6v-2.4l0,0c1.5,1.6,4.1,2.4,6.2,2.4h6.5c1.1,0,2.1-0.8,2.3-2l1.5-8.6c0.3-1.5-0.9-2.4-2.3-2.4H20V6.4C20,5.1,18.7,4,17.4,4h0C16.1,4,15,5.1,15,6.4v0c0,1.6-0.5,3.1-1.4,4.4L11,13.8"></path>
                                                                </g>
                                                            </svg>
                                                            <div class="reaction-buttons" title="Reactions">
                                                                <button class="reaction-btn" data-reaction="like" title="Like">👍</button>
                                                                <button class="reaction-btn" data-reaction="love" title="Love">❤️</button>
                                                                <button class="reaction-btn" data-reaction="haha" title="Haha">😂</button>
                                                                <button class="reaction-btn" data-reaction="wow" title="Wow">😮</button>
                                                                <button class="reaction-btn" data-reaction="sad" title="Sad">😢</button>
                                                                <button class="reaction-btn" data-reaction="angry" title="Angry">😡</button>
                                                                <button class="reaction-btn" data-reaction="care" title="Care">🤗</button>
                                                                <button class="reaction-btn" data-reaction="remove" title="Remove">❌</button>
                                                            </div>
                                                            <div class="reaction-summary-container">
                                                                <div class="reaction-summary">
                                                                    <!-- This will be filled dynamically with AJAX -->
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Add a new comment -->
                                    <form method="POST" class="comments mt-4 relative">
                                        <textarea name="comment" rows="3" class="w-full border rounded-lg p-2" placeholder="Add a comment..." required></textarea>
                                        <input type="hidden" name="discussion_id" value="<?= $discussion['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>"> <!-- Assuming user is logged in -->
                                        
                                        <button type="submit" title="Post comment" class="submit-button mt-2 bg-deep-green text-white py-1 px-2 rounded-lg hover:bg-burnt-orange">
                                            <i class="fa fa-paper-plane"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($discussions)): ?>
                            No stories have been shared about <?= $individual['first_name'] ?> yet. Be the first!
                        <?php endif; ?>
                    </div>
                </div>
            </div>
    </section>

    <section class="container mx-auto py-6">
        <!-- Display Items -->
        <div class="text-center p-2">
            <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                Facts and Events
                <button class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-0 top-0 z-10 font-normal text-sm" title="Add an event or item about <?= $individual['first_name'] ?>" onclick="openEventModal('add_item', '<?= $individual['id'] ?>');">
                    <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
                </button>            
            </h3>
            <div class="document-list grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-6 p-6 bg-white shadow-lg rounded-lg relative">
                <?php foreach ($items as $key=>$itemgroup): ?> 
                    <div id="item_id_<?= $itemgroup[0]['item_id'] ?>" class="document-item mb-4 text-center p-1 shadow-lg rounded-lg text-sm relative">
                        <button class="absolute text-burnt-orange bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 -right-2 -top-2 font-normal text-xs" title="Delete this item" onclick="doAction('delete_item', '<?= $individual['id'] ?>', '<?= $itemgroup[0]['item_id'] ?>');">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php if($key=="Singleton") $groupTitle=$itemgroup[0]['detail_type']; else $groupTitle=$key; ?>
                        <b><?= $groupTitle ?></b><br />
                        <?php foreach ($itemgroup as $item): ?>
                                <b class="text-xs"><?= $item['detail_type'] == $groupTitle ? "" : $item['detail_type'] ?></b><br />
                                <?php if (!empty($item['detail_value'])): ?>
                                    <p id="item_<?= $item['item_id'] ?>" class="mb-2 text-gray-600 text-xs" onDblClick="triggerEditItemDescription('item_<?= $item['item_id'] ?>')">
                                        <?php echo htmlspecialchars($web->truncateText($item['detail_value'], 100)); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if(!empty($item['file_id'])): ?>
                                    <?php if($item['file_type']=='image'): ?>
                                        <img src="<?= $item['file_path'] ?>" alt="<?= $item['detail_value'] ?>" class="w-full h-auto rounded-lg">
                                    <?php else: ?>
                                        <a href="<?= $item['file_path'] ?>" target="_blank" class="text-blue-600 hover:text-blue-800">View Document</a>
                                    <?php endif; ?>
                                    <?php if (!empty($item['file_description'])): ?>
                                        <p class="mt-2 text-xs text-gray-600"><?= $item['file_description'] ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <button class="absolute text-ocean-blue -right-1 -bottom-1 text-xs rounded-full p-0 m-0">
                                    <i class="fas fa-info-circle" title="Added by <?= $item['first_name'] ?> <?= $item['last_name'] ?> on <?= date("d M Y", strtotime($item['updated'])); ?>"></i>
                                </button>
                        <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
            </div>
        </div>
    </section>    
</div>








<div class="tab-content" id="relationshipstab">
    <section class="container mx-auto py-6">
        <div class="grid grid-cols-1 lg:grid-cols-2">


            <!-- Display Spouse(s) -->
            <div class="text-center p-2">
                <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                    Spouses
                    <button class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-0 top-0 z-10 font-normal text-sm" title="Add a spouse to <?= $individual['first_name'] ?>" onclick="openModal('add_spouse', '<?= $individual['id'] ?>', '<?= $individual['gender'] ?>');">
                        <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
                    </button>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6 bg-white shadow-lg rounded-lg grid-scrollable-3 place-items-center relative">
                    <?php foreach ($spouses as $spouse): ?>
                        <?= $web->individual_card($spouse) ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Display Children -->
            <div class="text-center p-2">
                <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                    Children (<?= count($children) ?>)
                    <button class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-0 top-0 z-10 font-normal text-sm" title="Add a child to <?= $individual['first_name'] ?>" onclick="openModal('add_child', '<?= $individual['id'] ?>', '<?= $individual['gender'] ?>');">
                        <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
                    </button>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6 bg-white shadow-lg rounded-lg grid-scrollable-3 place-items-center relative">
                    <?php foreach ($children as $child): ?>
                        <?= $web->individual_card($child) ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Display Parents -->
            <div class="text-center p-2">
                <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                    Parents
                    <button class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-0 top-0 z-10 font-normal text-sm" title="Add a parent to <?= $individual['first_name'] ?>" onclick="openModal('add_parent', '<?= $individual['id'] ?>', '<?= $individual['gender'] ?>');">
                        <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
                    </button>                
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6 bg-white shadow-lg rounded-lg grid-scrollable-3 place-items-center">
                    <?php foreach ($parents as $parent): ?>
                        <?= $web->individual_card($parent) ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Display Siblings -->
            <div class="text-center p-2">
                <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                    Siblings
                    <button class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-0 top-0 z-10 font-normal text-sm" title="Add a sibling to <?= $individual['first_name'] ?>" onclick="openModal('add_sibling', '<?= $individual['id'] ?>', '<?= $individual['gender'] ?>');">
                        <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
                    </button>                
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6 bg-white shadow-lg rounded-lg grid-scrollable-3 place-items-center relative">
                    <?php foreach ($siblings as $sibling): ?>
                        <?= $web->individual_card($sibling) ?>
                    <?php endforeach; ?>
                </div>
            </div>        

        </div>
    </section>
</div>

<div class="tab-content" id="mediatab">
    <section class="container mx-auto py-6">    
        <!-- Display Photos -->
        <div class="text-center p-2">
            <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                Photos
                <button onclick="triggerPhotoUpload()" class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-0 top-0 z-10 font-normal text-sm" title="Add a photo of <?= $individual['first_name'] ?>">
                    <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
                </button>
                <input type="file" id="photoUpload" style="display: none;" onchange="uploadPhoto('<?= $individual['id'] ?>')">
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-6 p-6 bg-white shadow-lg rounded-lg relative">
            <?php foreach ($photos as $photo): ?>
                    <div id="file_id_<?= $photo['id'] ?>" class="photo-item mb-4 text-center p-1 shadow-lg rounded-lg relative">
                        <button class="absolute text-burnt-orange bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-0 top-0 font-normal text-xs" title="Delete this item" onclick="doAction('delete_photo', '<?= $individual['id'] ?>', '<?= $photo['id'] ?>');">
                            <i class="fas fa-trash"></i>
                        </button>                    
                        <a href="<?php echo $photo['file_path']; ?>" target="_blank">
                            <img src="<?php echo $photo['file_path']; ?>" alt="Photo of <?php echo $individual['first_name']; ?>" class="w-full h-auto rounded-lg">
                        </a>
                        <?php if (!empty($photo['file_description'])): ?>
                            <p id="photo_<?= $photo['id'] ?>" class="mt-2 text-xs text-gray-600" onDblClick="triggerEditFileDescription('photo_<?= $photo['id'] ?>')"><?php echo $photo['file_description']; ?></p>
                        <?php endif; ?>
                            <button class="absolute text-ocean-blue -right-1 -bottom-1 text-xs rounded-full p-0 m-0">
                                <i class="fas fa-info-circle" title="Added by <?= $photo['first_name'] ?> <?= $photo['last_name'] ?> on <?= date("d M Y", strtotime($photo['upload_date'])); ?>"></i>
                            </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="container mx-auto py-6">
        <!-- Display Documents -->
        <div class="text-center p-2">
            <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                Documents
                <button class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-0 top-0 z-10 font-normal text-sm" title="Add a document about <?= $individual['first_name'] ?>">
                    <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
                </button>  
            </h3>
            <div class="document-list grid grid-cols-1 md:grid-cols-3 gap-6 p-6 bg-white shadow-lg rounded-lg relative">
            

                <?php foreach ($documents as $document): ?>
                    <div class="document-item mb-4 text-center p-1 shadow-lg rounded-lg">
                        <a href="<?php echo $document['file_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                            View Document
                        </a>
                        <?php if (!empty($document['file_description'])): ?>
                            <p class="mt-1 text-sm text-gray-600"><?php echo $document['file_description']; ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>
