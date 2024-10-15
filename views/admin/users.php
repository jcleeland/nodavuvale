<?php
/**
 * This page allows an administrator to list all the users on the site, and make changes to individual user settings
 * 
 */
//Gather a list of users

$sql = "SELECT * FROM users order by last_name, first_name";
$users = $db->fetchAll($sql);
?>
<section class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h1 class="text-4xl font-bold mb-6">Site Management</h1>
    <div class="p-6 bg-white shadow-lg rounded-lg">
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
    echo '<th class="border px-4 py-2">Change Password</th>';
    echo '<th class="border px-4 py-2">Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach($users as $user) {
        echo '<tr>';
        echo '<td class="border px-4 py-2">'.$user['first_name'].'</td>';
        echo '<td class="border px-4 py-2">'.$user['last_name'].'</td>';
        echo '<td class="border px-4 py-2">'.$user['email'].'</td>';
        echo '<td class="border px-4 py-2">'.$user['role'].'</td>';
        echo '<td class="border px-4 py-2">'.$user['approved'].'</td>';
        echo '<td class="border px-4 py-2 text-center"><button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">PW</button></td>';
        echo '<td class="border px-4 py-2"><a href="index.php?to=admin/users/'.$user['id'].'">Edit</a></td>';
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

