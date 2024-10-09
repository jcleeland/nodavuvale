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

// Handle form submission to update user information
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $relative_name = trim($_POST['relative_name']);
    $relationship = trim($_POST['relationship']);
    $email = trim($_POST['email']);
    
    // Admin-only fields
    $approved = isset($_POST['approved']) ? 1 : 0; // Checkbox, so value is either 1 or 0
    $role = $is_admin && isset($_POST['role']) ? $_POST['role'] : $user['role']; // Only allow admins to change role

    // Check if password reset is required
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    if (!empty($password)) {
        if ($password === $confirm_password) {
            // Hash the new password
            $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        } else {
            $error = "Passwords do not match.";
        }
    }

    // Validate fields (add more validation if needed)
    if (!isset($error) && !empty($first_name) && !empty($last_name) && !empty($relative_name) && !empty($relationship) && !empty($email)) {
        // Update the user's details in the database
        $params = [$first_name, $last_name, $relative_name, $relationship, $email, $approved, $role, $user_id];
        $sql = "UPDATE users SET first_name = ?, last_name = ?, relative_name = ?, relationship = ?, email = ?, approved = ?, role = ?";

        // Update password if provided
        if (!empty($password_hashed)) {
            $sql .= ", password = ?";
            $params[] = $password_hashed;
        }

        $sql .= " WHERE id = ?";
        echo $sql;
        print_r($params);
        try {
            $this->db->query(
                $sql, 
                $params
            );
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }        
        // Optionally redirect to avoid form resubmission
        header('Location: index.php?to=account' . ($is_admin ? "&user_id=$user_id" : ''));
        exit();
    }
}

?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold">Account Management</h2>
        <p class="mt-4 text-lg"><?= $is_admin && $user_id !== $_SESSION['user_id'] ? "Editing User: {$user['first_name']} {$user['last_name']}" : "Manage your personal details and account settings." ?></p>
    </div>
</section>

<!-- Account Information Section -->
<section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="bg-white shadow-lg rounded-lg p-6">
        <h3 class="text-2xl font-bold mb-4">Account Details</h3>
        
        <?php if (isset($error)): ?>
            <p class="text-red-500"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        
        <form action="index.php?to=account<?= $is_admin && $user_id !== $_SESSION['user_id'] ? "&user_id=$user_id" : '' ?>" method="POST">
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

            <!-- Relative Name -->
            <div class="mb-4">
                <label for="relative_name" class="block text-sm font-medium text-gray-700">Relative Name</label>
                <input type="text" name="relative_name" id="relative_name" value="<?= htmlspecialchars($user['relative_name']) ?>" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm" required>
            </div>

            <!-- Relationship -->
            <div class="mb-4">
                <label for="relationship" class="block text-sm font-medium text-gray-700">Relationship</label>
                <input type="text" name="relationship" id="relationship" value="<?= htmlspecialchars($user['relationship']) ?>" class="mt-1 p-2 block w-full border border-gray-300 rounded-md shadow-sm" required>
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
                    <label for="approved" class="block text-sm font-medium text-gray-700">Account Approved</label>
                    <input type="checkbox" name="approved" id="approved" value="1" <?= $user['approved'] ? 'checked' : '' ?> class="mt-1">
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
                <button type="submit" class="mt-4 bg-warm-red text-white py-2 px-4 rounded-lg hover:bg-burnt-orange">Update Account</button>
            </div>
        </form>
    </div>
</section
