<?php
if(isset($_GET['user_id'])) {

        $sql = "SELECT users.id, users.first_name, users.last_name, users.email, 
            users.avatar, users.individuals_id, users.show_presence
            FROM users
            WHERE users.id = ?";

        $user = $db->fetchOne($sql, [$user_id]);
        $user['avatar'] = $user['avatar'] ? $user['avatar'] : "images/default_avatar.webp";
        
        if($user['individuals_id']) {
            ?>
            <script>
                window.location = '?to=family/individual&individual_id=<?= $user['individuals_id'] ?>';
            </script>
            <?php
            return;
        }




    }

?>
    <?php if($user_id) { ?>
        <script>
            document.getElementById('individual-options').innerHTML = `
                <button class="flex-1 bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" title="View <?= $user['first_name'] ?> in the family tree" onclick="window.location.href='index.php?to=family/tree&zoom=<?= $user['individuals_id'] ?>&root_id=<?= $web->getRootId() ?>'">
                    <i class="fas fa-network-wired" style="transform: rotate(180deg)"></i> 
                </button>
                <?php if($_SESSION['user_id'] == $user_id || $auth->getUserRole() === 'admin') { ?>
                <button class="flex-1 bg-gray-800 bg-opacity-50 text-white rounded-full py-2 px-6 mx-1" title="Edit <?= $user['first_name'] ?>&apos;s account" onclick="window.location.href='index.php?to=account&user_id=<?= $user['id'] ?>'">
                    <i class="fas fa-users"></i>
                </button>
                <?php 
                }
            ?>
        </script>
    <?php 
    } 
    ?>

    <section class="container mx-auto pt-12 px-4 sm:px-6 lg:px-8">
        <div class="absolute z-10 p-2 w-max">
            <div class="flex justify-full items-top">
                <div>
                    <img src="<?= $user['avatar'] ? $user['avatar'] : 'images/default_avatar.webp'; ?>" alt="<?= $user['first_name'] . ' ' . $user['last_name'] ?>" class="avatar-img-lg avatar-float-left ml-1 mr-4 <?= $auth->getUserPresence($user_id) ? 'userpresent' : 'userabsent'; ?> rounded-full object-cover w-12 h-12">
                </div>
                <div>
                    <h3 class="text-2xl font-bold">
                        <?= $user['first_name'] . ' ' . $user['last_name'] ?>
                    </h3>
                    <span><?= $user['email'] ?> <a href='mailto: <?= $user['email'] ?>' title='Send an email to <?= $user['first_name'] ?>' ><i class="fas fa-envelope text-xs text-gray-600"></i></a></span>
                </div>
            </div>
        </div>
        <div id="userDetails" class="pt-10">
            <div class="p-6 bg-white shadow-lg rounded-lg mt-8 h-64 overflow-y-auto">
            </div>
        </div>
    </section>