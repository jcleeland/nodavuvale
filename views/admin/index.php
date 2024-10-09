<?php
// This is the administrators management page
// This page is only accessible to users with the 'admin' role
// If a user without the 'admin' role tries to access this page, they will be redirected to the home page
require 'vendor/autoload.php';
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = $auth->getUserRole() === 'admin';

if (!$is_logged_in || !$is_admin) {
    header('Location: index.php?to=home');
    exit;
}

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the form data
    $site_name = $_POST['site_name'];
    $site_description = $_POST['site_description'];
    $email_server = $_POST['site_email_server'];
    $email_username = $_POST['email_username'];
    $email_password = $_POST['email_password'];
    $email_port = $_POST['email_port'];

    // Update the site settings in the database
    $db->updateSiteSettings($site_name, $site_description, $email_server, $email_username, $email_password, $email_port);
}
?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold"><i>Soli's Children</i> Administration</h2>
        <p class="mt-4 text-lg">Welcome, Administrator! Here you can manage users and other site content.</p>
    </div>
</section>

<section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <h1 class="text-4xl font-bold mb-6">Site Management</h1>
<!-- A form that shows the current settings for *Site Name, *Site Email Server details, -->
<!-- *Site Email Address, *Site Email Password, *Site Email Port, *Site Email Encryption, *Site Email From Name, *Site Email From Address, *Site Email Reply To Address, *Site Email Reply To Name, *Site Email BCC Address, *Site Email BCC Name, *Site Email CC Address, *Site Email CC Name, *Site Email Signature, *Site Email Footer, *Site Email Header, *Site Email Subject Prefix, *Site Email Subject Suffix, *Site Email Subject Separator, *Site Email Subject Separator Position, *Site Email Subject Separator Style, *Site Email Subject Separator Color, *Site Email Subject Separator Size, *Site Email Subject Separator Weight, *Site Email Subject Separator Alignment, *Site Email Subject Separator Margin, *Site Email Subject Separator Padding, *Site Email Subject Separator Border, *Site Email Subject Separator Border Style, *Site Email Subject Separator Border Color, *Site Email Subject Separator Border Size, *Site Email Subject Separator Border Radius, *Site Email Subject Separator Border Margin, *Site Email Subject Separator Border Padding, *Site Email Subject Separator Border Alignment, *Site Email Subject Separator Border Display, *Site Email Subject Separator Border Position, *Site Email Subject Separator Border Collapse, *Site Email Subject Separator Border Spacing, *Site Email Subject Separator Border Caption, *Site Email Subject Separator Border Caption Side, *Site Email Subject Separator Border Caption Align, *Site Email Subject Separator Border Caption Width, *Site Email Subject Separator Border Caption Height, *Site Email Subject Separator Border Caption Margin, *Site Email Subject Separator Border Caption Padding, *Site Email Subject Separator Border Caption Border, *Site Email Subject Separator Border Caption Border Style, *Site Email Subject Separator Border Caption Border Color, *Site Email Subject Separator Border Caption Border Size, *Site Email Subject Separator Border Caption Border Radius, *Site Email Subject Separator Border Caption Border Margin, *Site Email Subject Separator Border Caption Border Padding, *Site Email Subject Separator Border Caption Border Alignment, *Site Email Subject Separator Border Caption Border Display, *Site Email Subject Separator Border Caption Border Position, *Site Email Subject Separator Border Caption Border Collapse, *Site Email Subject Separator Border Caption Border Spacing, *Site Email Subject Separator Border Caption Border Caption, *Site Email Subject Separator Border Caption Border Caption Side, *Site Email Subject Separator Border Caption Border Caption Align, *Site Email Subject Separator Border Caption Border Caption Width, *Site Email Subject Separator Border Caption Border Caption Height, *Site Email Subject -->
<?php
//Get the site settings from the database
$site_settings = $db->getSiteSettings();
?>
    <form action="index.php?to=admin/" method="POST">
        <div class="mb-4">
            <label for="site_name" class="block text-gray-700">Site Name</label>
            <input type="text" id="site_name" name="site_name" class="w-full px-4 py-2 border rounded-lg" value="<?php echo $site_settings['site_name']; ?>" required>
        </div>
        <div class="mb-4">
            <label for="site_description" class="block text-gray-700">Site Description</label>
            <input type="text" id="site_description" name="site_description" class="w-full px-4 py-2 border rounded-lg" value="<?php echo $site_settings['site_description']; ?>" required>
        </div>
        <div class="mb-4">
            <label for="email_server" class="block text-gray-700">Site Email Server</label>
            <input type="text" id="email_server" name="site_email_server" class="w-full px-4 py-2 border rounded-lg" value="<?php echo $site_settings['email_server']; ?>" required>
        </div>
        <div class="mb-4">
            <label for="email_username" class="block text-gray-700">Site Email Username</label>
            <input type="email" id="email_address" name="email_username" class="w-full px-4 py-2 border rounded-lg" value="<?php echo $site_settings['email_username']; ?>" required>
        </div>
        <div class="mb-4">
            <label for="email_password" class="block text-gray-700">Site Email Password</label>
            <input type="password" id="email_password" name="email_password" class="w-full px-4 py-2 border rounded-lg" value="<?php echo $site_settings['email_password']; ?>" required>
        </div>
        <div class="mb-4">
            <label for="email_port" class="block text-gray-700">Site Email Port</label>
            <input type="number" id="email_port" name="email_port" class="w-full px-4 py-2 border rounded-lg" value="<?php echo $site_settings['email_port']; ?>" required>  
        </div>
        <!-- Submit button -->
        <div class="text-center">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Save Settings
            </button>
        </div>
</section>
