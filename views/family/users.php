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

        </div>
    </div>
</section>

<?php
include("helpers/user.php");
?>

    <section class="container mx-auto pt-12 px-4 sm:px-6 lg:px-8">
        <div id="userlist" class="relative pt-6 xs:pt-1">
            <div class="p-6 bg-white shadow-lg rounded-lg mt-8 h-64 overflow-y-auto">
            <?php foreach($users as $user): ?>
                <div class="flex justify-between items-center border-b border-gray-200 py-2 cursor-pointer" onClick="window.location='?to=family/users&user_id=<?= $user['id'] ?>&tab=membertab'">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gray-300 rounded-full flex items-center justify-center mr-4">
                            <img src="<?= $user['avatar'] ? $user['avatar'] : "images/default_avatar.webp" ?>" alt="<?= $user['first_name'] . ' ' . $user['last_name'] ?>" class="rounded-full object-cover w-12 h-12">
                        </div>
                        <div class="w-64">
                            <h3 class="text-lg font-bold"><?= $user['first_name'] . ' ' . $user['last_name'] ?></h3>
                            <p class="text-sm text-gray-600"><?= $user['email'] ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </section>
