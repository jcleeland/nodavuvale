<div id="Nothing"></div>
<?php
if(isset($_GET['user_id'])) {
    $user=Utils::getUser($_GET['user_id']);
    if($user['individuals_id']) {
        ?>
        <script>
            window.location = '?to=family/individual&individual_id=<?php echo $user['individuals_id'] ?>';
        </script>
        <?php
        exit;
    }
}
?>
<div id="nothing"></div>

<?php
if (!isset($user) || !is_array($user)) {
    $user = Utils::getUser($user_id);
}

if($user_id) { 
    //If there is a $user_id, then the individual we are looking at
    // is a user on the system, not JUST a person in the family tree.
    // - we could be viewing this from either the family/users page,
    //   or from the family/individual page.
?>
    <script type="text/javascript">
        <?php 
        if($user['individuals_id']) { 
        ?>
        //Wait until the document has finished loading:
        document.addEventListener("DOMContentLoaded", function() {
            console.log('Doing the buttons for individual options');
            if(document.getElementById('individual-options')) {
                document.getElementById('individual-options').innerHTML = '<button class="jason flex-1 bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" title="View <?= $user['first_name'] ?> in the family tree" onclick="window.location.href=\'index.php?to=family/tree&zoom=<?= $user['individuals_id'] ?>&root_id=<?php echo $web->getRootId() ?>\'"><i class="fas fa-network-wired" style="transform: rotate(180deg)"></i></button>';
            }
            <?php 
            if($_SESSION['user_id'] == $user_id || $auth->getUserRole() === 'admin') { ?>
            if(document.getElementById('individual-options')) {
                document.getElementById('individual-options').innerHTML += '<button class="flex-1 bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" title="Edit <?= $user['first_name'] ?>&apos;s account" onclick="window.location.href=\'index.php?to=account&user_id=<?= $user['user_id'] ?>\'"><i class="fas fa-users"></i></button>'+document.getElementById('individual-options').innerHTML;
            } 
            <?php } ?>
        });
        <?php } ?>
            
    </script>
<?php 
}

//Get a list of discussions and comments for this user
$sql = "SELECT count(id) as discussioncount
    FROM discussions
    WHERE discussions.user_id = ?
    ORDER BY discussions.created_at DESC";
$discussionscount = $db->fetchOne($sql, [$user_id]);
$discussioncount = $discussionscount['discussioncount'];
$discussioncount = $discussioncount ? $discussioncount : 0;
$discussioncount = $discussioncount == 1 ? "1 discussion" : $discussioncount . " discussions";
$discussioncount = $discussioncount ? $discussioncount : "No discussions";
$sql = "SELECT count(id) as commentcount
    FROM discussion_comments
    WHERE user_id = ?
    ORDER BY created_at DESC";
$comments = $db->fetchOne($sql, [$user_id]);
$commentcount = $comments['commentcount'];
$commentcount = $commentcount ? $commentcount : 0;
$commentcount = $commentcount == 1 ? "1 comment" : $commentcount . " comments";
$commentcount = $commentcount ? $commentcount : "No comments";

$referencename=$_SESSION['user_id'] == $user_id ? "You" : $user['first_name'];
$referencenamepossessive=$_SESSION['user_id'] == $user_id ? "Your" : $user['first_name'] . "'s";
$referencenamepastposessive=$_SESSION['user_id'] == $user_id ? "You have" : $user['first_name'] . " has";

$dashboardLayout = isset($dashboardLayout) ? $dashboardLayout : 'legacy';
$dashboardIsDropdown = $dashboardLayout === 'dropdown';
$panelWrapperBaseClass = $dashboardIsDropdown ? 'dashboard-dropdown-panel' : 'pt-10';
$panelCardBaseClass = $dashboardIsDropdown ? 'dashboard-dropdown-card' : 'p-4 pt-6 bg-white shadow-lg rounded-lg mt-8 h-128 overflow-y-auto';

?>

