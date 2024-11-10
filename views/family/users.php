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
if(isset($_GET['user_id'])) {
    $descendancy = [];
    if($user_id) {
        $descendancy=Utils::getLineOfDescendancy(Web::getRootId(), $user_id);
    } 
    if($descendancy): ?>
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
    <?php endif;

    include("helpers/user.php");
?>


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