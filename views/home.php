<?php

// Check if the user is logged in
$is_logged_in = isset($_SESSION['user_id']);
// Set the default "view new" as being the last login time
$viewnewsince=isset($_SESSION['last_login']) ? date("Y-m-d H:i:s", strtotime('-1 day', strtotime($_SESSION['last_login']))) : date("Y-m-d H:i:s", strtotime('1 week ago'));
if(isset($_GET['changessince']) && $_GET['changessince'] != "lastlogin") {
    $viewnewsince=$_GET['changessince'];
}   

?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold">Welcome to <i><?= $site_name ?></i></h2>
        <p class="mt-4 text-lg">Connecting our family and preserving our cultural heritage.</p>
        <?php if (!$is_logged_in): ?>
            <!-- Button-styled anchor tag -->
            <a href="?to=about/aboutvirtualnataleira" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
                Our website
            </a>
            <a href="?to=about/aboutnataleira" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
                Our village
            </a>
        <?php else: ?>
            <a href="?to=family/tree" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
                Browse The Tree
            </a>
            <a href="?to=communications/discussions" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
                Chat with Family
            </a>
        <?php endif; ?>

    </div>
</section>

<!-- Conditional Content Section Based on Login Status -->
<?php if ($is_logged_in): ?>
<?php
    $changes=Utils::getNewStuff($user_id, $viewnewsince, "items.updated ASC");
    $summary=array();
    $summary['Discussions']=count($changes['discussions'])>0 ? count($changes['discussions'])." new discussions" : "";
    $summary['Individuals']=count($changes['individuals'])>0 ? count($changes['individuals'])." new individuals" : "";
    $summary['Relationships']=count($changes['relationships'])>0 ? count($changes['relationships'])." new relationships" : "";
    $summary['Items']=count($changes['items'])>0 ? count($changes['items'])." new items" : "";
    $summary['Files']=count($changes['files'])>0 ? count($changes['files'])." new files" : "";

    $item_types = Utils::getItemTypes();
    $item_styles= Utils::getItemStyles();
     //echo "<pre>"; print_r($changes['items']); echo "</pre>";
