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
//session_name('NODAVUVALESESSID');
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

// Ensure the uploads directory exists
if (!is_dir('uploads/discussions')) {
    mkdir('uploads/discussions', 0777, true);
}

// Define restricted directories
$restricted_paths = ['family', 'village', 'communications', 'admin'];

// Get the requested page
$page = isset($_GET['to']) ? $_GET['to'] : 'home'; // Default to 'home' if no page is provided
$original_page = $page;
$section = isset($_GET['section']) ? $_GET['section'] : null;

//if the $page is set, but ends in a "/" then add index to the end of it
if (substr($page, -1) == "/") {
    $page .= "index";
}
// Construct the full path for the page to be included
$pagePath = 'views/' . $page . '.php';

// Extract the directory from the requested page (e.g., 'family' from 'views/family/tree.php')
$path_parts = explode('/', $page);
$requested_directory = $path_parts[0]; // Get the first part of the path (e.g., 'family')
// If there are two forward slashes in the path, then the requested directory is the the first AND second parts
if (count($path_parts) > 1) {
    $requested_directory = $path_parts[0] . '/' . $path_parts[1];
}

//Check to see if the start of any value in $requested_directory matches any value in $restricted_paths
$matches = array_filter($restricted_paths, function($path) use ($requested_directory) {
    return strpos($requested_directory, $path) === 0;
});

// If there are any matches, and the user is not logged in, redirect to login
if (!empty($matches) && !$auth->isLoggedIn()) {
    $params = "?to=login";
    if($original_page) {
        $params .= "&redirect=" . urlencode($original_page);
    }
    if($section) {
        $params .= "&section=" . urlencode($section);
    }
    header('Location: index.php' . $params);
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

$individuals=array();
// If the user is logged in, fill the array with individuals
if ($auth->isLoggedIn()) {
    $individuals = $db->fetchAll("SELECT individuals.*, 
                                    COALESCE(
                                        (SELECT files.file_path 
                                            FROM file_links 
                                            JOIN files ON file_links.file_id = files.id 
                                            JOIN items ON items.item_id = file_links.item_id 
                                            WHERE file_links.individual_id = individuals.id 
                                            AND items.detail_type = 'Key Image'
                                            LIMIT 1), 
                                        '') AS keyimagepath
                                FROM individuals
                                ORDER BY last_name, first_names");
    //echo "<pre>"; print_r($individuals); echo "</pre>";
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
