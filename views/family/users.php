<?php
    $sql = "SELECT users.id, users.first_name, users.last_name, users.role, 
    users.email, users.avatar, users.individuals_id
    FROM users
    WHERE approved = 1
    AND role IN ('member', 'admin') 
    AND role != 'deleted'
    ORDER BY users.last_name, users.first_name";

    $users = $db->fetchAll($sql);

    if(isset($_GET['user_id'])) {
        $user_id = $_GET['user_id'];
        $sql = "SELECT users.id, users.first_name, users.last_name, users.email, 
            users.avatar, users.individuals_id, users.show_presence
            FROM users
            WHERE users.id = $user_id";

        $user = $db->fetchOne($sql);
        $user['avatar'] = $user['avatar'] ? $user['avatar'] : "images/default_avatar.webp";
        
        if($user['individuals_id']) {
            ?>
            <script>
                window.location = '?to=family/individual&individual_id=<?= $user['individuals_id'] ?>';
            </script>
            <?php
            return;
        }

        $descendancy = [];
        if($user['individuals_id']) {
            $descendancy=Utils::getLineOfDescendancy(Web::getRootId(), $user['individuals_id']);
        } 


    }    
    

?>

<section class="hero bg-deep-green text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold">Site Members</h1>
        <p class="mt-4 text-lg">
            <i>People who are registered to use '<?= $site_name ?>'</i>
        </p>
        <?php         
            ?>
        <div id="individual-options" class="absolute flex justify-between items-center w-full bottom-0 left-0 rounded-b-lg p-0 m-0">
            <?php if($user['individuals_id']) { ?>
            <button class="flex-1 bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" title="View <?= $user['first_name'] ?> in the family tree" onclick="window.location.href='index.php?to=family/tree&zoom=<?= $user['individuals_id'] ?>&root_id=<?= $web->getRootId() ?>'">
                <i class="fas fa-network-wired" style="transform: rotate(180deg)"></i> <!-- FontAwesome icon -->
            </button>
            <?php if($_SESSION['user_id'] == $user_id || $auth->getUserRole() === 'admin') { ?>
            <button class="flex-1 bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" title="Edit <?= $user['first_name'] ?>&apos;s account" onclick="window.location.href='index.php?to=account&user_id=<?= $user['id'] ?>'">
                <i class="fas fa-users"></i> <!-- FontAwesome icon -->
            </button>
            <?php } ?>
        </div>
        <?php } ?>
    </div>
</section>

<?php
if(isset($_GET['user_id'])) {
?>
    <?php if($descendancy): ?>
        <section class="container mx-auto pt-6 pb-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap justify-center items-center text-xxs sm:text-sm">
                <?php foreach($descendancy as $index => $descendant): ?>
                    <div class="bg-burnt-orange-800 nv-bg-opacity-20 text-center p-1 sm:p-2 my-1 sm:my-2 rounded-lg">
                        <a title='View <?= $descendant[0] ?>&apos;s details' href='?to=family/individual&individual_id=<?= $descendant[1] ?>'><?= $descendant[0] ?></a>
                    </div>
                    <?php if ($index < count($descendancy) - 1): ?>
                        <i class="fas fa-arrow-right mx-2"></i> <!-- FontAwesome arrow icon -->
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <section class="container mx-auto pt-12 px-4 sm:px-6 lg:px-8">
        <div class="absolute z-10 p-2 w-max">
            <div class="flex justify-full items-top">
                <div>
                    <img src="<?= $user['avatar'] ? $user['avatar'] : "images/default_avatar.webp" ?>" alt="<?= $user['first_name'] . ' ' . $user['last_name'] ?>" class="avatar-img-lg avatar-float-left ml-1 mr-4 <?= $auth->getUserPresence($_GET['user_id']) ? "userpresent" : "userabsent"; ?> rounded-full object-cover w-12 h-12">
                </div>
                <div>
                    <h3 class="text-2xl font-bold">
                        <?= $user['first_name'] . ' ' . $user['last_name'] ?>
                    </h3>
                    <span><?= $user['email'] ?> <a href='mailto: <?= $user['email'] ?>' title="Send an email to <?= $user['first_name'] ?>" ><i class="fas fa-envelope text-xs text-gray-600"></i></a></span>
                </div>
            </div>
        </div>
        <div id="userDetails" class="pt-10">
            <div class="p-6 bg-white shadow-lg rounded-lg mt-8 h-64 overflow-y-auto">
            </div>
        </div>
    </section>

<?php }  else {?>

    <section class="container mx-auto pt-12 px-4 sm:px-6 lg:px-8">
        <div id="userlist" class="relative pt-6 xs:pt-1">
            <div class="p-6 bg-white shadow-lg rounded-lg mt-8 h-64 overflow-y-auto">
            <?php foreach($users as $user): ?>
                <div class="flex justify-between items-center border-b border-gray-200 py-2">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gray-300 rounded-full flex items-center justify-center mr-4">
                            <img src="<?= $user['avatar'] ? $user['avatar'] : "images/default_avatar.webp" ?>" alt="<?= $user['first_name'] . ' ' . $user['last_name'] ?>" class="rounded-full object-cover w-12 h-12">
                        </div>
                        <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-4">
                            <?php if($user['role'] == 'admin'): ?>
                                <i class="fas fa-user-shield text-md text-gray-600"></i>
                            <?php else: ?>
                                <i class="fas fa-user text-md text-gray-600"></i>
                            <?php endif; ?>
                        </div>
                        <div class="w-64">
                            <h3 class="text-lg font-bold"><?= $user['first_name'] . ' ' . $user['last_name'] ?></h3>
                            <p class="text-sm text-gray-600"><?= $user['email'] ?></p>
                        </div>
                    </div>
                    <div class="flex items center">
                        <button class="bg-blue-500 text-white px-2 py-1 mx-2 rounded-lg w-20">View</button>
                    </div>
                    <?php if($user['individuals_id']): ?>
                        <div class="flex items center">
                            <button class="bg-brown text-white px-2 py-1 mx-2 rounded-lg w-20" onClick="window.location='?to=family/individual&individual_id=<?= $user['individuals_id'] ?>';"><i class="text-xs -ml-3 mr-2 fas fa-network-wired"></i>Tree</button>
                        </div>
                    <?php else: ?>
                        <div class="flex items center">
                            <div class="inline px-2 py-1 mx-2 rounded-lg w-20">&nbsp;</div>
                        </div>
                    <?php endif ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php } ?>