?>

    <!-- Show the users descendancy line -->
    <?php 
    $sql = "SELECT users.id, users.id as user_id, users.first_name, users.last_name, users.email, 
    users.avatar, users.individuals_id, users.show_presence
    FROM users
    WHERE users.id = ?";

    $user = $db->fetchOne($sql, [$user_id]);
    $user['avatar'] = $user['avatar'] ? $user['avatar'] : "images/default_avatar.webp";    

    // Fetch the line of descendancy
    if($user['individuals_id']) {
        $descendancy=Utils::getLineOfDescendancy(Web::getRootId(), $user['individuals_id']);
    }
    ?>
    <?php if(isset($descendancy) && $descendancy): ?>
        <section class="container mx-auto pt-6 pb-2 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap justify-center items-center text-xxs sm:text-sm">
                <?php foreach($descendancy as $index => $descendant): ?>
                    <div class="bg-burnt-orange-800 nv-bg-opacity-20 text-center p-1 sm:p-2 my-1 sm:my-2 rounded-lg">
                        <a href='?to=family/individual&individual_id=<?= $descendant[1] ?>'><?= $descendant[0] ?></a>
                    </div>
                    <?php if ($index < count($descendancy) - 1): ?>
                        <i class="fas fa-arrow-right mx-2"></i> <!-- FontAwesome arrow icon -->
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>    

    <!-- show the users home page -->
    <?php
    include("family/helpers/user.php");
    ?>



    <!-- Changes and Updates Section -->
    <section class="container mx-auto py-12 px-4 sm:px-3 xs:px-2 lg:px-8 pt-10">
        <h3 class="text-2xl font-bold">
            <i class="fas fa-bell" title="Changes and updates since <?= date("l, d F Y", strtotime($changes['last_view'])) ?>" onclick="toggleDateSelect()"></i> 
            Recent changes
        </h3>
        <select id="dateSelect" class="hidden mt-2" onchange="reloadWithDate()" onfocus="storeOriginalValue()" onblur="hideIfSameOption()">
            <option value="lastlogin">Since your last login</option>
            <option value="<?= date("Y-m-d", strtotime('-1 week')) ?>">The last week (since <?= date("l, d F Y", strtotime('-1 week')) ?></option>
            <option value="<?= date("Y-m-d", strtotime('-2 weeks')) ?>">The last fortnight (since <?= date("l, d F Y", strtotime('-2 weeks')) ?></option>
            <option value="<?= date("Y-m-d", strtotime('-1 month')) ?>">The last month (since <?= date("l, d F Y", strtotime('-1 month')) ?></option>
        </select>
        <script>
            var dateSelect = document.getElementById('dateSelect');
            var originalValue = dateSelect.value;

            dateSelect.value = "<?= isset($_GET['changessince']) ? $_GET['changessince'] : '' ?>";

            function toggleDateSelect() {
                if (dateSelect.classList.contains('hidden')) {
                    dateSelect.classList.remove('hidden');
                    dateSelect.focus(); // Focus the select element when it is shown
                } else {
                    dateSelect.classList.add('hidden');
                }
            }

            function reloadWithDate() {
                var selectedDate = dateSelect.value;
                window.location.href = window.location.pathname + "?changessince=" + selectedDate;
            }

            function storeOriginalValue() {
                originalValue = dateSelect.value;
            }

            function hideIfSameOption() {
                if (dateSelect.value === originalValue) {
                    dateSelect.classList.add('hidden');
                }
            }
        </script>
        <div id="recentchanges" class="relative pt-6 xs:pt-1">
            <div class="tabs absolute -top-0 text-lg gap-2">
                <div class="active tab px-4 py-2 h-11 border-top" data-tab="visitorstab">
                    <span class="hidden sm:inline">Visitors</span>
                    <span class="sm:hidden" title="Visitors"><i class="fas fa-heart"></i></span>
                </div>
                <div class="tab px-4 py-2 h-11" data-tab="discussionstab">
                    <span class="hidden sm:inline">Chats</span>
                    <span class="sm:hidden" title="Chats"><i class="fas fa-comments"></i></span>
                </div>
                <div class="tab px-4 py-2 h-11" data-tab="individualstab">
                    <span class="hidden sm:inline">Tree</span>
                    <span class="sm:hidden" title="Family"><i class="fas fa-users"></i></span>
                </div>
                <div class="tab px-4 py-2 h-11" data-tab="eventstab">
                    <span class="hidden sm:inline">Events</span>
                    <span class="sm:hidden" title="Events"><i class="fas fa-calendar-alt"></i></span>
                </div>
                <div class="tab px-4 py-2 h-11" data-tab="filestab">
                    <span class="hidden sm:inline">Media</span>
                    <span class="sm:hidden" title="Media"><i class="fas fa-photo-video"></i></span>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-8">          
            <!-- Recent Changes Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg mt-8 h-64 overflow-y-auto">
                <div class="grid grid-cols-1 gap-8">


                <div class="tab-content active" id="visitorstab">
                        <div class="flex flex-wrap justify-center">
                        <?php if(empty($changes['visitors'])): ?>
                            <div class="text-center text-gray-500">No <span title="family">vuvale</span> online at the moment.</div>
                        <?php endif; ?>
                        <?php foreach ($changes['visitors'] as $visitor): ?>
                            <?php
                            // if strtotime($visitor['last_view']) is less then 10 minutes ago, then show the visitor as online
                            $activityclass = strtotime($visitor['last_view']) > strtotime('-10 minutes') ? 'useronline' : 'useroffline';
                            $lastViewTime = strtotime($visitor['last_view']);
                            $timeprefixOptions = $lastViewTime > strtotime('-10 minutes') ? ['is visiting', 'is here', 'is online'] : ['a gole mail', 'dropped by', 'popped in for a bit', 'stopped in', 'checked things out', 'visited us', 'a mai sikova', 'hungout', 'was here', 'made a visit', 'looked around'];
                            $timeprefix = $timeprefixOptions[array_rand($timeprefixOptions)];
                            ?>
                                <div class="text-left w-64 m-2 p-1 border rounded-xl shadow-xl <?= $activityclass ?>"> 
                                <a href='?to=family/users&user_id=<?= $visitor['user_id'] ?>'><?php echo $web->getAvatarHTML($visitor['user_id'], "md", "mt-1 ml-1 pt-0 pl-0 avatar-float-left object-cover ".($auth->getUserPresence($visitor['user_id']) ? 'userpresent' : 'userabsent')); ?></a>
                                    <div class='visitors-content text-center pr-1'>
                                        <div>
                                            <a href='?to=family/users&user_id=<?= $visitor['user_id'] ?>'><b><?= $visitor['first_name'] ?>&nbsp;<?= $visitor['last_name'] ?></b></a> <?= $timeprefix ?> 
                                            <?= $web->timeSince($visitor['last_view']); ?>.
                                        </div>
                                    </div>
                                </div>
                        <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tab-content" id="discussionstab">
                        <div class="flex flex-wrap justify-center">
                        <?php if(empty($changes['discussions'])): ?>
                            <div class="text-center text-gray-500">No new discussions at the moment.</div>
                        <?php endif; ?>
                        <?php foreach ($changes['discussions'] as $discussion): ?>
                            <div class='border shadow-xl float-left rounded px-0 pt-0 pb-2 m-2 max-w-48 text-center relative max-w-xs leading-tight bg-gray-800 bg-opacity-10'>
                                <div class="w-full text-xs pt-0 pt-1 pb-1 ml-0 mr-0 mt-0 bg-brown rounded-t text-white">
                                <?php if($discussion['individual_id']) {
                                    echo "New ".$discussion['change_type']." in <b>Family Tree Chat:</b>";
                                    $url="?to=family/individual&individual_id=".$discussion['individual_id'];
                                } else {
                                    echo "<b>General Chat:</b> New ".$discussion['change_type'];
                                    $url="?to=communications/discussions&view_discussion_id=".$discussion['discussionId'];
                                }
                                ?>
                                </div>
                                <div class="text-left max-w-sm mt-2"> 
                                <?php echo $web->getAvatarHTML($discussion['user_id'], "md", "mt-1 ml-1 mr-2 pt-0 pl-0 avatar-float-left object-cover"); ?>
                                    <div class='discussion-content text-left pr-1'>
                                        <div class="text-xs italic text-gray-500">
                                            Posted by <?= $discussion['user_first_name'] ?>&nbsp;<?= $discussion['user_last_name'] ?>
                                            <span title="<?= date('F j, Y, g:i a', strtotime($discussion['updated_at'])) ?>"><?= $web->timeSince($discussion['updated_at']); ?></span>
                                        </div>
                                        <a href='<?= $url ?>'><b class="leading-tight text-md font-bold"><?= $discussion['title'] ?></b></a>
                                        <p class="text-sm"><?= $web->truncateText($discussion['content'], 10, "Read more", "discussion_".$discussion['discussionId']) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>



                    <div class="tab-content active" id="individualstab">
                        <div class="flex flex-wrap justify-center" id="family-tree">
                        <?php if(empty($changes['individuals'])): ?>
                            <div class="text-center text-gray-500">No new family members at the moment.</div>
                        <?php endif; ?>
                        <?php foreach ($changes['individuals'] as $individual): ?>
                        <?php $keyImagePath=$individual['keyimagepath'] ? $individual['keyimagepath'] : "images/default_avatar.webp"; ?>
                            <div class="relative text-left w-64 h-12 m-2 p-1 border rounded-xl shadow-xl leading-none treegender_<?= $individual['gender'] ?> rounded">
                                <div class='cursor-pointer text-lg w-full text-left' title='See details for <?= $individual['tree_first_name'] . " " . $individual['tree_last_name'] ?>' onclick='window.location.href=&apos;?to=family/individual&amp;individual_id=<?= $individual['individualId'] ?>&apos;'>    
                                    <img src='<?= $keyImagePath ?>' class='avatar-img-sm avatar-float-left border object-cover mt-0.5 ml-0 pt-0 pl-0 mr-2' title='<?= $individual['tree_first_name'] . " " . $individual['tree_last_name'] ?>'>
                                    <b>
                                        <?= explode(" ", $individual['tree_first_name'])[0] ?> <?= $individual['tree_last_name'] ?>
                                    </b>
                                    <div class="absolute w-64 pr-5 bottom-0 p-1 ml-8 italic break-words leading-none text-left">
                                        <span class="text-xxs">Added by <?= $individual['user_first_name'] ?> - <?= date("D, d M  g:ia", strtotime($individual['updated']) ) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>



                    
                    <div class="tab-content" id="eventstab">
                        <div class="flex flex-wrap justify-center">
                        <?php $itemlist=array(); //Set up the final item list to show all items according to their type/group ?>
                        <?php
                        if(empty($changes['items'])): ?>
                            <div class="text-center text-gray-500">No new items at the moment.</div>
                        <?php endif; 
                        
                        foreach ($changes['items'] as $key=>$itemgroup) :
                            if(isset($itemgroup['items']) && is_array($itemgroup['items']) && count($itemgroup['items']) > 0):
                                //echo "<pre>"; print_r($itemgroup); echo "</pre>";
                                $groupTitle=$key;
                                foreach($itemgroup['items'] as $item) {
                                    //echo "<pre>"; print_r($item); echo "</pre>";
                                    $itemlist['group_'.$item['unique_id']][$item['item_id']]=$item;
                                    $itemlist['group_'.$item['unique_id']]['privacy']=$itemgroup['privacy'];
                                }
                                
                            else:
                                //$groupTitle=$key;
                                //echo "<pre>ITEMGROUP<br />"; print_r($itemgroup); echo "</pre>";
                                //echo "<pre>ITEMLIST<br />"; print_r($itemlist); echo "</pre>";
                                foreach($itemgroup as $item) {
                                    //echo "<pre>"; print_r($item); echo "</pre>";
                                    //echo "item_".$item['item_id']."<br />";
                                    $itemlist['item_'.$item['item_id']][$item['item_id']]=$item;
                                    $itemlist['item_'.$item['item_id']]['privacy']=$itemgroup['privacy'];
                                }
                            endif; 


                        endforeach;
                            
                        foreach($itemlist as $itemgroup) {
                            $imgclasses=count($itemgroup) > 1 ? "w-1/4 float-right mx-1" : "w-2/5 mx-auto";
                            $firstItem=reset($itemgroup);
                            //echo "<pre>"; print_r($itemgroup); echo "</pre>";
                            $itemidentifier=$firstItem['unique_id']."_".$firstItem['item_id'];
                            $groupTitle=!empty($firstItem['item_group_name']) ? $firstItem['item_group_name'] : $firstItem['detail_type'];
                            
                            if($itemgroup['privacy'] == 'private') {
                                $privacystamp="<div class='relative' title='The owner of this information has asked for it to be kept private'><div class='stamp stamp-red-double cursor-info'>Private</div></div>";
                            } else {
                                $privacystamp="";
                            }
                            ?>
                            <div class='document-item m-2 mb-4 text-center items-center cursor-pointer shadow-lg rounded-lg text-xs sm:text-sm relative w-36 break-words' onClick='window.location.href="?to=family/individual&individual_id=<?=$firstItem['individualId'] ?>&tab=generaltab"'>
                                <div class="item_header p-1 h-12 rounded mb-1 bg-deep-green text-white break-words text-center items-center center text-sm">
                                    <b>
                                        <?= explode(" ", $firstItem['tree_first_names'])[0] . " " . $firstItem['tree_last_name'] ?>
                                    </b>
                                    <?= str_replace("Key Image", "Pic", $groupTitle) ?> 
                                    <?= $privacystamp ?>
                                </div>
                                <div id="eventid_<?= $itemidentifier ?>" class="item_body relative break-words text-gray leading-none bg-cream m-0.5 h-12 overflow-y-scroll overflow-y-hidden">
                                    <?php foreach ($itemgroup as $key=>$itemdetail) : ?>
                                        <?php if(!empty($itemdetail['file_id'])): ?>
                                            <?php if ($itemdetail['file_type'] == 'image'): ?>
                                                <script>
                                                    var eventElement=document.getElementById('eventid_<?= $itemidentifier ?>');
                                                    eventElement.innerHTML = "<img class='<?= $imgclasses ?> rounded object-cover' src='<?= $itemdetail['file_path'] ?>' alt='<?= $itemdetail['detail_value'] ?>'/>"+eventElement.innerHTML;
                                                </script>
                                            <?php else: ?>
                                                <div class="text-xxs text-left px-1 pb-1">
                                                    <b><?= $itemdetail['detail_type'] ?>:</b> <a href='<?= $itemdetail['file_path'] ?>' class='text-blue-500 hover:text-blue-700'><?= $itemdetail['file_description'] ?></a>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if(!empty($itemdetail['detail_value'])): ?>
                                                <div class="text-xxs text-left px-1 pb-1 leading-tight" title="<?= $itemdetail['item_id'] ?>">
                                                    <b><?= $itemdetail['detail_type'] ?>:</b>
                                                <?php if($item_styles[$itemdetail['detail_type']] == "individual") : ?>
                                                    <a href='?to=family/individual&individual_id=<?=$itemdetail['individual_name_id'] ?>'><?= $itemdetail['individual_name'] ?></a>
                                                <?php elseif($item_styles[$itemdetail['detail_type']] == "file"): ?>
                                                    <?php if($itemdetail['detail_type'] == "Photo"): ?>
                                                        <script>
                                                            var eventElement = document.getElementById('eventid_<?= $itemidentifier ?>');
                                                            eventElement.innerHTML = "<img class='<?= $imgclasses ?> rounded object-cover' src='<?= $itemdetail['detail_value'] ?>' alt='<?= $itemdetail['detail_value'] ?>'/>" + eventElement.innerHTML;
                                                        </script>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?= $web->truncateText($itemdetail['detail_value'], 15); ?>
                                                <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                    <?php endforeach; ?>
                                </div>
                                <div style='clear: both'></div>
                                <div style='height: 15px;'></div>
                                <div class="item_footer absolute bottom-1 w-full p-0 italic break-words leading-none text-ocean-blue">
                                    <span class="text-xxs">By <?= $firstItem['first_name'] ?> - <?= date("D d M g:ia", strtotime($firstItem['updated'])) ?></span>
                                </div>                                    

                            </div>
                        <?php } ?>
                        </div>
                    </div>


                    <div class="tab-content" id="filestab">
                        <div class="flex flex-wrap justify-center">
                        <?php if(empty($changes['files'])): ?>
                            <div class="text-center text-gray-500">No new media files at the moment.</div>
                        <?php endif; ?>
                        <?php foreach ($changes['files'] as $file): ?>
                            <div class='border rounded p-2 m-2 text-center text-wrap w-48 shadow-xl text-sm'>
                            <?= $file['file_description'] ?>
                            saved to <a href='?to=family/individual&individual_id=<?= $file['individualId'] ?>'><?= $file['tree_first_name'] ." ".$file['tree_last_name'] ?></a><br />
                            <?php if ($file['file_type'] == 'image'): ?>
                                <img src='<?= $file['file_path'] ?>' class='w-full h-auto rounded' />
                            <?php else: ?>
                                <a href='<?= $file['file_path'] ?>' class='text-blue-500 hover:text-blue-700'>Download</a>
                            <?php endif; ?>
                            <span class="text-xxs">Added by <?= $file['user_first_name'] . " " . $file['user_last_name'] ?></span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>            

        </div>
    </section>



<?php else: ?>

    <!-- Public Information for Visitors -->
    <section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-3 gap-8">
            <!-- About Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">About Nataleira</h3>
                <p class="mt-2">Learn about the rich history and culture of Nataleira.</p>
                <a href="?to=about/aboutnataleira.php" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">About Nataleira</a>
            </div>

            <!-- Login/Register Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">About this site</h3>
                <p class="mt-2">Find out more about this site, it's story and who it is for.</p>
                <a href="?to=about/aboutvirtualnataleira.php" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">About <i>Soli's Children</i></a>
            </div>            

            <!-- Login/Register Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">Login or Register</h3>
                <p class="mt-2">Soli's Children can enter the village by logging in or registering.</p>
                <a href="?to=login" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Login</a> | 
                <a href="?to=register" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Register</a>
            </div>
        </div>
    </section>

<?php endif; ?>