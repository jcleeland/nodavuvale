<?php
/*
    @uses Database class
    @uses Auth class
    @uses Web class
    @uses Utils class
*/
//Handle form submission for updating individuals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_individual') {
    include("helpers/update_individual.php");
}

//If the page is loaded with a $_GET['discussion_id'] parameter, scroll so that the top of that div is at the top of the screen
if (isset($_GET['discussion_id'])) {
    ?>
    <script>
        //Show the general tab (where the discussions are)
        document.addEventListener('DOMContentLoaded', function() {
            showTab('generaltab');
            var discussionElement = document.getElementById('discussion_id_<?= $_GET['discussion_id'] ?>');
            if (discussionElement) {
                discussionElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                window.scrollTo({
                    top: discussionElement.getBoundingClientRect().top + window.scrollY - document.documentElement.clientTop,
                    behavior: 'smooth'
                });
            }
        });
    </script>
    <?php
}

if (isset($_GET['item_group_id'])) {
    ?>
    <script>
        //Show the general tab (where the discussions are)
        document.addEventListener('DOMContentLoaded', function() {
            showTab('generaltab');
            var eventElement = document.getElementById('item_group_id_<?= $_GET['item_group_id'] ?>');
            if (eventElement) {
                eventElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                window.scrollTo({
                    top: eventElement.getBoundingClientRect().top + window.scrollY - document.documentElement.clientTop,
                    behavior: 'smooth'
                });
            }
        });
    </script>
    <?php
}

if(isset($_GET['tab'])) {
    ?>
    <script>
        //Show the general tab (where the discussions are)
        document.addEventListener('DOMContentLoaded', function() {
            showTab('<?= $_GET['tab'] ?>');
        });
    </script>
    <?php
}

if(!isset($rootId)) {
    $rootId = Web::getRootId();
}

$is_admin = $auth->getUserRole() === 'admin';

//Gather a list of individuals for the add relationship modal
$individuallist = $db->fetchAll("SELECT id, first_names, last_name FROM individuals ORDER BY last_name, first_names");
$individuals=array();
foreach($individuallist as $individualperson) {
    $individuals[$individualperson['id']] = $individualperson;
}

include("helpers/add_relationship.php");

include("helpers/quickedit.php");

include("helpers/add_story.php");

include("helpers/add_edit_item.php");

//Add the "privacy" class to the $item_types array
//Extract the array_keys from $item_styles
$item_types['Private']=array('Private');
$item_styles['Private']='private';



