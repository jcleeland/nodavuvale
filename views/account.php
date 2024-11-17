<?php
// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header('Location: index.php?to=login');
    exit();
}

// Get the logged-in user's details
$logged_in_user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

// Determine if the user is an admin
$is_admin = $logged_in_user['role'] === 'admin';

// Determine which user to edit (logged-in user or specified by admin)
if ($is_admin && isset($_GET['user_id'])) {
    // Admin is editing another user's account
    $user_id = (int)$_GET['user_id'];
} else {
    // Non-admin can only edit their own account
    $user_id = $_SESSION['user_id'];
}

// Fetch the user’s account details to be edited
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);

$privacy_settings = Utils::getPrivacySettings($user_id, null);

//Fetch the list of family tree individuals for the select dropdown
$individuals = Utils::getIndividualsList();

// Check if the standard form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accountUpdate'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    //$relative_name = trim($_POST['relative_name']);
    //$relationship = trim($_POST['relationship']);
    $email = trim($_POST['email']);
    $individuals_id = trim($_POST['individuals_id']);
    $show_presence = isset($_POST['show_presence']) ? 1 : 0;
    
    // Admin-only fields
    $approved = isset($_POST['approved']) ? 1 : 0;
    $role = $is_admin && isset($_POST['role']) ? $_POST['role'] : $user['role'];

    // Check if password reset is required
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    if (!empty($password)) {
        if ($password === $confirm_password) {
            $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        } else {
            $error = "Passwords do not match.";
        }
    }

    // Validate other fields
    if (!isset($error) && !empty($first_name) && !empty($last_name) && !empty($email)) {
        // Process avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/avatars/';
            $avatar_file_name = $user_id . '_' . basename($_FILES['avatar']['name']);
            $file_path = $upload_dir . $avatar_file_name;
            $file_format = pathinfo($file_path, PATHINFO_EXTENSION);

            // Validate file type
            $allowed_formats = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array(strtolower($file_format), $allowed_formats)) {
                $error = "Invalid file format. Allowed formats: jpg, jpeg, png, gif.";
            } else {
                // Move the uploaded file to the target directory
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $file_path)) {
                    // Update avatar path in the database
                    $avatar = $file_path;
                } else {
                    $error = "Failed to upload avatar.";
                }
            }
        }

        // Update the user's details in the database
        $params = [$first_name, $last_name, $individuals_id, $show_presence, $email, $approved, $role];
        $sql = "UPDATE users 
                SET first_name = ?, last_name = ?, 
                    individuals_id = ?,
                    show_presence = ?, 
                    email = ?, approved = ?, `role` = ?";

        // Add the avatar if uploaded
        if (isset($avatar)) {
            $sql .= ", avatar = ?";
            $params[] = $avatar;
        }

        // Update password if provided
        if (!empty($password_hashed)) {
            $sql .= ", password = ?";
            $params[] = $password_hashed;
        }

        $sql .= " WHERE id = ?";
        $params[] = $user_id;

        // Function to safely escape and quote parameters
        /*function quote($value) {
            return "'" . addslashes($value) . "'";
        }*/

        // Replace placeholders with actual values
        /*foreach ($params as $param) {
            $fullsql = preg_replace('/\?/', quote($param), $sql, 1);
        }*/

        try {
            $db->query($sql, $params);
        } catch (Exception $e) {
            error_log($e->getMessage());
            $error = "Failed to update account.";
        }

        // Optionally redirect to avoid form resubmission
        if (!isset($error)) {
            //echo $fullsql; die();
            ?>
            <script type="text/javascript">
                window.location.href = "index.php?to=account<?= ($is_admin) ? "&user_id=$user_id" : "" ?>";
            </script>
            <?php            
            exit();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accountPrivacyUpdate'])) {

    $update_user_id=trim($_POST['user_id']);
    $update_individual_id=trim($_POST['individual_id']);
    //First delete all the current privacy settings for this user
    $db->query("DELETE FROM individuals_privacy WHERE user_id = ? OR individual_id = ?", [$update_user_id, $update_individual_id]);
    
    foreach ($privacy_settings as $key => $value) {
        $newvalue = isset($_POST[$key]) ? 1 : 0;
        //echo $key . ' => ' . $value . ' -> '.$newvalue .'<br>';
        $db->query("INSERT INTO individuals_privacy (user_id, individual_id, privacy_label, public) VALUES (?, ?, ?, ?)", [$update_user_id, $update_individual_id, $key, $newvalue]);
        //echo "INSERT INTO individuals_privacy (user_id, individual_id, privacy_label, public) VALUES ($update_user_id, $update_individual_id, $key, $value)<br /><br />";
        //Now reload the privacy settings
        $privacy_settings = Utils::getPrivacySettings($update_user_id, $update_individual_id);
    }

    // Update the privacy settings in the database
    //$db->query("UPDATE privacy_settings SET value = ? WHERE user_id = ? AND setting = ?", [$value, $user_id, $key]);
}

