<?php
/**
 * This page allows an administrator to list all the users on the site, and make changes to individual user settings
 * 
 */
//Gather tree folk
// Todo - see if the existing $individuals list can be reused to save a db call
$individuallist = $db->fetchAll("SELECT id, first_names, last_name FROM individuals ORDER BY last_name, first_names");
$individuals2=array();
foreach($individuallist as $individualperson) {
    $individuals2[$individualperson['id']] = $individualperson;
} 
?>
<script type='text/javascript'>
// Check to see if the const @individuals@ const already exists
if (typeof individuals === 'undefined') {
    const individuals = [
        <?php foreach($individuals2 as $ind): ?>
            { id: <?= $ind['id'] ?>, name: "<?= $ind['first_names'] . ' ' . $ind['last_name'] ?>" },
        <?php endforeach; ?>
    ];
}

</script>
<?php

//Gather a list of users
$sql = "SELECT * FROM users WHERE role != 'deleted' order by last_name, first_name";
$users = $db->fetchAll($sql);
?>
<script>
    window.usersData = <?php echo json_encode($users); ?>;
</script>
<section class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h3 class="text-4xl font-bold mb-6">User Management</h3>
    <div class="p-6 bg-white shadow-lg rounded-lg relative">
        <button class="absolute text-white bg-gray-800 bg-opacity-20 rounded-full py-1 px-2 m-0 -right-3 -top-3 z-10 font-normal text-lg" title="Add a user" onclick="addUser()">
            <i class="fas fa-plus"></i> <!-- FontAwesome icon -->
        </button>        
<?php
//Iterate through all the users & display them in a table
if(count($users) > 0) {
    echo '<table class="w-full border pb-8">';
    echo '<thead>';
    echo '<tr class="text-white bg-brown">';
    echo '<th class="border px-4 py-2">First Name</th>';
    echo '<th class="border px-4 py-2">Last Name</th>';
    echo '<th class="border px-4 py-2">Email</th>';
    echo '<th class="border px-4 py-2 sm:hidden">Last Login</th>';
    echo '<th class="border px-4 py-2">Role</th>';
    echo '<th class="border px-4 py-2">Approved</th>';
    echo '<th class="border px-4 py-2 sm:hidden">Tree Id</th>';
    echo '<th class="border px-4 py-2">Reset Password</th>';
    echo '<th class="border px-4 py-2">Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach($users as $user) {
        $lastlogin=!empty($user['last_login']) ? $web->timeSince($user['last_login']) : 'Never';
        echo '<tr id="user_'.$user['id'].'" class="bg-opacity-10 ';
        if($user['approved'] == 0) { echo "bg-red-700";} else {echo "bg-green-500";}
        echo '">';
        echo '<td class="border px-4 py-2">'.$user['first_name'].'</td>';
        echo '<td class="border px-4 py-2">'.$user['last_name'].'</td>';
        echo '<td class="border px-4 py-2">'.$user['email'].'</td>';
        echo '<td class="border px-4 py-2 sm:hidden">'.$lastlogin.'</td>';
        echo '<td class="border px-4 py-2">'.$user['role'].'</td>';
        echo '<td class="border px-4 py-2 text-center">';
        if($user['approved'] == 0) {
            echo ' <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded float" title="Grant user access" onclick="approveUser('.$user['id'].')">';
            echo ' <i class="fas fa-check"></i>';
            echo '</button>';
        } else {
            echo ' <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded float" title="Cancel user\'s access" onclick="approveUser('.$user['id'].', true)">'; 
            echo ' <i class="fas fa-times"></i>';
            echo '</button>';
        }
        echo '</td>';
        echo '<td class="border px-4 py-2 text-xs sm:hidden">';
        if(isset($individuals2[$user['individuals_id']])) {
            echo "<a href='?to=family/individual&individual_id=".$user['individuals_id']."'>";
            echo explode(" ",$individuals2[$user['individuals_id']]['first_names'])[0] . ' ' . $individuals2[$user['individuals_id']]['last_name'];
            echo "</a>";
        }
        echo '</td>';
        echo '<td class="border px-4 py-2 text-center">';
        echo '  <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded" title="Send user a password reset email" onclick="passwordReset('.$user['id'].')">'; 
        echo '<i class="fas fa-key"></i>';
        echo '</button>';
        echo '</td>';
        echo '<td class="border px-4 py-2 text-center flex flex-cols-3 gap-1">';
        echo '  <button class="text-xs bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded" title="Edit user details" onclick="editUser('.$user['id'].')">';
        echo '  <i class="fas fa-edit"></i>';
        echo '</button>';
        echo '  <button class="text-xs bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded" title="Send user a welcome and login password setup details" onclick="emailUserLoginDetails('.$user['id'].')">';
        echo '  <i class="fas fa-envelope"></i>';
        echo '</button>';
        echo '  <button class="text-xs bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded" title="Delete this user" onclick="deleteUser('.$user['id'].')">';
        echo '  <i class="fas fa-trash"></i>';
        echo '</button>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>&nbsp;';
} else {
    echo '<p>No users found</p>';
}
?>
    </div>
</section>

<!-- Modal for Editing User -->
<div id="editUserModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-lg sm:w-4/5 w-1/3">
        <h2 class="text-xl font-bold mb-4">Edit User</h2>
        <form id="editUserForm">
            <input type="hidden" id="editUserId" name="id">
            <div class="mb-4">
                <label for="editFirstName" class="block text-gray-700">First Name</label>
                <input type="text" id="editFirstName" name="first_name" class="w-full px-4 py-2 border rounded-lg">
            </div>
            <div class="mb-4">
                <label for="editLastName" class="block text-gray-700">Last Name</label>
                <input type="text" id="editLastName" name="last_name" class="w-full px-4 py-2 border rounded-lg">
            </div>
            <div class="mb-4">
                <label for="editEmail" class="block text-gray-700">Email</label>
                <input type="email" id="editEmail" name="email" class="w-full px-4 py-2 border rounded-lg">
            </div>
            <div class="mb-4">
                <label for="editRole" class="block text-gray-700">Role</label>
                <select id="editRole" name="role" class="w-full px-4 py-2 border rounded-lg">
                    <option value="admin">Admin</option>
                    <option value="member">Member</option>
                    <option value="unconfirmed">Unconfirmed</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="editApproved" class="block text-gray-700">Approved</label>
                <select id="editApproved" name="approved" class="w-full px-4 py-2 border rounded-lg">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
            <div class="mb-4" id="treeidlookup">
                <?php
                echo $web->showFindIndividualLookAhead($individuals, 'editIndividualsId', 'individuals_id');
                ?>
            </div>
            <div class="flex justify-end">
                <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded mr-2" onclick="closeEditUserModal()">Cancel</button>
                <button type="button" class="bg-blue-500 text-white px-4 py-2 rounded" onClick="updateUser()">Save</button>
            </div>
        </form>
    </div>
</div>


