<div id="Nothing"></div>
<?php
if(isset($_GET['user_id'])) {
    $sql = "SELECT users.id, users.id as user_id, users.first_name, users.last_name, users.email, 
        users.avatar, users.individuals_id, users.show_presence
        FROM users
        WHERE users.id = ?";

    $user = $db->fetchOne($sql, [$user_id]);
    $user['avatar'] = $user['avatar'] ? $user['avatar'] : "images/default_avatar.webp";
    
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

if($user_id) { 
?>
    <script type="text/javascript">
        <?php 
        if($user['individuals_id']) { 
        ?>
        document.getElementById('individual-options').innerHTML = '<button class="flex-1 bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" title="View <?= $user['first_name'] ?> in the family tree" onclick="window.location.href='index.php?to=family/tree&zoom=<?php echo $user['individuals_id'] ?>&root_id=<?php echo $web->getRootId() ?>'"><i class="fas fa-network-wired" style="transform: rotate(180deg)"></i></button>';
        <?php 
        } 
        if($_SESSION['user_id'] == $user_id || $auth->getUserRole() === 'admin') { ?>
        document.getElementById('individual-options').innerHTML = '<button class="flex-1 bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" title="Edit <?php echo $user['first_name'] ?>&apos;s account" onclick="window.location.href='index.php?to=account&user_id=<?php echo $user['id'] ?>'"><i class="fas fa-users"></i></button>'+document.getElementById('individual-options').innerHTML();
        <?php 
        } 
        ?>
            
    </script>
<?php 
} 
?>

<section class="container mx-auto px-4 sm:px-3 xs:px-2 lg:px-8 pt-1 pb-12">
    <div class="absolute z-10 p-2 w-max">
        <div class="flex justify-full items-top">
            <div>
                <img class="mt-6 sm:mt-0 h-20 w-20 sm:h-36 sm:w-36 avatar-img-0 avatar-float-left ml-1 mr-4 <?php echo $auth->getUserPresence($user_id) ? 'userpresent' : 'userabsent'; ?> rounded-full object-cover" src="<?php echo $user['avatar'] ? $user['avatar'] : 'images/default_avatar.webp'; ?>" alt="<?php echo $user['first_name'] . ' ' . $user['last_name'] ?>">
            </div>

        </div>
    </div>

    <!-- User's Page -->
    <div id="userDetails" class="pt-10">
        <div class="p-4 pt-6 bg-white shadow-lg rounded-lg mt-8 h-128 overflow-y-auto">


        <?php 
        if($user_id == $_SESSION['user_id']  || $auth->getUserRole() === 'admin') {
            if($auth->getUserRole() === 'admin' && $user_id != $_SESSION['user_id']) {
        ?>
            <div class="pb-6">
                <div class="relative -mt-4 -ml-4 -mr-4 -mb-4 flex justify-center items-center text-center border-t-2 bg-gradient-to-b from-gray-200 to-white">
                    <div class='w-1 -mb-2'></div>
                    <div class='flex-grow text-sm italic -mb-2'>
                        This is the user's personal page, only visible to the user (and you because you're an admin).
                    </div>
                    <div class='w-1 -mb-2'></div>
                </div>
            </div>
            <?php } ?>

            <!-- User's Personal Page (only visible to the user) -->
            <div class="pb-6"> 
                <div class="relative flex justify-center items-center text-center">
                    <div class='w-1'></div>
                    <div class="flex-grow" style="z-index: 100000">
                        <h3 class="text-2xl whitespace-nowrap font-bold p-1 rounded" style="z-index: 100000" >
                            <span class="text-ocean-blue bg-white-800 nv-bg-opacity-50"><?php echo $user['first_name'] ?>'s Home</span>
                        </h3>
                    </div>
                    <div class='w-1'></div>
                </div>
                <div id="tasks">
                    <div class="flex justify-center items-center border-gray-200 py-2 w-full">
                        <div class="flex items-center my-8">
                            <div class="w-12 h-12 bg-gray-300 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-tasks text-3xl text-gray-600"></i>
                            </div>
                            <div class="w-full">
                                <h3 class="text-lg font-bold">To-do List</h3>
                                <p class="text-gray-600">Things you might want to do to get more connected to our family diaspora..</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 justify-center items-center sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs sm:text-sm md:text-sm lg:text-md py-2 mx-1 text-sm sm:text-xs">
                        <?php if(!$user['individuals_id']) { ?>
                        <div class="flex border p-2 rounded-full h-28 overflow-hidden hover:bg-ocean-blue-800 hover:nv-bg-opacity-10 cursor-pointer onclick="window.location.href='index.php?to=family/individual&individual_id=<?php echo $user['individuals_id'] ?>'">
                            <button class="bg-ocean-blue-800 nv-bg-opacity-50 text-white rounded-full h-16 py-2 text-xl px-5 my-4 mx-1" title="Connect to our tree" onclick="window.location.href='index.php?to=family/individual&individual_id=<?php echo $user['individuals_id'] ?>'"><i class="fas fa-user-friends"></i></button>
                            <p class="text-gray-600 ml-3 h-22 overflow-y-scroll"><b>Link Yourself In</b><br />Your account is not yet linked to a person in our family tree. Connect yourself to your entry in our tree so we all know where you fit into the Diaspora, and so you can take "ownership" of your information.</p>
                        </div>
                        <?php } ?>
                        <?php if($user['avatar'] == 'images/default_avatar.webp') { ?>
                        <div class="flex border p-2 rounded-full h-28 overflow-hidden hover:bg-burnt-orange-800 hover:nv-bg-opacity-10 cursor-pointer " onclick="triggerKeyPhotoUpload();">
                            <button class="bg-burnt-orange-800 nv-bg-opacity-50 text-white rounded-full h-16 py-2 text-xl px-6 my-4 mx-1" title="Add a profile picture" onclick="triggerKeyPhotoUpload()"><i class="fas fa-portrait"></i></button>
                            <p class="text-gray-600 ml-3 h-22 overflow-y-scroll"><b>Add a Profile Picture</b><br />You don't have a profile picture yet. Add one so we can see your lovely face!</p>
                        </div>
                        <?php } ?>
                        <div class="flex border p-2 rounded-full h-28 overflow-hidden hover:bg-deep-green-800 hover:nv-bg-opacity-10 cursor-pointer " onclick="window.location.href='index.php?to=account&user_id=<?php echo $user['user_id'] ?>'">
                            <button class="bg-deep-green-800 nv-bg-opacity-50 text-white rounded-full h-16 py-2 text-xl px-6 my-4 mx-1" title="Edit your account"><i class="fas fa-user"></i></button>
                            <p class="text-gray-600 ml-3 h-22 overflow-y-scroll"><b>Edit your account</b><br />Add a profile picture, set your privacy level and more</p>
                        </div>
                        <div class="flex border p-2 rounded-full h-28 overflow-hidden hover:bg-brown-800 hover:nv-bg-opacity-10 cursor-pointer ">
                            <button class="bg-brown-800 nv-bg-opacity-50 text-white rounded-full h-16 py-2 text-xl px-5 my-4 mx-1" title="View your family tree" onclick="window.location.href='index.php?to=family/tree&zoom=<?php echo $user['individuals_id'] ?>&root_id=<?php echo $web->getRootId() ?>'"><i class="fas fa-network-wired" style="transform: rotate(180deg)"></i></button>
                            <p class="text-gray-600 ml-3 h-22 overflow-y-scroll"><b>Help fill out the tree</b><br />This is a site for collaboration and sharing - so why not find an ancestor you know, or know about, and tell us some stories?</p>
                        </div>
                    </div>
                </div>

            </div>
        <?php 
        } 
        ?>
            <!-- User's Public Information (for other members) -->
            <div class="pb-6">
                <div class="relative grid grid-cols-3 justify-center items-center text-center border-t-2 bg-gradient-to-b from-gray-200 to-white">
        
                </div>
            </div>

        </div>
    </div>
</section>