$avatar_path = $user['avatar'] ?? 'uploads/avatars/default-avatar.png';

?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold">Account Management</h2>
        <p class="mt-4 text-lg"><?= $is_admin && $user_id !== $_SESSION['user_id'] ? "Editing User: {$user['first_name']} {$user['last_name']}" : "Manage your personal details and account settings." ?></p>
    </div>
    <div class="tabs absolute -bottom-0 text-sm md:text-lg gap-2">
        <div class="tab active px-4 py-2" data-tab="generaltab" title="Change your ">General</div>
        <div class="tab px-4 py-2" data-tab="privacytab" title="Adjust your privacy settings">Privacy</div>

    </div>

</section>
<!-- Account Information Section -->
<div class="tab-content active" id="generaltab">
    <section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h3 class="text-2xl font-bold mb-4">Account Details</h3>
            
            <?php if (isset($error)): ?>
                <p class="text-red-500"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            
            <form action="index.php?to=account<?= $is_admin && $user_id !== $_SESSION['user_id'] ? "&user_id=$user_id" : '' ?>" method="POST" enctype="multipart/form-data">
                <!-- First Name -->
                <div class="mb-4">
                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm" required>
                </div>

                <!-- Last Name -->
                <div class="mb-4">
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm" required>
                </div>

                <!-- Family Tree Individual ID -->
                <div class="mb-4">
                    <label for="individuals_id" class="block text-sm font-medium text-gray-700">Family Tree Connection</label>
                    <select name="individuals_id" id="individuals_id" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm">
                        <option value="">Select an individual</option>
                        <?php foreach ($individuals as $individual): ?>
                            <option value="<?= $individual['id'] ?>" <?= $user['individuals_id'] == $individual['id'] ? 'selected' : '' ?>><?= htmlspecialchars($individual['first_names'].' '.$individual['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Avatar Upload -->
                <div>
                    <label for="avatar" class="block text-sm font-medium text-gray-700 cursor-pointer">Upload Avatar</label>
                    <div class="ml-1 mb-4 flex flex-cols-2 w-full">
                        <div>
                            <img src="<?= htmlspecialchars($avatar_path) ?>" alt="User Avatar" class="avatar-img-lg object-cover"></label>
                        </div>
                        <div>
                            
                            <input type="file" name="avatar" id="avatar" accept="image/*" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm">

                        </div>
                    </div>
                </div>
                <!-- Show presence to others -->
                <div class="mb-4">
                    <input type="checkbox" name="show_presence" id="show_presence" value="1" <?= $user['show_presence'] ? 'checked' : '' ?> class="mt-1 inline">
                    <label for="show_presence" class="block text-sm font-medium text-gray-700 inline">Show presence on <i><?= $site_name ?></i> to others</label>
                </div>
                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm" required>
                </div>

                <!-- Password (Optional) -->
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700">New Password (Leave blank to keep current password)</label>
                    <input type="password" name="password" id="password" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm">
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm">
                </div>

                <?php if ($is_admin): ?>
                    <!-- Admin-only fields -->

                    <!-- Account Approval -->
                    <div class="mb-4">
                        <input type="checkbox" name="approved" id="approved" value="1" <?= $user['approved'] ? 'checked' : '' ?> class="mt-1 inline">
                        <label for="approved" class="block text-sm font-medium text-gray-700 inline">Account Approved</label>
                    </div>

                    <!-- Role Selection -->
                    <div class="mb-4">
                        <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" id="role" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm">
                            <option value="unconfirmed" <?= $user['role'] === 'unconfirmed' ? 'selected' : '' ?>>Unconfirmed</option>
                            <option value="member" <?= $user['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" name="accountUpdate" class="mt-4 bg-warm-red text-white py-2 px-4 rounded-lg hover:bg-burnt-orange">Update Account</button>
                </div>
            </form>
        </div>
    </section>
</div>




<div class="tab-content" id="privacytab">
    <section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h3 class="text-2xl font-bold mb-4">Privacy Settings</h3>
            <div class="text-sm text-gray-600 mb-4">
                <p>Adjust your privacy settings to control how your information is shared with others.</p>
            </div>
            <form action="index.php?to=account<?= $is_admin && $user_id !== $_SESSION['user_id'] ? "&user_id=$user_id" : '' ?>" method="POST">
                <input type='hidden' name='user_id' value='<?= $user_id ?>'>
                <input type='hidden' name='individual_id' value='<?= $user['individuals_id'] ?>'>
                <div class="mb-4 mt-4">
                    <h3 class="text-xl font-bold mb-4">General Privacy Settings</h3>
                    <div class="text-sm text-gray-600 mb-4">
                        <p>Because this is a family site and we have a general basic level of trust in each other, the default settings
                            allow others to see your name, date of birth, and other basic information. But if you're not comfortable with that, you can change it here.</p>
                    </div>
                    <h3 class="text-md font-bold mb-2 ml-4">Your user/login information</h3>
                    <div class="ml-6">
                        <input type="checkbox" name="users_show_presence" id="users_show_presence" value="1" <?= $privacy_settings['users_show_presence'] ? 'checked' : '' ?> class="mt-1 inline">
                        <label for="users_show_presence" class="block text-sm font-medium text-gray-700 inline">Show presence on <i><?= $site_name ?></i> to others</label>
                    </div>
                    <div class="ml-6">
                        <input type="checkbox" name="users_email" id="users_email" value="1" <?= $privacy_settings['users_email'] ? 'checked' : '' ?> class="mt-1 inline">
                        <label for="users_email" class="block text-sm font-medium text-gray-700 inline">Show email address to others</label>
                    </div>
                    <h3 class="text-md font-bold mb-2 mt-2 ml-2">Your basic family tree information</h3>
                    <div class="ml-6">
                        <input type="checkbox" name="individuals_birthdate" id="individual_birthdate" value="1" <?= $privacy_settings['individuals_birthdate'] ? 'checked' : '' ?> class="mt-1 inline">
                        <label for="individuals_birthdate" class="block text-sm font-medium text-gray-700 inline">Show month and date of birth to others (if unchecked, only your year of birth will be shown)</label>
                    </div>
                    <div class="ml-6">
                        <input type="checkbox" name="individuals_first_names" id="individual_first_names" value="1" <?= $privacy_settings['individuals_first_names'] ? 'checked' : '' ?> class="mt-1 inline">
                        <label for="individuals_first_names" class="block text-sm font-medium text-gray-700 inline">Show your first name(s) to others</label>
                    </div>
                    <div class="ml-6">
                        <input type="checkbox" name="individuals_last_name" id="individuals_last_name" value="1" <?= $privacy_settings['individuals_last_name'] ? 'checked' : '' ?> class="mt-1 inline">
                        <label for="individuals_last_name" class="block text-sm font-medium text-gray-700 inline">Show your last name to others</label>
                    </div>
                </div>
                <div class="mb-4 mt-4">
                    <h3 class="text-xl font-bold mb-4">Stories about you</h3>
                    <div class="text-sm text-gray-600 mb-4">
                        <p class="mb-1">One of the things we want to do in this site, is provide a place where we can all add stories about our departed ancestors; from funny anecdotes or things they did, to grand adventures they undertook and experiences they had.</p>
                        <p class="mb-1">By default, however, we turn off the option for others to write stories about you, because we understand that these are your stories to tell.</p>
                        <p>So you can always add stories about yourself for others to see, but others can't. If you're happy to let others write stories about you, turn this option on.</p>
                    </div>
                    <div class="ml-4">
                        <input type="checkbox" name="discussions_others_to_write_stories" id="discussions_others_to_write_stories" value="1" <?= $privacy_settings['discussions_others_to_write_stories'] ? 'checked' : '' ?> class="mt-1 inline">
                        <label for="discussions_others_to_write_stories" class="block text-sm font-medium text-gray-700 inline">Allow others to add stories to your tree record</label>
                    </div>
                </div>
                <div class="mb-4 mt-4">
                    <h3 class="text-xl font-bold mb-4">Family Tree Events & Facts Visibility</h3>
                    <div class="text-sm text-gray-600 mb-4">
                    <p class="mb-1">Information, such as that listed below, might be sensitive</p>
                    <p class="mb-1">By default, we hide these things from other users if you're alive. But if you're happy to share some or all of your facts, you can do so here.</p>
                    </div>
                    <?php foreach($privacy_settings as $grouplabel=>$value): ?>
                        <?php if(substr($grouplabel, 0, 6) == 'items_'): ?>
                            <?php $group=substr($grouplabel, 6); ?>
                            <div class="ml-4">
                                <input type="checkbox" name="<?= $grouplabel ?>" id="<?= $grouplabel ?>" value="1" class="mt-1 inline" <?= $privacy_settings[$grouplabel] ? 'checked' : '' ?> >
                                <label for="<?= $grouplabel ?>" class="block text-sm font-medium text-gray-700 inline">Show <?= $group ?> items to others</label>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" name="accountPrivacyUpdate" class="mt-4 bg-warm-red text-white py-2 px-4 rounded-lg hover:bg-burnt-orange">Update Privacy Settings</button>
                </div>
            </form>
        </div>
    </section>
</div>
