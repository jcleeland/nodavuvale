<?php
// If the user is already logged in, redirect to the home page
if ($auth->isLoggedIn()) {
    header('Location: index.php?to=home');
    exit;
}

//Set variables
$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$email = $_POST['email'] ?? '';
$relative_name = $_POST['relative_name'] ?? '';
$relationship = $_POST['relationship'] ?? '';
$role = "unconfirmed";
$approved = 0;

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate form input
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($password) && !empty($relative_name) && !empty($relationship) && !empty($first_name) && !empty($last_name)) {
        // Check if the passwords match
        if ($password === $confirm_password) {
            // Attempt to register the user
            if ($auth->register($first_name, $last_name, $email, $password, $relative_name, $relationship, $role, $approved)) {
                // Redirect to login page after successful registration
                header('Location: index.php?to=login&justRegistered=1&login=' . $email);
                exit;
            } else {
                die();
                // If registration fails, display an error message
                $error_message = "Registration failed. This email might already be registered.";
            }
        } else {
            // If passwords don't match, display an error message
            $error_message = "Passwords do not match.";
        }
    } else {
        $error_message = "Please ensure all fields are correctly filled out.";
    }
}
?>

<!-- HTML form for registration -->
<section class="container mx-auto py-12">
    <div class="max-w-md mx-auto bg-white p-8 shadow-lg rounded-lg">
        <h2 class="text-2xl font-bold mb-6 text-center">Register for <i>Soli's Children</i></h2>

        <!-- Display error message if registration fails -->
        <?php if (isset($error_message)): ?>
            <div class="mb-4 text-red-500 text-center">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form action="index.php?to=register" method="POST">
        <input type="hidden" name="action" value="register" />
            <!-- First Name field -->
            <div class="mb-4">
                <label for="first_name" class="block text-gray-700">First Name</label>
                <input type="text" id="first_name" name="first_name" class="w-full px-4 py-2 border rounded-lg" required value="<?= $first_name ?>">
            </div>

            <!-- Last Name field -->
            <div class="mb-4">
                <label for="last_name" class="block text-gray-700">Last Name</label>
                <input type="text" id="last_name" name="last_name" class="w-full px-4 py-2 border rounded-lg" required value="<?= $last_name ?>">
            </div>

            <!-- Email (username) field -->
            <div class="mb-4">
                <label for="email" class="block text-gray-700">Email (will be your username)</label>
                <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-lg" required value="<?= $email ?>">
            </div>

            <!-- Password field -->
            <div class="mb-4">
                <label for="password" class="block text-gray-700">Password</label>
                <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-lg" required>
            </div>

            <!-- Confirm Password field -->
            <div class="mb-6">
                <label for="confirm_password" class="block text-gray-700">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="w-full px-4 py-2 border rounded-lg" required>
            </div>

            <!-- Instructional note -->
            <p class="text-gray-600 text-center mb-6 text-sm">
                You must be connected to Nataleira Village. Relationships are how we establish this connection. Please provide the name of a relative who is confirmed as connected to this village.
            </p>

            <!-- Relative's Name field -->
            <div class="mb-4">
                <label for="relative_name" class="block text-gray-700">Relative's Name</label>
                <input type="text" id="relative_name" name="relative_name" class="w-full px-4 py-2 border rounded-lg" required value="<?= $relative_name ?>">
            </div>

            <!-- Relationship field -->
            <div class="mb-4">
                <label for="relationship" class="block text-gray-700">Relationship to You</label>
                <input type="text" id="relationship" name="relationship" class="w-full px-4 py-2 border rounded-lg" required value="<?= $relationship ?>">
            </div>



            <div class="text-center">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Register
                </button>
            </div>
        </form>
    </div>
</section>
