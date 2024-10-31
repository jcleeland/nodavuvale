<?php
/**
 * --== NodaVuvale ==--
 * by Jason Cleeland / Aptigence
 * 
 * (c) 2024 Jason Cleeland, 47 Grove St, Eltham, Victoria, Australia
 * All Rights Reserved
 * 
 * This software is provided as-is, without any warranty or guarantee of any kind.
 */
session_name('nodavuvale_app_session');
session_start();

/**
 * Include the NodaVuvale classes.
 * 
 * @package NodaVuvale
 */
include('system/config.php');

/**
 * Include the Composer autoloader
 */
require 'vendor/autoload.php';

/**
 * Include the Global Village classes.
 * 
 * @package NodaVuvale
 */

// Mapping of class names to file paths
$classMap = [
    'Database' => __DIR__ . '/system/nodavuvale_database.php',
    'Auth' => __DIR__ . '/system/nodavuvale_auth.php',
    'Web' => __DIR__ . '/system/nodavuvale_web.php',
    'Utils' => __DIR__ . '/system/nodavuvale_utils.php',
    // Add other class mappings here
];

spl_autoload_register(function($class) use ($classMap) {
    if (isset($classMap[$class])) {
        require_once $classMap[$class];
    }
});

// Instantiate the necessary objects
$db = Database::getInstance();
$auth = new Auth($db);
$web = new Web($db);

$site_name=$db->getSiteSettings()['site_name'];
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';


// Define restricted directories
$restricted_paths = ['family', 'village', 'communications'];

// Get the requested page
$page = isset($_GET['to']) ? $_GET['to'] : 'home'; // Default to 'home' if no page is provided

//if the $page is set, but ends in a "/" then add index to the end of it
if (substr($page, -1) == "/") {
    $page .= "index";
}
// Construct the full path for the page to be included
$pagePath = 'views/' . $page . '.php';

// Extract the directory from the requested page (e.g., 'family' from 'views/family/tree.php')
$path_parts = explode('/', $page);
$requested_directory = $path_parts[0]; // Get the first part of the path (e.g., 'family')

// Check if the requested page is in a restricted directory and if the user is not logged in
if (in_array($requested_directory, $restricted_paths) && !$auth->isLoggedIn()) {
    // Redirect to login page if the user is not logged in
    header('Location: index.php?to=login');
    exit;
}

if(isset($_GET['action']) && $_GET['action'] == 'logout') {
    $auth->logout();
    header('Location: index.php');
    exit;
}

if((isset($_POST['action']) && $_POST['action'] == 'login') && (isset($_GET['to']) && $_GET['to'] == 'login')) {
    include("views/login.php");
    exit;
}

if((isset($_POST['action']) && $_POST['action'] == 'register' && (isset($_GET['to']) && $_GET['to'] == 'register'))) {
    include("views/register.php");
    exit;
}

// Include the header
include('views/header.php');

// Include the requested page if it exists, otherwise include the 404 page
if (file_exists($pagePath)) {
    //extract just the path from the $pagePath variable
    $sourcepath = pathinfo($pagePath);
    //extract just the filename from the $sourcepath variable and replace the .php with .js
    $jsfile = $sourcepath['filename'] . '.js';
    $jspath = $sourcepath['dirname'] . '/js/' . $jsfile;
    //echo "Looking for ".$jspath;
    //check if the .js file exists
    $jsload=null;
    if (file_exists($jspath)) {
        echo '<script src="'. $jspath . '"></script>';
    }
    include($pagePath);

} else {
    include('views/404.php');
}

// Include the footer
include('views/footer.php');
?>