if ($individual_id) {
    // Fetch the individual details
    $sql="SELECT individuals.* 
    FROM individuals 
    WHERE individuals.id = ?";
    $individual = $db->fetchOne($sql, [$individual_id]);

    //See if the individual matches a user
    $user = $db->fetchOne("SELECT id as user_id, email, first_name, last_name, avatar, role, show_presence, last_login, last_view, individuals_id FROM users WHERE individuals_id = ?", [$individual_id]);
    if($user) {
        if(empty($user['avatar'])) {
            $user['avatar']="images/default_avatar.webp";
        }
    }

    //If this person is a member, use their membership name
    $firstnames=($user && $user['first_name']) ? $user['first_name'] : $individual['first_names'];
    $firstname=explode(' ', $firstnames)[0];
    $lastname=($user && $user['last_name']) ? $user['last_name'] : $individual['last_name'];

    // See if this person is deceased
    $is_deceased = ($individual['death_year'] > 0 || $individual['death_year'] != null || $individual['is_deceased'] == 1) ? true : false;

    //Find an avatar
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
    $treeavatar= $individual['keyimagepath'] ? $individual['keyimagepath'] : "images/default_avatar.webp";
    $useravatar=($user && $user['avatar']) ? $user['avatar'] : $treeavatar;
    if(isset($user['show_presence']) && $user['show_presence'] == 1 && $user['last_view']) {                        
        $activityclass = strtotime($user['last_view']) > strtotime('-15 minutes') ? 'useronline' : 'useroffline'; 
        $activityinfo = $web->timeSince($user['last_view']);
    } else {
        $activityclass = '';
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

    // Fetch the line of descendancy
    $descendancy=Utils::getLineOfDescendancy(Web::getRootId(), $individual_id);  

    if($_SESSION['individuals_id']) {
        $commonAncestor=Utils::getCommonAncestor($_SESSION['individuals_id'], $individual_id);
        $relationshipLabel=Utils::getRelationshipLabel($_SESSION['individuals_id'], $individual_id);
    } else {
        $commonAncestor=[];
        $relationshipLabel="";
    }
    //echo "<pre>"; print_r($commonAncestor); echo "</pre>";
    
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





?>
<input type='hidden' id='individual_brief_name' value='<?= $individual['fullname'] ?>' />
<section class="hero text-white py-20 relative">
    <div class="container hero-content relative">
        <div class="hero-image">
            <img class='bg-opacity-10 border-2' id='keyImage' src="<?= $treeavatar ?>" alt="Photo of <?= $firstname ?>" >
                <button onclick="triggerKeyPhotoUpload()" class="text-white bg-gray-800 bg-opacity-50 rounded-full p-2" title="Change <?= $firstname ?>'s Key Image">
                    <i class="fas fa-camera"></i> <!-- FontAwesome icon -->
                </button>
                <input type="file" id="keyPhotoUpload" style="display: none;" onchange="uploadKeyImage('<?= $individual['id'] ?>')">
        </div>    
        <div class="hero-text text-center mx-auto">
            <h2 class="text-4xl font-bold"><?php echo $firstnames. ' ' . $lastname; ?></h2>
            <p class="mt-0"><span class="mt-2 text-xs text-cream italic rounded-lg px-1 mt-0"><?= $relationshipLabel ?></span></p>
            <p class="mt-2 text-lg"><?= $individual['birth_prefix']; ?> <?= $birthdate ?> - <?= $individual['death_prefix'] ?> <?= $deathdate ?></p>
        </div>
        <div id="individual-options" class="absolute flex justify-between items-center w-full bottom-0 left-0 rounded-b-lg p-0 m-0">
            <button class="flex-1 bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" title="View <?= $individual['first_name'] ?> in the family tree" onclick="window.location.href='index.php?to=family/tree&zoom=<?= $individual['id'] ?>&root_id=<?= $rootId ?>'">
                <i class="fas fa-network-wired" style="transform: rotate(180deg)"></i> <!-- FontAwesome icon -->
            </button>
            <button class="flex-1 bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" title="View the tree showing <?= $individual['first_name'] ?>'s descendants" onclick="window.location.href='index.php?to=family/tree&root_id=<?= $individual['id'] ?>'">
                <i class="fas fa-network-wired"></i> <!-- FontAwesome icon -->
            </button>
            <button class="flex-1 edit-btn bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" title="Edit <?= $individual['first_name'] ?>" data-individual-id="<?= $individual['id'] ?>">
                <i class="fas fa-edit"></i> <!-- FontAwesome icon -->
            </button>
            <?php if(!$user && !$is_deceased): ?>
                <button class="flex-1 bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" data-individual-id="<?= $individual['id'] ?>" title="<?= $individual['first_name'] ?> is me" onclick="triggerAddIndividual()">
                    <i class="fas fa-user-circle"></i> <!-- FontAwesome icon -->
                </button>
            <?php endif; ?>
        </div>
    </div>


    <div class="tabs absolute -bottom-0 text-sm md:text-lg gap-2">
        <?php 
        $nouserclass="active";
        if($user): 
            $nouserclass="";
        ?>
            <div class="tab active px-4 py-0" data-tab="membertab" title="This person is registered on this website. Find out more about them...">
                <img src="<?= $useravatar ?>" alt="<?= $user['first_name'] ?> <?= $user['last_name'] ?>" class="avatar-img-sm object-cover mt-1 p-1 h-1 <?=$activityclass ?>" title="<?= $user['first_name'] ?> <?= $user['last_name'] ?>">
            </div>
        <?php endif; ?>
        <div class="tab $nouserclass px-4 py-2" data-tab="generaltab" title="View (or add-to) facts about this person's life, and read or share stories for ancestors">General</div>
        <div class="tab px-4 py-2" data-tab="relationshipstab" title="View (or add-to) the family tree relationships for this person">Relationships</div>
        <div class="tab px-4 py-2" data-tab="mediatab" title="View (or add-to) photos, videos, documents and other digital records about this person">Media</div>
    </div>
</section>





<?php if($descendancy): ?>
    <section class="container mx-auto pt-6 pb-2 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap justify-center items-center text-xxs sm:text-sm">
            <?php
                $commonancestorclass="bg-burnt-orange-800";
                $commonancestortitle="";

                $ancestorInCommon=true; //We start assuming a common ancestor
            ?>            
            <?php foreach($descendancy as $index => $descendant): ?>
                <?php
                    if($commonAncestor) {
                        if($ancestorInCommon) {
                            $commonancestorclass="border-b-2 border-burnt-orange-800 nv-border-opacity-50 bg-burnt-orange-800";
                            $commonancestortitle="Common Ancestor";
                        } else {
                            $commonancestorclass="bg-burnt-orange-800";
                            $commonancestortitle="";
                        }
                        if($descendant[1]==$commonAncestor['common_ancestor_id']) {
                            $ancestorInCommon=false; //Now that we've found the common ancestor, we can stop highlighting
                        }

                    }
                ?>
                <div class="<?= $commonancestorclass ?> nv-bg-opacity-20 text-center p-1 sm:p-2 my-1 sm:my-2 rounded-lg cursor-pointer" title="<?= $commonancestortitle ?>">
                    <a href='?to=family/individual&individual_id=<?= $descendant[1] ?>'><?= $descendant[0] ?></a>
                </div>
                <?php if ($index < count($descendancy) - 1): ?>
                    <i class="fas fa-arrow-right mx-2"></i> <!-- FontAwesome arrow icon -->
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<div class="tab-content" id="membertab">
    <section class="container mx-auto ">
    <?php 
    if($user): 
        $user_id = $user['user_id'];
        include("helpers/user.php");
    endif; 
    ?>
    </section>
</div>





<div class="tab-content active" id="generaltab">
    <?php if($is_deceased || ($_SESSION['user_id'] == $user['user_id'])) { ?>
    <?php 
    /*********************************************
     * Only show the stories page if the individual is deceased
     *********************************************/
    ?>
    <section class="container mx-auto ">
        <div class="text-center p-2">
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
                        <?php 
                            $avatar_path=isset($discussion['avatar']) ? $discussion['avatar'] : 'images/default_avatar.webp'; 
                        ?>
                        <div class="discussion-item" id="discussion_id_<?= $discussion['id'] ?>">
                            <a href='<?= "?to=family/users&user_id=".$discussion['user_id'] ?>'>
                                <img src="<?= htmlspecialchars($avatar_path) ?>" alt="User Avatar" class="avatar-img-md avatar-float-left object-cover mr-1 <?php echo $auth->getUserPresence($user_id) ? 'userpresent' : 'userabsent'; ?>" title="<?= $discussion['first_name'] ?> <?= $discussion['last_name'] ?>">                
                            </a>
                            <div class='discussion-content'>
                                <div class="text-sm text-gray-500 relative">
                                    Posted by <b>
                                        <a href='<?= "?to=family/users&user_id=".$discussion['user_id'] ?>'><?= $discussion['first_name'] ?> <?= $discussion['last_name'] ?></a>
                                    </b><br />
                                    <span title="<?= date('F j, Y, g:i a', strtotime($discussion['created_at'])) ?>">
                                        <?= $web->timeSince($discussion['created_at']); ?>
                                    </span>
                                    <?php if ($is_admin || $_SESSION['user_id'] == $discussion['user_id']): ?>
                                        <button type="button" title="Edit this story" onClick="editDiscussion(<?= $discussion['id'] ?>);" class="absolute text-burnt-orange bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-10 top-2 font-normal text-xs">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" title="Delete this story" onClick="deleteDiscussion(<?= $discussion['id'] ?>);" class="absolute text-burnt-orange bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-2 top-2 font-normal text-xs">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>  
                                </div>
                                <h3 class="text-2xl font-bold" id="discussion_title_<?= $discussion['id'] ?>"><?= htmlspecialchars($discussion['title']) ?></h3>
                                <?php $content = $web->truncateText(nl2br($discussion['content']), '100', 'read more...', 'individualstory_'.$discussion['id'], "expand"); ?>
                                <div class="mt-2" id="discussion_text_<?= $discussion['id'] ?>"><?= stripslashes($content) ?></div>
                                <div class="mt-2 hidden" id="fullindividualstory_<?= $discussion['id'] ?>"><?= nl2br($discussion['content']) ?></div>
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
                                    <?php
                                    if ($discussion['comments']): 
                                    ?>
                                        <h4 class="font-semibold">Comments:</h4>
                                        <?php foreach ($discussion['comments'] as $comment): ?>
                                            <div class="bg-gray-100 p-4 rounded-lg mt-2">
                                                <a href='<?= "?to=family/users&user_id=".$comment['user_id'] ?>'>
                                                    <img src="<?= isset($comment['avatar']) ? $comment['avatar'] : 'images/default_avatar.webp' ?>" alt="User Avatar" class="avatar-img-sm avatar-float-left object-cover mr-1 <?php echo $auth->getUserPresence($comment['user_id']) ? 'userpresent' : 'userabsent'; ?>" title="<?= $comment['first_name'] ?> <?= $comment['last_name'] ?>">
                                                </a>
                                                <div class="comment-content">
                                                    <div class="text-sm text-gray-500 relative">
                                                        Added by <b><?= htmlspecialchars($comment['first_name']) ?> <?= $comment['last_name'] ?></b><br />
                                                        <span title="<?= date('F j, Y, g:i a', strtotime($comment['created_at'])) ?>"><?= $web->timeSince($comment['created_at']); ?></span>
                                                        <?php if ($is_admin || $_SESSION['user_id'] == $comment['user_id']): ?>
                                                            <button type="button" title="Delete this story" onClick="deleteStoryComment(<?= $comment['id'] ?>);" class="absolute text-burnt-orange bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-2 top-2 font-normal text-xs">
                                                                Delete
                                                            </button>
                                                        <?php endif; ?>                                                        
                                                    </div>
                                                    <p><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
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
                                    
                                    <button type="submit" name="new_comment" title="Post comment" class="submit-button mt-2 bg-deep-green text-white py-1 px-2 rounded-lg hover:bg-burnt-orange">
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
    <?php } ?>

    <section class="container mx-auto ">
        <!-- Display Items -->
        <div class="text-center p-2">
            <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                Facts and Events
                <button class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-0 top-0 z-10 font-normal text-sm" title="Add an event or item about <?= $individual['first_name'] ?>" onclick="openEventModal('add_item', '<?= $individual['id'] ?>');">
                    <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
                </button>            
            </h3>
            <div class="document-list grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6 p-6 bg-white shadow-lg rounded-lg relative">
                <?php 
                foreach ($items as $key=>$itemgroup):
                    //$firstItem=reset($itemgroup);
                    //echo "<pre>"; print_r($itemgroup); echo "</pre>";
                    if($itemgroup['item_group_name'] == "Private") {
                        //Show a "private" div
                        ?>
                        <div class="document-item mb-4 text-center p-1 shadow-lg rounded-lg text-sm relative">
                            <div class="bg-cream-800 nv-bg-opacity-20 rounded p-0.5 text-left relative text-brown nv-text-opacity-50">
                                <div class='text-center'>
                                    <b class="text-xs mb-2 ">Private</b>
                                </div>
                                <div class="text-center text-burnt-orange nv-text-opacity-50 italic text-xxs p-3">
                                    This information is protected until the owner agrees to share it.
                                </div>
                            </div>
                        </div>
                        <?php
                        continue;
                    }
                    $privacystamp="";
                    if ($itemgroup['privacy'] == "private") {
                        //Add the tailwind class for the cursor to show the information icon
                        $privacystamp="<div class='relative' title='The owner of this information has asked for it to be kept private'><div class='stamp stamp-red-double cursor-info'>Private</div></div>";
                    }
                    $is_group = count($itemgroup['items']) > 1 ? true : false; 
                    ?>
                    
                    <?php 
                    if($is_group) {
                        //Order the items in the itemgroup according to the item_styles
                        $reference=$item_types[$itemgroup['item_group_name']];
                        if($itemgroup['item_group_name'] == "Birth" || $itemgroup['item_group_name'] == "Death") {
                            //insert "Date" at the beginning of the array
                            array_unshift($reference, "Date");
                            //echo "<pre>"; print_r($reference); echo "</pre>";
                        }
                        $groupTitle=$itemgroup['item_group_name'];
                    ?>
                        <div id="item_group_id_<?= $key ?>" class="document-item mb-4 text-center p-1 shadow-lg rounded-lg text-sm relative">
                            <button class="absolute text-burnt-orange nv-text-opacity-20 bg-gray-800 bg-opacity-10 rounded-full py-1 px-2 m-0 -right-2 -top-2 font-normal text-xs" title="Delete <?= $groupTitle ?>" onclick="doAction('delete_item_group', '<?= $individual['id'] ?>', '<?= $itemgroup['items'][0]['item_identifier'] ?>');">
                                <i class="fas fa-trash"></i>
                            </button>
                    <?php                                 
                    } else {
                        //echo "<pre>"; print_r($itemgroup); echo "</pre>";
                        $reference=(isset($item_types[$itemgroup['items'][0]['detail_type']])) ? $item_types[$itemgroup['items'][0]['detail_type']] : [$itemgroup['items'][0]['detail_type']];    
                        $groupTitle=$itemgroup['item_group_name'];
                        ?>
                        <div id="item_id_<?= $itemgroup['items'][0]['item_id'] ?>" class="document-item mb-4 text-center p-1 shadow-lg rounded-lg text-sm relative">
                            <button class="absolute text-burnt-orange nv-text-opacity-20 bg-gray-800 bg-opacity-10 rounded-full py-1 px-2 m-0 -right-2 -top-2 font-normal text-xs" title="Delete <?= $groupTitle ?>" onclick="doAction('delete_item', '<?= $individual['id'] ?>', '<?= $itemgroup['items'][0]['item_id'] ?>');">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php                        
                    }
                    $thisitem=array();
                    foreach($itemgroup['items'] as $itemdetail) {
                        //echo "<pre>"; print_r($itemdetail); echo "</pre>";
                        $thisitem[$itemdetail['detail_type']]=$itemdetail;
                    }
                    //echo "<pre>"; print_r($thisitem); echo "</pre>";
                    $incompleteItems=[];
                    ?>
                            <div class="item_header p-1 rounded mb-2 bg-brown text-white">
                                <b><?= $groupTitle ?></b>
                            </div>
                            <?= $privacystamp ?>

                            <button class="absolute text-ocean-blue -right-1 -bottom-1 text-xs rounded-full p-0 m-0 Z-2">
                                <i class="fas fa-info-circle" title="Added by <?= $itemgroup['items'][0]['first_name'] ?> <?= $itemgroup['items'][0]['last_name'] ?> on <?= date("d M Y", strtotime($itemgroup['items'][0]['updated'])); ?>"></i>
                            </button>   

                        <?php 
                        //Iterate through all the items for this item group & show them
                        foreach ($reference as $itemname) {
                            if(isset($thisitem[$itemname])) {
                                $item=$thisitem[$itemname];
                                //echo "<pre>"; print_r($item); echo "</pre>";
                        ?>
                            <div <?= $is_group ? "id='item_id_".$item['item_id']."'" : "" ?> class="bg-cream-800 nv-bg-opacity-20 rounded p-0.5 text-left relative">
                                
                                <div class='<?= $is_group ? "w-1/3 float-left pl-1 pr-1": "text-center" ?>'>
                                    <b class="text-xs mb-2"><?= $item['detail_type'] == $groupTitle ? "" : $item['detail_type'] ?>&nbsp;</b>
                                </div>
                                
                                
                                <?php if(!empty($item['file_id'])): //This is a file ?>
                                    <?php if($item['file_type']=='image'): ?>

                                        <div class="<?= $is_group ? "float-left w-2/3" : "mx-auto w-3/4" ?>">
                                            <div class="<?= $is_group ? "relative w-11/12" : "relative" ?> h-auto p-0 m-0 mt-2">
                                                <a href="<?= $item['file_path'] ?>" target="_blank">
                                                    <img class="w-full h-auto rounded" src="<?= $item['file_path'] ?>" alt="<?= $item['detail_value'] ?>"  >
                                                </a>
                                                <p class="absolute <?= $is_group ? "w-full" : "w-full" ?> leading-tight bottom-0 rounded text-xxs text-white bg-gray-800 bg-opacity-40 text-center py-1 p-0" id="file_<?= $item['file_id'] ?>" onDblClick="triggerEditFileDescription('file_<?= $item['file_id'] ?>')" title="Double click to edit this description" ><?= $item['file_description'] ?></p>
                                            </div>
                                        </div>

                                    <?php else: ?>

                                        <div class="float-left w-2/3">
                                            <div class='border rounded text-xs pr-1 pb-1 mx-2 mt-1 bg-cream no-indent <?= $is_group ? "inline" : "" ?>'>
                                                <a href="<?= $item['file_path'] ?>" target="_blank" class="text-blue-600 hover:text-blue-800 z-2" title="Download file">
                                                    <i class="text-md fas fa-file pl-1 pr-0 pb-0"></i>
                                                </a>
                                                <span class="pl-0 text-xxs" id="file_<?= $item['file_id'] ?>" title="Double click to edit this description" onDblClick="triggerEditFileDescription('file_<?= $item['file_id'] ?>')"><?= !empty($item['file_description']) ? $item['file_description'] : 'Attached file'; ?></span>
                                            </div>
                                        </div>
                                        
                                        <?= $is_group ? "<div style='clear: both'></div>" : "" ?>
                                    
                                        <?php endif; ?>
                                <?php else: //This is not a file ?>
                                    <?php if (!empty($item['detail_value'])): ?>
                                        <?php if($item_styles[$itemname] == "individual") : ?>
                                    
                                            <div class="float-left w-2/3">
                                                <a href="index.php?to=family/individual&individual_id=<?= $item['individual_name_id'] ?>" class="text-blue-600 hover:text-blue-800"><?= $item['individual_name'] ?></a>
                                            </div>

                                        <?php elseif($item_styles[$itemname] == "textarea") : ?>
                                    
                                            <div class="float-left w-2/3 overflow-auto overflow-scroll max-h-32 leading-tight">
                                                <span id="item_<?= $item['item_id'] ?>" class="mb-2 text-gray-600 text-xxs " title="Double click to edit this text" onDblClick="triggerEditItemDescription('item_<?= $item['item_id'] ?>')"><?php echo nl2br($web->truncateText($item['detail_value'], 50, 'Read more...', "hiddenStory_".$item['item_id'])); ?></span>
                                            </div>
                                            <div class="hidden" id="hiddenStory_<?= $item['item_id'] ?>"><?= nl2br(htmlspecialchars($item['detail_value'])) ?></div>                                                    

                                        <?php elseif($item_styles[$itemname] == "date" && $item['detail_value'] && preg_match("/\d{4}-\d{2}-\d{2}/", $item['detail_value'])) : ?>
                                    
                                            <div class="float-left w-2/3 overflow-auto overflow-scroll max-h-32 leading-tight">
                                                <span id="item_<?= $item['item_id'] ?>" class="mb-2 text-gray-600 text-xs" title="Double click to edit this date" onDblClick="triggerEditItemDescription('item_<?= $item['item_id'] ?>')"><?php echo date("D, d M Y", strtotime($item['detail_value'])); ?></span>
                                            </div>
                                        <?php else: ?>

                                            <div class="float-left w-2/3">
                                                <span id="item_<?= $item['item_id'] ?>" class="mb-2 text-gray-600 text-xs" title="Double click to edit this text" onDblClick="triggerEditItemDescription('item_<?= $item['item_id'] ?>')"><?php echo nl2br(htmlspecialchars($item['detail_value'])); ?></span>
                                            </div>
                                    
                                        <?php endif; ?>
                                    <?php endif; ?>

                                <?php endif; ?>
                                <?php if($is_group) : ?>

                                    <button data-group-event-name="<?= $groupTitle ?>" data-group-item-type="<?= $item_styles[$itemname] ?>" data-group-id="<?= $item['item_identifier'] ?>" class="absolute text-burnt-orange nv-text-opacity-20 p-0 m-0 -right-0 -top-0 text-xxxxs" title="Delete <?= $itemname ?>" onclick="doAction('delete_item', '<?= $individual['id'] ?>', '<?= $item['item_id'] ?>', event);">
                                        <i class="fas fa-trash"></i>
                                    </button>

                                <?php endif; ?>
                                
                                <div style="clear: both"></div>

                            </div>
                            <?php 
                            } else { 
                                $incompleteItems[]=$itemname;
                            } 
                            ?>
                        <?php 
                        }
                        ?>
                            <div class="h-10"></div>
                        <?php if(count($incompleteItems) > 0) : ?>
                            <div class='flex justify-end items-end absolute right-1 bottom-1 w-full p-0' id='item_buttons_group_<?= $itemgroup['items'][0]['item_identifier'] ?>'>
                            <?php foreach($incompleteItems as $incompleteItem) : ?>
                                <div class="cursor-pointer text-xxs border rounded bg-cream text-brown p-0.5 m-1 relative" data-group-event-name="<?= $groupTitle ?>" data-group-item-type="<?= $item_styles[$incompleteItem] ?>" data-group-id="<?= $itemgroup['items'][0]['item_identifier'] ?>" onclick="doAction('add_sub_item', '<?= $individual['id'] ?>', '<?= $incompleteItem ?>', event);">
                                    <button class="absolute text-burnt-orange nv-text-opacity-50 text-bold rounded-full py-0 px-1 m-0 -right-2 -top-2 text-xxxs" title="Add <?= $incompleteItem ?>" >
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <?= $incompleteItem ?>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>    
</div>



<div class="tab-content" id="relationshipstab">
    <section class="container mx-auto ">
        <div class="grid grid-cols-1 lg:grid-cols-2">


            <!-- Display Spouse(s) -->
            <div class="text-center p-2">
                <h3 class="text-2xl font-bold mt-8 mb-4 relative cursor-help" title="Spouses are significant relationships to the individual - it could be someone they married, or someone they had a child with, or someone that they have a deep personal relationship to. A spouse relationship is not just formed by a marriage, but rather by a significant relationship as determined by the individual themself (which could include a marriage) or as a result of parenting children together.">
                    Spouses
                    <button class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-0 top-0 z-10 font-normal text-sm" title="Add a spouse to <?= $individual['first_name'] ?>" onclick="openModal('add_spouse', '<?= $individual['id'] ?>', '<?= $individual['gender'] ?>');">
                        <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
                    </button>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6 bg-white shadow-lg rounded-lg grid-scrollable-3 place-items-center relative">
                    <?php foreach ($spouses as $spouse): ?>
                        <?= $web->individual_card($spouse, $showrelationshipoption=true, $relationshiptype='spouse') ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Display Children -->
            <div class="text-center p-2">
                <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                    <span title="<?= count($children) ?> children">Children</span>
                    <button class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 right-0 top-0 z-10 font-normal text-sm" title="Add a child to <?= $individual['first_name'] ?>" onclick="openModal('add_child', '<?= $individual['id'] ?>', '<?= $individual['gender'] ?>');">
                        <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
                    </button>
                </h3>
                <?php if (empty($children)): ?>
                    
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6 bg-white shadow-lg rounded-lg grid-scrollable-3 place-items-center relative">
                    <?php foreach ($children as $child): ?>
                        <?= $web->individual_card($child, $showrelationshipoption=true, $relationshiptype='child') ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
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
                        <?= $web->individual_card($parent, $showrelationshipoption=true, $relationshiptype='parent') ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Display Siblings -->
            <div class="text-center p-2">
                <h3 class="text-2xl font-bold mt-8 mb-4 relative">
                    Siblings
                                 
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6 bg-white shadow-lg rounded-lg grid-scrollable-3 place-items-center relative">
                    <?php foreach ($siblings as $sibling): ?>
                        <?= $web->individual_card($sibling, false) ?>
                    <?php endforeach; ?>
                </div>
            </div>        

        </div>
    </section>
</div>



<div class="tab-content" id="mediatab">
    <section class="container mx-auto ">    
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
                            <p id="photo_<?= $photo['id'] ?>" class="mt-2 text-xs text-gray-600" title="Double click to edit this description" onDblClick="triggerEditFileDescription('photo_<?= $photo['id'] ?>')" ><?php echo $photo['file_description']; ?></p>
                        <?php endif; ?>
                            <button class="absolute text-ocean-blue -right-1 -bottom-1 text-xs rounded-full p-0 m-0">
                                <i class="fas fa-info-circle" title="Added by <?= $photo['first_name'] ?> <?= $photo['last_name'] ?> on <?= date("d M Y", strtotime($photo['upload_date'])); ?>"></i>
                            </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="container mx-auto ">
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
