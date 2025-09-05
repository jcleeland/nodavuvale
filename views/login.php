<?php
// If the user is already logged in, redirect to the home page
if ($auth->isLoggedIn()) {
    ?>
    <script type="text/javascript">
        window.location.href = "index.php?to=home";
    </script>
    <?php
    exit;
}
$redirect = isset($_POST['redirect']) ? $_POST['redirect'] : false;
$redirectsection = isset($_POST['section']) ? $_POST['section'] : false;

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $redirection = isset($_POST['redirect']) ? $_POST['redirect'] : false;
    $redirectsection = isset($_POST['section']) ? $_POST['section'] : false;
    

    // Attempt to log the user in
    $login_result = $auth->login($email, $password);

    $error_class = 'text-red-500';

    if ($login_result === 'unapproved') {
        include("views/header.php");
        // User is registered but not approved yet
        $error_message = "<b>Not logged in!</b><br />Your registration is pending approval. Please wait for an administrator to approve your account.<br /><span class='italic text-xs'>In the meantime, feel free to <a href='?to=home' class='link'>browse our public areas</a></span>.";
    } elseif ($login_result === 'invalid') {
        include("views/header.php");
        // Invalid credentials
        $error_message = "<b>Not logged in!</b><br />Invalid email or password.<br /><a href='index.php?to=forgot_password' class='text-blue-500 hover:text-blue-700'>Forgot password?</a> or <a href='index.php?to=register' class='text-blue-500 hover:text-blue-700'>Register here</a>.";
    } else {
        // Redirect to the home page after successful login
        if($redirection) {
            //echo "Redirection: " . $redirection;
            //die();
            $redirecturl= $redirection;
            if($redirectsection) {
                $redirecturl .= "&section=" . urlencode($redirectsection);
            }
            header('Location: ' . "index.php?to=".$redirecturl);
            exit;
        }
        header('Location: index.php?to=home');
        exit;
    }
}

if (isset($_GET['justRegistered']) && $_GET['justRegistered'] == 1) {
    $error_class = 'text-green-500';

    $email = $_GET['login'];
    $error_message = "<b>Registration successful!</b><br />Your account has been created and an email has been sent to ".$email." for you to confirm. Please wait for an administrator to approve your account.<br /><span class='italic text-xs'>In the meantime, feel free to <a href='?to=home' class='link'>browse our public areas</a></span>.";
}
?>

<!-- HTML form for login -->
<section class="container mx-auto py-12">
    <div class="max-w-md mx-auto bg-white p-8 shadow-lg rounded-lg">
        <h2 class="text-2xl font-bold mb-6 text-center">Login to <i>Soli's Children</i></h2>

        <!-- Display error message if login fails or user is unapproved -->
        <?php if (isset($error_message)): ?>
            <div class="mb-4 <?= $error_class ?> text-center">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form action="index.php?to=login" method="POST">
            <input type="hidden" name="action" value="login" />
            <input type='hidden' name='redirect' value='<?= isset($redirect) ? htmlspecialchars($redirect) : ''; ?>' />
            <input type='hidden' name='section' value='<?= isset($redirectsection) ? htmlspecialchars($redirectsection) : ''; ?>' />
            <div class="mb-4">
                <label for="email" class="block text-gray-700">Email</label>
                <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-lg" required>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700">Password</label>
                <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-lg" required>
            </div>
            <div class="text-center">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Login
                </button>
            </div>
        </form>

        <!-- Links to register and forgot password -->
        <div class="text-center mt-4">
            <a href="index.php?to=forgot_password" class="text-blue-500 hover:text-blue-700">Forgot your password?</a> |
            <a href="index.php?to=register" class="text-blue-500 hover:text-blue-700">Register here</a>
        </div>
    </div>
</section>
