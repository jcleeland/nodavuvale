<?php

// Check if the user is logged in
$is_logged_in = isset($_SESSION['user_id']);
// Set the default "view new" as being the last login time
$viewnewsince=isset($_SESSION['last_login']) ? date("Y-m-d H:i:s", strtotime('-1 day', strtotime($_SESSION['last_login']))) : date("Y-m-d H:i:s", strtotime('1 week ago'));

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
    $changes=Utils::getNewStuff($user_id, $viewnewsince);
    $summary=array();
    $summary['Discussions']=count($changes['discussions'])>0 ? count($changes['discussions'])." new discussions" : "";
    $summary['Individuals']=count($changes['individuals'])>0 ? count($changes['individuals'])." new individuals" : "";
    $summary['Relationships']=count($changes['relationships'])>0 ? count($changes['relationships'])." new relationships" : "";
    $summary['Items']=count($changes['items'])>0 ? count($changes['items'])." new items" : "";
    $summary['Files']=count($changes['files'])>0 ? count($changes['files'])." new files" : "";

    $item_types = Utils::getItemTypes();
    $item_styles= Utils::getItemStyles();
    
?>

    <!-- Changes and Updates Section -->
    <section class="container mx-auto py-12 px-4 sm:px-3 xs:px-2 lg:px-8 pt-10">
        <h3 class="text-2xl font-bold"><i class="fas fa-bell" title="Changes and updates since <?= date("l, d F Y", strtotime($changes['last_view'])) ?>"></i> Recent changes</h3>
        <div id="recentchanges" class="relative pt-6 xs:pt-1">
            <div class="tabs absolute -top-0 text-lg gap-2">
                <div class="active tab px-4 py-2 h-11" data-tab="discussionstab">
                    <span class="hidden sm:inline">Chats</span>
                    <span class="sm:hidden" title="Chats"><i class="fas fa-comments"></i></span>
                </div>
                <div class="tab px-4 py-2 h-11" data-tab="visitorstab">
                    <span class="hidden sm:inline">Visitors</span>
                    <span class="sm:hidden" title="Visitors"><i class="fas fa-heart"></i></span>
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
                    <div class="tab-content active" id="discussionstab">
                        <div class="flex flex-wrap justify-center">
                        <?php foreach ($changes['discussions'] as $discussion): ?>
                            <div class='border float-left rounded px-0 pt-0 pb-2 m-2 max-w-48 text-center relative max-w-xs leading-tight bg-gray-800 bg-opacity-10'>
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
                                <?php echo $web->getAvatarHTML($discussion['user_id'], "md", "mt-1 ml-1 pt-0 pl-0 avatar-float-left object-cover"); ?>
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
                        <?php foreach ($changes['individuals'] as $individual): ?>
                            <?php $keyImagePath=$individual['keyimagepath'] ? $individual['keyimagepath'] : "images/default_avatar.webp"; ?>
                            <div width="100px" height="170px" class="m-2">
                                <div class="node treegender_<?= $individual['gender'] ?> rounded">
                                    <div class='nodeBodyText p-1'>
                                        <img src='<?= $keyImagePath ?>' class='nodeImage border object-cover' title='<?= $individual['tree_first_name'] . " " . $individual['tree_last_name'] ?>'>
                                        <span class='bodyName' title='See details for <?= $individual['tree_first_name'] . " " . $individual['tree_last_name'] ?>' onclick='window.location.href=&apos;?to=family/individual&amp;individual_id=<?= $individual['individualId'] ?>&apos;'>
                                            <?= explode(" ", $individual['tree_first_name'])[0] ?><br>
                                            <?= $individual['tree_last_name'] ?>
                                        </span><br />
                                        <span class="text-xxs italic">Added by <?= $individual['user_first_name'] . " " . $individual['user_last_name'] ?><br /><?= date("l, d F Y", strtotime($individual['updated']) ) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>



                    <div class="tab-content" id="visitorstab">
                        <div class="flex flex-wrap justify-center">
                        <?php foreach ($changes['visitors'] as $visitor): ?>
                            <?php
                            // if strtotime($visitor['last_view']) is less then 10 minutes ago, then show the visitor as online
                            $activityclass = strtotime($visitor['last_view']) > strtotime('-30 minutes') ? 'useronline' : 'useroffline';
                            $timeprefix = strtotime($visitor['last_view']) > strtotime('-30 minutes') ? 'is visiting' : 'visited';
                            ?>
                                <div class="text-left max-w-sm mt-2 p-1 border rounded <?= $activityclass ?>"> 
                                <?php echo $web->getAvatarHTML($visitor['user_id'], "md", "mt-1 ml-1 pt-0 pl-0 avatar-float-left object-cover"); ?>
                                    <div class='visitors-content text-left pr-1'>
                                        <div>
                                            <b><?= $visitor['first_name'] ?>&nbsp;<?= $visitor['last_name'] ?></b> <?= $timeprefix ?> 
                                            <span title="<?= date('F j, Y, g:i a', strtotime($visitor['last_view'])) ?>"><?= $web->timeSince($visitor['last_view']); ?></span>
                                        </div>
                                    </div>
                                </div>
                        <?php endforeach; ?>
                        </div>
                    </div>

                    
                    <div class="tab-content" id="eventstab">
                        <div class="flex flex-wrap justify-center">
                        <?php $itemlist=array(); //Set up the final item list to show all items according to their type/group ?>
                        <?php foreach ($changes['items'] as $key=>$itemgroup) : ?>
                                <?php 
                                if($key != "Singleton"):
                                    $groupTitle=$key;
                                    foreach($itemgroup as $item) {
                                        $itemlist['group_'.$item['item_identifier']][$item['item_id']]=$item;
                                    }
                                    
                                else:
                                    foreach($itemgroup as $item) {
                                        $itemlist['item_'.$item['item_id']][$item['item_id']]=$item;
                                    }
                                endif; 
                            endforeach;
                            
                            foreach($itemlist as $itemgroup) {
                                $firstItem=reset($itemgroup);
                                $groupTitle=!empty($firstItem['item_group_name']) ? $firstItem['item_group_name'] : $firstItem['detail_type'];
                                ?>
                            <div class='document-item m-2 mb-4 text-center items-center shadow-lg rounded-lg text-sm relative max-w-3xs break-words'>
                                <div class="item_header p-1 rounded mb-2 bg-brown text-white break-words text-center items-center center">
                                    <b><?= $groupTitle ?></b> added to<br /> 
                                    <a href='?to=family/individual&individual_id=<?=$firstItem['individualId'] ?>'>
                                        <?= explode(" ", $firstItem['tree_first_names'])[0] . " " . $firstItem['tree_last_name'] ?>
                                    </a>
                                </div>
                                <?php foreach ($itemgroup as $key=>$itemdetail) : ?>
                                    <?php if(!empty($itemdetail['file_id'])): ?>
                                        <?php if ($itemdetail['file_type'] == 'image'): ?>
                                            <center><img class="w-3/4 h-auto rounded object-cover" src='<?= $itemdetail['file_path'] ?>' alt="<?= $itemdetail['detail_value'] ?>"/></center>
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
                                                <a href='?to=family/individual&individual_id=<?=$itemdetail['detail_value'] ?>'><?= $itemdetail['detail_value'] ?></a>
                                            <?php elseif($item_styles[$itemdetail['detail_type']] == "file"): ?>
                                                <?php print_r($itemdetail); ?>
                                                <?php if($itemdetail['detail_type'] == "Photo"): ?>
                                                    <img class="w-3/4 h-auto rounded object-cover" src='<?= $itemdetail['detail_value'] ?>' alt="<?= $itemdetail['detail_value'] ?>"/>)
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?= $web->truncateText($itemdetail['detail_value'], 15); ?>
                                            <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                <?php endforeach; ?>
                        
                                <div class="item_body p-1 italic break-words leading-none">
                                    <span class="text-xxs">By <?= $firstItem['first_name'] . " " . $firstItem['last_name'] ?><br /><?= date("l, d F Y", strtotime($firstItem['updated'])) ?></span>
                                </div>                                    

                            </div>
                            <?php } ?>
                        </div>
                    </div>


                    <div class="tab-content" id="filestab">
                        <div class="flex flex-wrap justify-center">
                        <?php foreach ($changes['files'] as $file): ?>
                            <div class='border rounded p-2 m-2 text-center text-wrap max-w-xs'>
                            <?= $file['file_description'] ?> added to <a href='?to=family/individual&individual_id=<?= $file['individualId'] ?>'><?= $file['tree_first_name'] ." ".$file['tree_last_name'] ?></a><br />
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

    <!-- Logged-in Content Sections -->
    <section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Family Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">Our Diaspora</h3>
                <p class="mt-2">Explore your roots and connect with family members around the world.</p>
                <a href="?to=family/" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Connect with Family</a>
            </div>

            <!-- Village Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">Nataleira Life</h3>
                <p class="mt-2">Learn about the cultural traditions and the history of Nataleira village.</p>
                <a href="?to=village/" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Discover Village Life</a>
            </div>

            <!-- Communications Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">Getting Together</h3>
                <p class="mt-2">Read news, join discussions and stay in touch with the Soli diaspora.</p>
                <a href="?to=communications/" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Get Involved</a>
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