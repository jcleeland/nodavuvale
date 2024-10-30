<?php

// Check if the user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold">Welcome to <i><?= $site_name ?></i></h2>
        <p class="mt-4 text-lg">Connecting our family and preserving our cultural heritage.</p>
        <!-- Button-styled anchor tag -->
        <a href="?to=about/aboutvirtualnataleira" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
            Our website
        </a>
        <a href="?to=about/aboutnataleira" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
            Our village
        </a>

    </div>
</section>

<!-- Conditional Content Section Based on Login Status -->
<?php if ($is_logged_in): ?>
<?php
    $changes=Utils::getNewStuff($user_id, "1 week ago");
    $summary=array();
    $summary['Discussions']=count($changes['discussions'])>0 ? count($changes['discussions'])." new discussions" : "";
    $summary['Individuals']=count($changes['individuals'])>0 ? count($changes['individuals'])." new individuals" : "";
    $summary['Relationships']=count($changes['relationships'])>0 ? count($changes['relationships'])." new relationships" : "";
    $summary['Items']=count($changes['items'])>0 ? count($changes['items'])." new items" : "";
    $summary['Files']=count($changes['files'])>0 ? count($changes['files'])." new files" : "";
    
?>

    <!-- Changes and Updates Section -->
    <section class="container mx-auto py-12 px-4 sm:px-6 lg: px-8 pt-10">
        <h3 class="text-2xl font-bold">New since <?= date("d M Y", strtotime($changes['last_view'])) ?></h3>
        <div class="relative pt-6">
            <div class="tabs absolute -top-0 text-sm md:text-lg gap-2">
                <div class="active tab px-4 py-2" data-tab="discussionstab">Chats</div>
                <div class="tab px-4 py-2" data-tab="individualstab">Family</div>
                <div class="tab px-4 py-2" data-tab="relationshipstab">Relationships</div>
                <div class="tab px-4 py-2" data-tab="eventstab">Events</div>
                <div class="tab px-4 py-2" data-tab="filestab">Media</div>
            </div>  
            <div class="grid grid-cols-1 gap-8">          
            <!-- Recent Changes Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg mt-8">
                <div class="grid grid-cols-1 gap-8">
                    <div class="tab-content active" id="discussionstab">
                        <div class="flex flex-wrap justify-center">
                        <?php foreach ($changes['discussions'] as $discussion): ?>
                            <div class='border float-left rounded p-0 m-2 text-center relative min-w-md'>
                                <div class="w-full text-xs pt-0 ml-0 mr-0 mt-0 bg-brown rounded text-white">
                                <?php if($discussion['individual_id']) {
                                    echo "Family Tree Chat";
                                    $url="?to=family/individual&individual_id=".$discussion['individual_id'];
                                } else {
                                    echo "General Chat";
                                    $url="?to=communications/discussions&view_discussion_id=".$discussion['discussionId'];
                                }
                                ?>
                                </div>
                                <div class="px-1 pt-1 pb-1 max-w-xs leading-none">
                                    <?php echo $web->getAvatarHTML($discussion['user_id'], "md", "avatar-float-left object-cover"); ?>
                                    <a href='<?= $url ?>'><?= $discussion['title'] ?></a><br />
                                    <div class='absolute bottom-1 right-1 text-xxs whitespace-no-wrap bg-white-800 bg-opacity-20'>Added by <?= $discussion['user_first_name'] . " " . $discussion['user_last_name'] ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="tab-content active" id="individualstab">
                        <div class="flex flex-wrap justify-center">
                        <?php foreach ($changes['individuals'] as $individual): ?>
                            <div class='border rounded p-2 m-2 text-center'>
                                <a href='?to=family/individual&individual_id=<?= $individual['individualId'] ?>'><?= $individual['tree_first_name'] . " " . $individual['tree_last_name'] ?></a><br />
                                <span class="text-xxs">Added by <?= $individual['user_first_name'] . " " . $individual['user_last_name'] ?></span>  
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="tab-content" id="relationshipstab">
                        <div class="flex flex-wrap justify-center">
                        <?php foreach ($changes['relationships'] as $relationship): ?>
                            <div class='border rounded p-2 m-2 text-center text-sm'>
                                <a href='?to=family/individual&individual_id=<?=$relationship['object_individualId'] ?>'><?= $relationship['object_first_names'] ?> <?= $relationship['object_last_name'] ?></a><br />
                                marked as a <?= $relationship['relationship_type'] ?> of<br />
                                <a href='?to=family/individual&individual_id=<?=$relationship['subject_individualId'] ?>'><?= $relationship['subject_first_names'] ?> <?= $relationship['subject_last_name'] ?></a><br />
                                <span class="text-xxs">Connection made <?= $relationship['updated'] ?></span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="tab-content" id="eventstab">
                        <div class="flex flex-wrap justify-center">
                        <?php foreach ($changes['items'] as $item): ?>
                            <div class='border rounded p-2 m-2 text-center'>    
                            <?= !empty($item['item_group_name']) ? $item['item_group_name'] : $item['detail_type'] ?>
                            for 
                            <a href='?to=family/individual&individual_id=<?=$item['individualId'] ?>'>
                                <?= $item['tree_first_name'] . " " . $item['tree_last_name'] ?>
                            </a><br />
                            <span class="text-xxs">Added by <?= $item['user_first_name'] . " " . $item['user_last_name'] ?></span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="tab-content" id="filestab">
                        <div class="flex flex-wrap justify-center">
                        <?php foreach ($changes['files'] as $file): ?>
                            <div class='border rounded p-2 m-2 text-center text-wrap max-w-xs'>
                            <?= $file['file_description'] ?> added to <?= $file['tree_first_name'] ." ".$file['tree_last_name'] ?><br />
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