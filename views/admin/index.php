<?php
// This is the administrators management page
// This page is only accessible to users with the 'admin' role
// If a user without the 'admin' role tries to access this page, they will be redirected to the home page
require 'vendor/autoload.php';
$admin_page = true;
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = $auth->getUserRole() === 'admin';

if (!$is_logged_in || !$is_admin) {
    header('Location: index.php?to=home');
    exit;
}


?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold"><i><?= $site_name ?></i> Administration</h2>
        <p class="mt-4 text-lg">Welcome, Administrator! Here you can manage your NodaVuvale site.</p>
    </div>
</section>

<!-- Admin Navigation for site settings, user management, family tree management and database management -->
<section class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="?to=admin/&section=site" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-center">Site Settings</a>
        <a href="?to=admin/&section=users" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-center">User Management</a>
        <a href="?to=admin/&section=familytree" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-center">Family Tree Management</a>
        <a href="?to=admin/&section=database" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-center">Database Management</a>
        <a href="?to=admin/FeedTest" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-center">Newsfeed Test</a>
    </div>
</section>

<?php
// Check if a section has been requested
if(!isset($admin_backload) ||!$admin_backload) {
    $section = isset($_GET['section']) ? $_GET['section'] : 'site';
    require_once('views/admin/' . $section . '.php');
} 
//$section = isset($_GET['section']) ? $_GET['section'] : 'site';
//load the section (add .php to the section name)

