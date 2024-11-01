<?php
/**
 * This page allows an administrator to list all the users on the site, and make changes to individual user settings
 * 
 */
//Gather tree folk
$individuallist = $db->fetchAll("SELECT id, first_names, last_name FROM individuals ORDER BY last_name, first_names");
$individuals=array();
foreach($individuallist as $individualperson) {
    $individuals[$individualperson['id']] = $individualperson;
}
?>
<script type='text/javascript'>
const individuals = [
    <?php foreach($individuals as $ind): ?>
        { id: <?= $ind['id'] ?>, name: "<?= $ind['first_names'] . ' ' . $ind['last_name'] ?>" },
    <?php endforeach; ?>
];
</script>
<?php

//Gather a list of users
$sql = "SELECT * FROM users order by last_name, first_name";
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
    echo '<tr>';
    echo '<th class="border px-4 py-2">First Name</th>';
    echo '<th class="border px-4 py-2">Last Name</th>';
    echo '<th class="border px-4 py-2">Email</th>';
    echo '<th class="border px-4 py-2">Role</th>';
    echo '<th class="border px-4 py-2">Approved</th>';
    echo '<th class="border px-4 py-2">Tree Id</th>';
    echo '<th class="border px-4 py-2">Change Password</th>';
    echo '<th class="border px-4 py-2">Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach($users as $user) {
        echo '<tr id="user_'.$user['id'].'">';
        echo '<td class="border px-4 py-2">'.$user['first_name'].'</td>';
        echo '<td class="border px-4 py-2">'.$user['last_name'].'</td>';
        echo '<td class="border px-4 py-2">'.$user['email'].'</td>';
        echo '<td class="border px-4 py-2">'.$user['role'].'</td>';
        echo '<td class="border px-4 py-2 text-center">';
        if($user['approved'] == 0) {
            echo ' <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded float" onclick="approveUser('.$user['id'].')">Approve</button>';
        } else {
            echo ' <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded float" onclick="approveUser('.$user['id'].', true)">Unapprove</button>';
        }
        echo '</td>';
        echo '<td class="border px-4 py-2">'.$user['individuals_id'].'</td>';
        echo '<td class="border px-4 py-2 text-center"><button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">PW</button></td>';
        echo '<td class="border px-4 py-2"><button class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded" onclick="editUser('.$user['id'].')">Edit</button></td>';
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
    <div class="bg-white p-6 rounded-lg shadow-lg w-1/3">
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


