<?php
/**
 * This file is used to handle AJAX requests for individual details. 
 * It routes requests to the appropriate php file in the system/ajax/ directory
 * and then json_encodes the array that is returned.
 * 
 * It expects the name of the file to be included in the 'method' parameter of the POST request. 
 * 
 * It loads the required classes.
 */

// Load required files
session_name('nodavuvale_app_session');
session_start();

/** 
 * Include the configuration files
 */
include('system/config.php');

/**
 * Include the Global Village classes.
 * 
 * @package nodavuvale
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

if(!empty($_FILES)) {
    //Handle file uploads
    $method = $_POST['method'] ?? null;
    $data = json_decode($_POST['data'], true);
    $data['file'] = $_FILES['file'];
    if(isset($method)) {
        $method = str_replace('..', '', $method); // Prevent directory traversal
        $method = str_replace('/', '', $method); // Prevent directory traversal

        //Check if the user is logged in
        if($auth->isLoggedIn()) {
            //Include the requested file
            include('system/ajax/' . $method . '.php');
            echo json_encode($response);
        } else {
            //User is not logged in
            echo json_encode(['error' => 'User not logged in']);
        }
    } else {
        //No method specified
        echo json_encode(['error' => 'No method specified for file upload']);
    }
} else {
    // Handle JSON data
    // Get the raw POST data
    $postData = file_get_contents('php://input');

    // Decode the JSON data
    $request = json_decode($postData, true);

    // Extract the method and data
    $method = $request['method'] ?? null;
    $data = $request['data'] ?? null;
    
    if(isset($method)) {
        $method = str_replace('..', '', $method); // Prevent directory traversal
        $method = str_replace('/', '', $method); // Prevent directory traversal

        // Check if the user is logged in
        if ($auth->isLoggedIn()) {
            // Include the requested file
            include('system/ajax/' . $method . '.php');
            echo json_encode($response);
        } else {
            // User is not logged in
            echo json_encode(['error' => 'User not logged in']);
        }
    } else {
        // No method specified
        echo json_encode(['error' => 'No method specified for AJAX request']);
    }
}
