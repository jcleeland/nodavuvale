<?php
//Check to see if the parent admin page has loaded, and if not then "require" it first
if (!isset($admin_page) || !$admin_page) {
    $admin_backload=true;
    require_once('views/admin/index.php');
}
//Gather tree folk
$individuallist = $db->fetchAll("SELECT id, first_names, last_name FROM individuals ORDER BY last_name, first_names");
$individuals2=array();
foreach($individuallist as $individualperson) {
    $individuals2[$individualperson['id']] = $individualperson;
} 
?>
<script type='text/javascript'>
if (typeof individuals === 'undefined') {
    const individuals = [
        <?php foreach($individuallist as $ind): ?>
            { id: <?= $ind['id'] ?>, name: "<?= $ind['first_names'] . ' ' . $ind['last_name'] ?>" },
        <?php endforeach; ?>
    ];
}
</script>
<?php
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
      <i class="fas fa-plus"></i>
    </button>        

<?php if(count($users) > 0): ?>
  <table class="min-w-full border pb-8">
    <thead>
      <tr class="text-white bg-brown">
        <th class="border px-4 py-2">Name</th>
        <th class="border px-4 py-2 hidden sm:table-cell">Email</th>
        <th class="border px-4 py-2 hidden md:table-cell">Last Login</th>
        <th class="border px-4 py-2 hidden md:table-cell">Role</th>
        <th class="border px-4 py-2 hidden lg:table-cell">Tree Id</th>
        <th class="border px-4 py-2 text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($users as $user): 
        $lastlogin=!empty($user['last_login']) ? $web->timeSince($user['last_login']) : 'Never'; ?>
        <tr id="user_<?= $user['id'] ?>" class="bg-opacity-10 <?= $user['approved']==0 ? 'bg-red-700' : 'bg-green-500' ?>">
          <!-- Name column (always visible) -->
          <td class="border px-4 py-2">
            <div class="font-semibold"><?= $user['first_name'].' '.$user['last_name'] ?></div>
            <!-- Stacked details on small screens -->
            <div class="sm:hidden text-xs text-gray-700"><?= $user['email'] ?></div>
            <div class="md:hidden text-xs text-gray-500">Role: <?= $user['role'] ?></div>
            <div class="md:hidden text-xs text-gray-500">Login: <?= $lastlogin ?></div>
          </td>

          <!-- Hidden on small, visible on larger screens -->
          <td class="border px-4 py-2 hidden sm:table-cell"><?= $user['email'] ?></td>
          <td class="border px-4 py-2 hidden md:table-cell text-xs"><?= $lastlogin ?></td>
          <td class="border px-4 py-2 hidden md:table-cell text-xs"><?= $user['role'] ?></td>
          <td class="border px-4 py-2 hidden lg:table-cell text-xs">
            <?php if(isset($individuals2[$user['individuals_id']])): ?>
              <a href="?to=family/individual&individual_id=<?= $user['individuals_id'] ?>">
                <?= explode(" ",$individuals2[$user['individuals_id']]['first_names'])[0] . ' ' . $individuals2[$user['individuals_id']]['last_name'] ?>
              </a>
            <?php endif; ?>
          </td>

          <!-- Actions dropdown -->
          <td class="border px-4 py-2 text-center">
            <div class="relative inline-block text-left">
              <button onclick="toggleDropdown(<?= $user['id'] ?>)" 
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                <span>Actions</span>
                <svg class="ml-2 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M5.25 7l4.5 4.5L14.25 7z"/></svg>
              </button>

              <div id="dropdown-<?= $user['id'] ?>" 
                   class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 hidden z-20">
                <div class="py-1">
                  <?php if($user['approved'] == 0): ?>
                    <a href="#" onclick="approveUser(<?= $user['id'] ?>)" 
                       class="block px-4 py-2 text-sm text-green-600 hover:bg-gray-100">
                      <i class="fas fa-check"></i> Approve
                    </a>
                  <?php else: ?>
                    <a href="#" onclick="approveUser(<?= $user['id'] ?>, true)" 
                       class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                      <i class="fas fa-times"></i> Unapprove
                    </a>
                  <?php endif; ?>

                  <a href="#" onclick="passwordReset(<?= $user['id'] ?>)" 
                     class="block px-4 py-2 text-sm text-blue-600 hover:bg-gray-100">
                    <i class="fas fa-key"></i> Reset Password
                  </a>

                  <a href="#" onclick="editUser(<?= $user['id'] ?>)" 
                     class="block px-4 py-2 text-sm text-yellow-600 hover:bg-gray-100">
                    <i class="fas fa-edit"></i> Edit
                  </a>

                  <a href="#" onclick="emailUserLoginDetails(<?= $user['id'] ?>)" 
                     class="block px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
                    <i class="fas fa-envelope"></i> Send Login Details
                  </a>

                  <a href="#" onclick="deleteUser(<?= $user['id'] ?>)" 
                     class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                    <i class="fas fa-trash"></i> Delete
                  </a>
                </div>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <p>No users found</p>
<?php endif; ?>
  </div>
</section>

<!-- Modal for Editing User -->
<div id="editUserModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 hidden z-10">
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
        <?php echo $web->showFindIndividualLookAhead($individuals, 'editIndividualsId', 'individuals_id'); ?>
      </div>
      <div class="flex justify-end">
        <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded mr-2" onclick="closeEditUserModal()">Cancel</button>
        <button type="button" class="bg-blue-500 text-white px-4 py-2 rounded" onClick="updateUser()">Save</button>
      </div>
    </form>
  </div>
</div>