<?php if (!$dashboardIsDropdown): ?>
<section class="container mx-auto px-4 sm:px-3 xs:px-2 lg:px-8 pt-1 pb-12">
<?php endif; ?>
<?php 
        if($user_id == $_SESSION['user_id']  || $auth->getUserRole() === 'admin') {
            if($auth->getUserRole() === 'admin' && $user_id != $_SESSION['user_id']) {
                ?>
                <center class="mt-2"><button onClick='toggleUserInfo()'><i class='fas fa-eye'></i> Toggle User View</button></center>
                <script type='text/javascript'>
                    function toggleUserInfo() {
                        document.getElementById('userDetails').classList.toggle('hidden');
                        document.getElementById('userNotifications').classList.toggle('hidden');
                    }
                </script>  
                <?php
            }
            if(!$dashboardIsDropdown) {
                ?>
                <div class="absolute z-10 p-2 w-max">
                    <div class="flex justify-full items-top">
                        <div>
                            <img 
                                class="mt-6 sm:mt-0 h-20 w-20 sm:h-36 sm:w-36 avatar-img-0 avatar-float-left ml-1 mr-4 <?php echo $auth->getUserPresence($user_id) ? 'userpresent' : 'userabsent'; ?> rounded-full object-cover" 
                                src="<?php echo $user['avatar'] ? $user['avatar'] : 'images/default_avatar.webp'; ?>" 
                                alt="<?php echo $user['first_name'] . ' ' . $user['last_name'] ?>">
                        </div>
                    </div>
                </div>
                <?php
            }
    ?>

    <!-- Notifications -->
    <?php
    if($user_id == $_SESSION['user_id']  || $auth->getUserRole() === 'admin') {
            //Get any new comments to discussions for this user

            //See if the users' last login date is more than one month ago
            $now = time();
            $lastlogin=null;
            if($user['last_login']) {
                $lastlogin = strtotime($user['last_login']);
                $lastlogin = strtotime($user['last_login']);
                
                $newsincedate = date('Y-m-d H:i:s', strtotime('-1 month', $now));
                if($lastlogin < strtotime($newsincedate)) {
                    $newsincedate = $user['last_login'];
                }
            } else {
                $newsincedate = date('Y-m-d H:i:s', strtotime('-1 month', $now));
            }

            $mynewdiscussioncomments = Utils::getNewCommentsToDiscussionsIveStarted($user_id, $newsincedate);
            $mynewcommentreplies = Utils::getNewCommentsToDiscussionsIveCommentedOn($user_id, $newsincedate);
            $mynewreactions = Utils::getNewReactionsToDiscussionsAndComments($user_id, $newsincedate); 

            $hideuser="";
            if($auth->getUserRole() === 'admin' && $user_id != $_SESSION['user_id']) {
                $hideuser="hidden";
            }
            $notificationWrapperClass = trim($panelWrapperBaseClass . ' ' . $hideuser);
            $notificationCardClass = $panelCardBaseClass;
            if(isset($dashboardDropdownMeta) && is_array($dashboardDropdownMeta)) {
                $dashboardDropdownMeta['notifications'] = count($mynewdiscussioncomments) + count($mynewcommentreplies) + count($mynewreactions);
            }

    ?>
    <div id="userNotifications" class="<?= $notificationWrapperClass ?>" data-panel="notifications">
        <div class="<?= $notificationCardClass ?>">
            <div class="pb-6">
                <div class="relative flex justify-center items-center text-center min-h-4">
                    <div class="flex-grow" style="z-index: 1000">
                        <h3 class="text-2xl whitespace-nowrap font-bold p-1 rounded" style="z-index: 1000" >
                            <span class="text-ocean-blue bg-white-800 nv-bg-opacity-50"><?= $referencenamepossessive ?> Notifications</span>
                        </h3>
                        <i>Since <?= date('F j, Y', strtotime($newsincedate)) ?></i><!--&nbsp;<i class="fas fa-clock text-brown-500 cursor-pointer"></i>-->
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap justify-around items-center w-full">
            <?php
            if(count($mynewdiscussioncomments) > 0 || count($mynewcommentreplies) > 0 || count($mynewreactions) > 0) {
            ?>
                    <div class="w-full text-center pt-12 font-bold">
                        <span class="border-t-2 pt-2 pb-4">
                        People are talking with you
                        </span>
                    </div>
                    <div class="flex flex-wrap justify-between items-start text-sm lg:text-md py-2 mx-1">


                    <?php if(count($mynewdiscussioncomments) > 0) { ?>
                        <div class="w-full sm:w-1/3 min-w-20 p-2">
                            <div 
                                class="flex ml-1 mr-1 mb-1 border p-2 w-full min-w-20 rounded-full h-22 overflow-hidden hover:bg-ocean-blue-800 hover:nv-bg-opacity-10 cursor-pointer"
                                onClick="document.getElementById('discussionreplies').classList.toggle('hidden'); this.classList.toggle('bg-ocean-blue-800'); this.classList.toggle('nv-bg-opacity-10'); this.classList.toggle('z-1');"
                                title="View new replies and comments on your chat posts"
                            >
                                <button 
                                    class="bg-ocean-blue-800 nv-bg-opacity-50 text-white rounded-full h-12 py-2 text-xl px-6 my-4 mx-1" 
                                >
                                    <i class="fas fa-comments"></i>
                                </button>
                                <p class="text-gray-600 ml-3 h-22 overflow-y-scroll">
                                    <b>Replies to your discussions</b><br />
                                    <?= count($mynewdiscussioncomments) ?> new comments
                                </p>
                            </div>
                            <!-- Hidden div for listing discussions & comments -->
                            <div id="discussionreplies" class="hidden p-2">
                                <div class="items-center text-xs sm:text-sm md:text-sm lg:text-md py-2 mx-1 -mt-5">
                                    <?php if(count($mynewdiscussioncomments) > 0) { ?>
                                        <?php foreach($mynewdiscussioncomments as $discomment) { ?>
                                            <p 
                                                class="min-w-20 cursor-pointer text-center p-2 m-1 hover:bg-ocean-blue-800 hover:nv-bg-opacity-10"
                                                onClick="window.location.href='index.php?to=communications/discussions&filter=discussions&discussion_id=<?= $discomment['discussion_id'] ?>&comment_id=<?= $discomment['id'] ?>'"
                                            >
                                                <?= $web->timeSince($discomment['created_at']) ?> <b><?= $discomment['first_name'] ?></b> commented 
                                                "<?= stripslashes($web->truncateText($discomment['comment'], 50)) ?>"
                                                on your discussion <b><i><?= stripslashes($discomment['title']) ?></i></b>
                                            </p>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>  
                        </div>                                         
                    <?php } ?>


                    <?php if(count($mynewcommentreplies) > 0) { ?>
                        <div class="w-full sm:w-1/3 min-w-20 p-2">
                            <div 
                                class="flex ml-0 mr-2 mb-1 border p-2 w-full min-w-20 rounded-full h-22 overflow-hidden hover:bg-burnt-orange-800 hover:nv-bg-opacity-10 cursor-pointer"
                                onClick="document.getElementById('commentreplies').classList.toggle('hidden'); this.classList.toggle('bg-burnt-orange-800'); this.classList.toggle('nv-bg-opacity-10'); this.classList.toggle('z-1');"
                                title="View replies and comments on other people's discussions that you have commented on"
                            >
                                <button 
                                    class="bg-burnt-orange-800 nv-bg-opacity-50 text-white rounded-full h-12 py-2 text-xl px-6 my-4 mx-1" 
                                >
                                    <i class="fas fa-comment"></i>
                                </button>
                                <p class="text-gray-600 ml-3 h-22 overflow-y-scroll">
                                    <b>Replies to your comments</b><br />
                                    <?= count($mynewcommentreplies) ?> new replies
                                </p>
                            </div>
                            <!-- Hidden div for listing comments -->
                            <div id="commentreplies" class="hidden p-2">
                                <div class="items-center text-xs sm:text-sm md:text-sm lg:text-md py-2 mx-1 -mt-5">
                                    <?php if(count($mynewcommentreplies) > 0) { ?>
                                        <?php foreach($mynewcommentreplies as $commentreply) { ?>
                                            <p
                                                class="cursor-pointer text-center p-2 m-1 hover:bg-burnt-orange-800 hover:nv-bg-opacity-10"
                                                onClick="window.location.href='index.php?to=communications/discussions&filter=comments&discussion_id=<?= $commentreply['discussion_id'] ?>&comment_id=<?= $commentreply['id'] ?>'"
                                            >
                                                <?= $web->timeSince($commentreply['created_at']) ?> <b><?= $commentreply['first_name'] ?></b> replied 
                                                "<?= stripslashes($web->truncateText($commentreply['comment'], 50)) ?>"
                                                to your comment on the discussion <b><i><?= stripslashes($commentreply['title']) ?></i></b>
                                            </p>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>                            
                        </div>
                    <?php } ?>


                    <?php if(count($mynewreactions) > 0) { ?>
                        <div class="w-full sm:w-1/3 min-w-20 p-2">
                            <div 
                                class="flex ml-0 mr-2 mb-1 border p-2 w-full min-w-20 rounded-full h-22 overflow-hidden hover:bg-deep-green-800 hover:nv-bg-opacity-10 cursor-pointer"
                                onClick="document.getElementById('reactionreplies').classList.toggle('hidden'); this.classList.toggle('bg-deep-green-800'); this.classList.toggle('nv-bg-opacity-10'); this.classList.toggle('z-1');"
                                title="View new reactions to your discussions and comments"
                            >
                                <button 
                                    class="bg-deep-green-800 nv-bg-opacity-50 text-white rounded-full h-12 py-2 text-xl px-6 my-4 mx-1" 
                                >
                                    <i class="fas fa-thumbs-up"></i>
                                </button>
                                <p class="text-gray-600 ml-3 h-22 overflow-y-scroll">
                                    <b>Reactions to your posts</b><br />
                                    <?= count($mynewreactions) ?> new reactions
                                </p>
                            </div>
                            <!-- Hidden div for listing reactions -->
                            <div id="reactionreplies" class="hidden p-2">
                                <div class="items-center text-xs sm:text-sm md:text-sm lg:text-md py-2 mx-1 -mt-5">
                                    <?php if(count($mynewreactions) > 0) { ?>
                                        <?php foreach($mynewreactions as $reaction) { ?>
                                            <?php 
                                            //Work out if this is a reaction to a discussion or a comment
                                            $reactiontype="discussion";
                                            if(isset($reaction['comment_id'])) {
                                                $reactiontype="comment";
                                            }
                                            //Build a clickable link to the discussion or comment
                                            $reactionlink="index.php?to=communications/discussions&filter=reactions&discussion_id=".$reaction['discussion_id'];
                                            if(isset($reaction['comment_id'])) {
                                                $reactionlink.="&comment_id=".$reaction['comment_id'];
                                            }
                                            //Set an icon to reflect the type of reaction
                                            $emoticons=$web->getReactionEmoticons();
                                            $reactionicon="<button class='reaction-btn'>".$emoticons[$reaction['reaction_type']]."</button>";
                                            
                                            ?>
                                            <p
                                                class="cursor-pointer text-center p-2 m-1 hover:bg-deep-green-800 hover:nv-bg-opacity-10"
                                                onClick="window.location.href='<?= $reactionlink ?>'"
                                            >
                                                <?= $web->timeSince($reaction['reacted_at']) ?> <b><?= $reaction['first_name'] ?></b> reacted 
                                                <?= $reactionicon ?> to
                                                your <?= $reactiontype ?> on the discussion <b><i><?= stripslashes($reaction['title']) ?></i></b>
                                            </p>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>                            
                        </div>
                    <?php } ?>
                    </div>

            <?php } else { ?>
            <div class="w-full text-center pt-12 font-bold mb-4">
                <span class="border-t-2 border-b-2 pt-2 pb-4">
                No new notifications
                </span>
            </div>
            <?php } ?>
            </div>
        </div>
    </div>
    <?php
    }
    ?>


    <!-- User's About Page (for other members to see) -->
    <?php
        $profileWrapperClass = $panelWrapperBaseClass;
        $profileCardClass = $panelCardBaseClass;
    ?>
    <div id="publicUserDetails" class="<?= $profileWrapperClass ?>" data-panel="profile">
        <div class="<?= $profileCardClass ?>">
            <div class="pb-6">
                <div class="relative flex justify-center items-center text-center min-h-4">
                    <div class="flex-grow" style="z-index: 1000">
                        <h3 class="text-2xl whitespace-nowrap font-bold p-1 rounded" style="z-index: 1000" >
                            <span class="text-ocean-blue bg-white-800 nv-bg-opacity-50">About <?= $referencename ?></span>
                        </h3>
                        <?php
                        if($user_id == $_SESSION['user_id']) {
                        ?>
                        <i>This section is visible to other members</i>
                        <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
            <div>
                <p class="text-gray-600 mb-6 text-center">
                <?php if($user['registration_date']) : ?>
                    <?= $referencenamepastposessive ?> been registered with <i><?= $site_name ?></i> since <?= date('F j, Y', strtotime($user['registration_date'])) ?>
                <?php endif; ?>
                <?php if($user['last_login']) : ?>
                    and last visited the site at <?= date('H:i a F j, Y', strtotime($user['last_login'])) ?>
                <?php endif; ?>
                </p>
            </div>


            <div class="flex flex-wrap justify-around items-center w-full">
                <?php if($user['individuals_id']) { ?>
                    <div class="text-gray-600 min-w-10 mb-6 border rounded-full p-3 cursor-pointer hover:bg-burnt-orange-800 hover:nv-bg-opacity-10" onClick="window.location.href='index.php?to=family/tree&zoom=<?= $user['individuals_id'] ?>&root_id=1'">
                        <i class="fas fa-network-wired text-burnt-orange text-2xl"></i> <?= $referencename ?> in the Tree
                    </div>
                <?php } ?>
                <div class="text-gray-600 min-w-10 mb-6 border rounded-full text-md p-3 cursor-pointer hover:bg-ocean-blue-800 hover:nv-bg-opacity-10" onClick="window.location.href='index.php?to=communications/discussions&filter=discussions&user_id=<?= $user['user_id'] ?>'">
                    <i class="fas fa-comments text-ocean-blue text-2xl"></i> <?= $discussioncount ?>
                </div>
                <div class="text-gray-600 min-w-10 mb-6 border rounded-full p-3 cursor-pointer hover:bg-deep-green-800 hover:nv-bg-opacity-10" onClick="window.location.href='index.php?to=communications/discussions&filter=comments&user_id=<?= $user['user_id'] ?>'">
                    <i class="fas fa-comment text-deep-green text-2xl"></i> <?= $commentcount ?>
                </div>
                <div class="text-gray-600 min-w-10 mb-6 border rounded-full p-3 cursor-pointer hover:bg-warm-red-800 hover:nv-bg-opacity-10">
                    <i class="fas fa-envelope text-warm-red text-2xl"></i> Message <?= $user['first_name'] ?>
                </div>
            </div>


            <?php if($_SESSION['user_id'] == $user_id || $auth->getUserRole() === 'admin') { 
                $editclass='cursor-pointer';
                $titlesuffix=" (Double click to change your settings)";
            } else {
                $editclass='';
                $titlesuffix="";
            }
            ?>

            <?php if ($user['about']) { ?>
                <div 
                    class="items-center text-center w-full h-44 mb-6 border rounded-xl <?= $editclass ?> hover:bg-deep-green-800 hover:nv-bg-opacity-10"
                    title="About <?= $user['first_name'] ?> <?= $titlesuffix ?>"
                    data-field-value="id-users_about_me" 
                    <?php if($_SESSION['user_id'] == $user_id || $auth->getUserRole() === 'admin') { ?>
                        onDblClick="editUserField('about', 'About <?= $user['first_name'] ?>', '<?= $user['id'] ?>', event)"
                    <?php } ?>
                    >
                        <div class="top-0 left-0 w-full bg-deep-green-800 nv-bg-opacity-50 text-white text-xs text-center rounded-t-full">About <?= $referencename ?></div>
                        <div class="text-gray-600 h-40 p-3 overflow-y-scroll " id="id-users_about_me"><?php echo nl2br($user['about']); ?></div>
                </div>
            <?php } ?>




            <div class="flex flex-wrap justify-between items-start w-full my-4">
            <?php if ($user['location']) { ?>
                <div 
                    class="text-gray-600 w-1/3 min-w-10 mb-6 border rounded-xl overflow-hidden <?= $editclass ?> hover:bg-ocean-blue-800 hover:nv-bg-opacity-10"
                    title="<?= $user['first_name'] ?> lives at <?= $titlesuffix ?>"
                    data-field-value="<?= htmlspecialchars($user['location']) ?>"
                    <?php if($_SESSION['user_id'] == $user_id || $auth->getUserRole() === 'admin') { ?>
                        onDblClick="editUserField('location', 'Where <?= $user['first_name'] ?> lives', '<?= $user['id'] ?>', event)"  
                    <?php } ?>
                >
                    <div class="top-0 left-0 w-full bg-ocean-blue-800 nv-bg-opacity-50 text-white text-xs text-center rounded-t-full"><?= $referencenamepossessive ?> Location</div>
                    <div class="mx-4 mt-2 mb-2">
                        <i class="fas fa-map text-ocean-blue text-2xl"></i> <?php echo htmlspecialchars($user['location']); ?>
                    </div>
                </div>
            <?php } ?>
            <?php if ($user['skills']) { ?>
                <div 
                    class="text-gray-600 w-1/3 min-w-20 mb-6 border rounded-xl overflow-hidden text-sm <?= $editclass ?> hover:bg-warm-red-800 hover:nv-bg-opacity-10 max-h-20 w-1/3 overflow-y-scroll"
                    title="Skills <?= $user['first_name'] ?> has<?= $titlesuffix ?>"
                    data-field-value="<?= htmlspecialchars($user['skills']) ?>"
                    <?php if($_SESSION['user_id'] == $user_id || $auth->getUserRole() === 'admin') { ?>
                        onDblClick="editUserField('skills', 'Skills <?= $user['first_name'] ?> has', '<?= $user['id'] ?>', event)"
                    <?php } ?>
                >
                    <div class="w-full bg-warm-red-800 nv-bg-opacity-50 text-white text-xs text-center rounded-t-full"><?= $referencenamepossessive ?> Skills</div>
                    <div class="mx-4 mt-2 mb-2">
                        <i class="fas fa-tools text-warm-red text-2xl float-left mr-1"></i>  <?= $user['skills'] ?>
                    </div>
                </div>
            <?php } 
            if($user['languages_spoken'] != "") {
                $languages=json_decode($user['languages_spoken']);
                //Convert this object into an array 
                $languages=(array)$languages;
                //Now convert into a comma separated string
                $languages=implode(", ", $languages);
                if ($user['languages_spoken']) { 
                    ?>
                    <div 
                        class="text-gray-600 w-1/3 min-w-10 mb-6 border rounded-xl overflow-hidden <?= $editclass ?> hover:bg-burnt-orange-800 hover:nv-bg-opacity-10" 
                        title="Languages <?= htmlspecialchars($user['first_name']) ?> speaks<?= $titlesuffix ?>"
                        data-field-value="<?= $languages ?>"
                        <?php if($_SESSION['user_id'] == $user_id || $auth->getUserRole() === 'admin') { ?>
                            onDblClick="editUserField('languages_spoken', 'Languages spoken by <?= htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') ?>', '<?= $user['id'] ?>', event)"
                        <?php } ?>
                    >
                        <div class="w-full bg-burnt-orange-800 nv-bg-opacity-50 text-white text-xs text-center rounded-t-full"><?= $referencenamepossessive ?> Languages</div>
                        <div class="mx-4 mt-2 mb-2">
                            <i class="fas fa-language text-burnt-orange text-2xl"></i> <?php
                                $languages=json_decode($user['languages_spoken']);
                                foreach($languages as $language) {
                                    if($language) {
                                        echo "<span class='p-1 bg-burnt-orange text-white rounded-md m-1'>".trim($language)."</span>";
                                    }
                                }
                            ?>
                        </div>
                    </div>
            <?php 
                } 
            }    
            ?>            
            </div>
        </div>
    </div>    



    <!-- User's Control Page -->
    <?php
        $controlsHideClass = isset($hideuser) ? $hideuser : '';
        $controlsWrapperClass = trim($panelWrapperBaseClass . ' ' . $controlsHideClass);
        $controlsCardClass = $panelCardBaseClass;
    ?>
    <div id="userDetails" class="<?= $controlsWrapperClass ?>" data-panel="controls">
        <div class="<?= $controlsCardClass ?>">

        <?php
            if($auth->getUserRole() === 'admin' && $user_id != $_SESSION['user_id']) {
                //These options will display a users personal page - either if they 
                // are logged in as this user themself, or if they are an admin
        ?>
            <div class="pb-6">
                <div class="relative -mt-4 -ml-4 -mr-4 -mb-4 flex justify-center items-center text-center border-t-2 bg-gradient-to-b from-gray-200 to-white">
                    <div class='flex-grow text-sm italic -mb-2' style='z-index: 1000'>
                        This is the user's personal page, only visible to the user (and you because you're an admin).
                    </div>
                </div>
            </div>
            <?php 
            } 
            ?>            
            <!-- User's Personal Page (only visible to the user) -->
            <div class="pb-6"> 
                <div class="relative flex justify-center items-center text-center">
                    <div class='w-1'></div>
                    <div class="flex-grow" style="z-index: 1000">
                        <h3 class="text-2xl whitespace-nowrap font-bold p-1 rounded" style="z-index: 1000" >
                            <span class="text-ocean-blue bg-white-800 nv-bg-opacity-50">Your Controls</span>
                        </h3>
                    </div>
                    <div class='w-1'></div>
                </div>  
                <div id="tasks" class="mt-6">
                    <!-- Family tree & account information -->                          
            <?php
            if($user['individuals_id']) {
                $missinginfo=Utils::getMissingDataForUser($user['individuals_id']);
                if(isset($dashboardDropdownMeta) && is_array($dashboardDropdownMeta)) {
                    $dashboardDropdownMeta['controls'] = is_array($missinginfo) ? count($missinginfo) : 0;
                }
                //echo "<pre>ALl the info"; print_r($missinginfo); echo "</pre>";
                //Remove all the items that have no missing data
                
                if($missinginfo) {
                    //select a random missing info group
                    $missingitem = $missinginfo[array_rand($missinginfo)];
                    //echo "<pre>Selected Item: "; print_r($missingitem); echo "</pre>";
                    //Select a random person from the group
                    $missingpersongroup = $missingitem[array_rand($missingitem)];
                    //echo "<pre>Selected person group"; print_r($missingpersongroup); echo "</pre>";
                    if($missingperson=$missingpersongroup[array_rand($missingpersongroup)]) {
                        
                        $missingperson = $missingpersongroup[array_rand($missingpersongroup)];
                        
                        //echo "<pre>Selected person"; print_r($missingperson); echo "</pre>";

                        $missingdataoption="";
                        if(!empty($missingperson['missingcoredata'])) {
                            //Select a random item from $missingperson['missingcoredata']
                            $missingdataoption = $missingperson['missingcoredata'][array_rand($missingperson['missingcoredata'])];

                        } elseif(!empty($missingperson['missingitems'])) {
                            //Select a random item from $missingperson['missingitems']
                            $missingdataoption = $missingperson['missingitems'][array_rand($missingperson['missingitems'])];

                        }
                        if(empty($missingperson['details'])) {
                            //echo "<pre>"; print_r($missingpersongroup); echo "</pre>";
                        }
                        $missingpersontitle="";
                        $missingpersonmessage="";
                        if($missingperson['details']['relationshiplabel']=="Self") {
                            $missingpersontitle="yourself";
                        } else {
                            $missingpersontitle="your ".$missingperson['details']['relationshiplabel'];
                        }
                        $missingpersonmessage="Can you help us with information about ".explode(" ",$missingperson['details']['first_names'])[0]."'s ";
                        
                        $missingpersonmessage.=strtolower(str_replace("_", " ",$missingdataoption))."?";
                        
                        $keyimage=Utils::getKeyImage($missingperson['details']['individual_id']);
                        
                        $helpwiththis = '<div class="flex mx-2 border p-2 w-1/3 min-w-20 rounded-full h-22 overflow-hidden hover:bg-brown-800 hover:nv-bg-opacity-10 cursor-pointer "';
                        $helpwiththis .= ' onclick="window.location.href=\'?to=family/individual&individual_id='.$missingperson['details']['individual_id'].'\'"';
                        $helpwiththis .= '>';
                        $helpwiththis .= '<button class="bg-ocean-blue-800 nv-bg-opacity-50 text-white text-center rounded-full h-16 w-12 text-xl my-4 mx-1 py-2 px-5" title="Help us with this thing" ';
                        $helpwiththis .= '>';
                        $helpwiththis .= '<img src="'.$keyimage.'" class="rounded-full text-xl object-cover m-auto px-2 py-1" title="'. $missingperson['details']['first_names'] .' '. $missingperson['details']['last_name'] .'">';
                        $helpwiththis .= '</button>';
                        $helpwiththis .= '<p class="text-gray-600 ml-3 h-3/5 overflow-y-scroll"><b>';
                        $helpwiththis .= 'What about ';
                        $helpwiththis .= $missingpersontitle;
                        $helpwiththis .= '</b><br />';
                        $helpwiththis .= $missingpersonmessage;
                        $helpwiththis .= '</p>';
                        $helpwiththis .= '</div>';
                    }
                }
            }
            ?>

                    <div class="w-full text-center pt-12 font-bold">
                        <span class="border-t-2 pt-2 pb-4">
                        <span class="border-t-2 pt-2">
                        Adjust your account settings
                        </span>
                    </div>
                    <div class="flex flex-wrap justify-around items-center text-xs sm:text-sm md:text-sm lg:text-md py-2 mx-1">
                        <?php if(!$user['individuals_id']) { ?>
                        <div class="flex mx-2 mb-2 border p-2 w-1/3 min-w-20 rounded-full h-22 overflow-hidden hover:bg-ocean-blue-800 hover:nv-bg-opacity-10 cursor-pointer onclick="window.location.href='index.php?to=family/individual&individual_id=<?php echo $user['individuals_id'] ?>'">
                            <button class="bg-ocean-blue-800 nv-bg-opacity-50 text-white rounded-full h-11 py-2 text-xl px-5 my-4 mx-1" title="Connect to our tree" onclick="window.location.href='index.php?to=family/individual&individual_id=<?php echo $user['individuals_id'] ?>'"><i class="fas fa-user-friends"></i></button>
                            <p class="text-gray-600 ml-3 h-22 overflow-y-scroll">
                                <b>Link Yourself In</b><br />
                                Your account is not yet linked to a person in our family tree. Connect yourself to your entry in our tree so we all know where you fit into the Diaspora, and so you can take "ownership" of your information.
                            </p>
                        </div>
                        <?php } ?>
                        <?php if($user['avatar'] == 'images/default_avatar.webp') { ?>
                        <div class="flex mx-2 mb-2 border p-2 w-1/3 min-w-20 rounded-full h-22 overflow-hidden hover:bg-burnt-orange-800 hover:nv-bg-opacity-10 cursor-pointer " onclick="triggerKeyPhotoUpload();">
                            <button class="bg-burnt-orange-800 nv-bg-opacity-50 text-white rounded-full h-12 py-2 text-xl px-6 my-4 mx-1" title="Add a profile picture" onclick="triggerKeyPhotoUpload()">
                                <i class="fas fa-portrait"></i>
                            </button>
                            <p class="text-gray-600 ml-3 h-22 overflow-y-scroll">
                                <b>Add a Profile Picture</b><br />You don't have a profile picture yet. Add one so we can see you!
                            </p>
                        </div>
                        <?php } ?>
                        <div class="flex mx-2 mb-2 border p-2 w-1/3 min-w-20 rounded-full h-22 overflow-hidden hover:bg-deep-green-800 hover:nv-bg-opacity-10 cursor-pointer " onclick="window.location.href='index.php?to=account&user_id=<?php echo $user['user_id'] ?>'">
                            <button class="bg-deep-green-800 nv-bg-opacity-50 text-white rounded-full h-12 py-2 text-xl px-6 my-4 mx-1" title="Edit your account">
                                <i class="fas fa-user"></i>
                            </button>
                            <p class="text-gray-600 ml-3 h-22 overflow-y-scroll">
                                <b>Edit your account</b><br />
                                Adjust your account settings, set your privacy levels and more..
                            </p>
                        </div>
                    </div>

                    <div class="w-full text-center pt-12 font-bold">
                        <span class="border-t-2 pt-2 pb-4">
                        Help us with missing information
                        </span>
                    </div>
                    <div class="flex flex-wrap justify-around items-center text-xs sm:text-sm md:text-sm lg:text-md py-2 mx-1">
                        <?php if (isset($helpwiththis)) {
                            echo $helpwiththis;
                        } ?>
                        <div class="flex mx-2 mb-2 border p-2 w-1/3 min-w-20 rounded-full h-22 overflow-hidden hover:bg-brown-800 hover:nv-bg-opacity-10 cursor-pointer ">
                            <button class="bg-brown-800 nv-bg-opacity-50 text-white rounded-full h-12 py-2 text-xl px-5 my-4 mx-1" title="View your family tree" onclick="window.location.href='index.php?to=family/tree&zoom=<?php echo $user['individuals_id'] ?>&root_id=<?php echo $web->getRootId() ?>'">
                                <i class="fas fa-network-wired" style="transform: rotate(180deg)"></i>
                            </button>
                            <p class="text-gray-600 ml-3 h-16 overflow-y-scroll">
                                <b>Help fill out the tree</b><br />
                                This is a site for collaboration and sharing - so why not find an ancestor you know, or know about, and tell us some stories?
                            </p>
                        </div>
                    </div>

            <?php
            //Build an array of divs to show missing user data
            $userdata=array(
                'about'=>array('description'=>'A little bit about yourself for others to know', 'icon'=>'fas fa-info-circle', 'color'=>'bg-ocean-blue-800'),    
                'location'=>array('description'=>'Where are you living currently?', 'icon'=>'fas fa-map-marker-alt', 'color'=>'bg-burnt-orange-800'),
                'skills'=>array('description'=>'What are you good at?', 'icon'=>'fas fa-tools', 'color'=>'bg-deep-green-800'),
                'languages_spoken'=>array('description'=>'What languages do you speak?', 'icon'=>'fas fa-language', 'color'=>'bg-warm-red-800'),
            );
            $userinfos=[];
            foreach($userdata as $dataname=>$details) {
                if($dataname == 'languages_spoken') {
                    if($user[$dataname] == "") {
                        $user[$dataname]="[]";
                    }
                    $user[$dataname]=json_decode($user[$dataname]);
                    //remove any empty array keys
                    $user[$dataname]=array_filter($user[$dataname]);
                    //Convert back to json
                    $user[$dataname]=json_encode($user[$dataname]);
                    //If the user has no languages spoken, then $user[$dataname] will be an empty array
                    // in which case, we want to show this as a missing item
                    if($user[$dataname] == "[]") {
                        $user[$dataname]=null;
                    }
                }
                if(empty($user[$dataname])) {
                    $userinfos[]=[
                        "fieldname"=>$dataname,
                        "description"=>$details['description'],
                        "icon"=>$details['icon'],
                        "color"=>$details['color']
                    ];
                }
            }
            if(isset($dashboardDropdownMeta) && is_array($dashboardDropdownMeta)) {
                $dashboardDropdownMeta['profile'] = count($userinfos);
            }
            ?>                    

                    <!-- User information -->
                    <div class="w-full text-center pt-12 font-bold">
                        <span class="border-t-2 pt-2 pb-4">
                        Help us get to know you better
                        </span>
                    </div>
                    <div class="flex flex-wrap justify-around items-center text-xs sm:text-sm md:text-sm lg:text-md py-2 mx-1">
                    

                    <?php if(count($userinfos) > 0) :
                        //Randomise the order of $userinfos
                        shuffle($userinfos);
                        //Display the first 3 items in $userinfos
                        $userinfos=array_slice($userinfos, 0, 4);
                        foreach($userinfos as $mui) : ?>
                            <div 
                                class="flex mx-2 mb-2 border p-2 w-1/3 min-w-20 rounded-full h-22 overflow-hidden hover:bg-ocean-blue-800 hover:nv-bg-opacity-10 cursor-pointer "
                                onclick="editUserField('<?php echo $mui['fieldname']; ?>', '<?php echo addslashes($mui['description']); ?>', '<?php echo $user['id']; ?>');"
                                >
                                <button class="<?= $mui['color'] ?> nv-bg-opacity-50 text-white rounded-full h-12 py-2 text-xl px-6 my-4 mx-1" >
                                    <i class="<?= $mui['icon'] ?>"></i>
                                </button>
                                <p class="text-gray-600 ml-3 h-22 overflow-y-scroll">
                                    <b><?= ucfirst(str_replace("_", " ", $mui['fieldname'])) ?></b><br />
                                    <?= $mui['description'] ?>.
                                </p>
                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        </div>

        <?php 
        } 
        ?>
    </div>

<?php if (!$dashboardIsDropdown): ?>
</section>
<?php endif; ?>
