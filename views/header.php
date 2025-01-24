<?php
//If this is an individual page, get their name so it can be shown in the page title
$pagetitlesuffix="";
if(isset($_GET['individual_id'])) {
    $individual_id = $_GET['individual_id'];
    $pagetitlesuffix = Utils::getIndividualName($individual_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $site_name ?><?php if(!empty($pagetitlesuffix)) echo ": ".$pagetitlesuffix ?></title>
    <!-- Tailwind CSS -->
    <link href="styles/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/font-awesome/css/all.min.css">
    <link href="styles/styles.css" rel="stylesheet">

    <!-- Link to dTree CSS -->
    <link rel="stylesheet" href="styles/dTree.css">

    <!-- Link to Flatpickr CSS -->
    <link rel="stylesheet" href="styles/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Link to tinymce editor -->
    <script src="js/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
    
    <!-- Link to main js file -->
    <script src="js/index.js"></script>

    <!-- Link to dTree JavaScript -->
    <script src="vendor/lodash/lodash.js"></script>
    <script src="https://d3js.org/d3.v5.min.js"></script>
    <script src="vendor/dTree/dist/dTree.js"></script>


</head>

<?php
//If user is admin show the SESSION information
if($auth->getUserRole() == 'admin') {
    echo "<!-- SESSION INFO: ";
    print_r($_SESSION);
    echo " -->";
}
?>


<body class="bg-cream text-brown font-sans">
<input type='hidden' id='js_user_id' value='<?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '' ?>'>

    <!-- shared modal prompt for all pages --> 
    <div id="customPrompt" class="modal" >
        <div class="modal-content w-3/5 max-w-20 min-w-min max-h-screen my-5 overflow-y-auto">
            <div class="cursor-pointer py-1 bg-deep-green-800 text-white">
                <span id="customPromptClose" class="close-story-btn">&times;</span>
                <h2 id="customPromptTitle" class="text-xl font-bold mb-4 text-center">Custom Prompt</h2>
            </div>
            <div class="modal-body">
                <p id="customPromptMessage" class="mb-4"></p>
                <div id="customPromptInputs" class="mb-4">
                    <!-- Form content will go here -->
                </div>
                <div class="flex justify-end">
                    <button id="customPromptCancel" class="bg-gray-500 text-white px-4 py-2 rounded mr-2">Cancel</button>
                    <button id="customPromptOk" class="bg-blue-500 text-white px-4 py-2 rounded">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="bg-deep-green text-white p-4">
        <div class="container mx-auto flex items-center justify-between">
            <!-- Sitename -->
            <div class="text-left whitespace-nowrap">
                <h1 class="text-xl font-bold"><a href="index.php"><?= $site_name ?></a></h1>
            </div>
            
            <!-- Navigation Options -->
            <nav class="hidden sm:flex flex-grow justify-end text-right space-x-4">
                <?php if ($auth->isLoggedIn()) : ?>
                <a href="index.php" class="text-white hover:text-burnt-orange">Home</a>
                <a href="?to=family/tree" class="text-white hover:text-burnt-orange">Tree</a>
                <a href="?to=family/users" class="text-white hover:text-burnt-orange">Members</a>
                <a href="?to=communications/discussions" class="text-white hover:text-burnt-orange">Chat</a>
                <a href="?to=family/gallery" class="text-white hover:text-burnt-orange">Gallery</a>
                <?php endif; ?>
            </nav>
            
            <!-- Mobile Navigation Toggle Button -->
            <div class="flex-grow sm:hidden text-right">
                <button id="nav-toggle" class="text-white hover:text-burnt-orange focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M8 12h8m-4 6h2m-2"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Mobile Navigation Menu -->
            <div id="nav-menu" class="hidden md:hidden flex-grow absolute top-16 right-0 w-full bg-brown text-white z-30 text-center">
                <?php if ($auth->isLoggedIn()) : ?>
                <a href="index.php" class="block px-4 py-2 text-white hover:text-burnt-orange">Home</a>
                <a href="?to=family/tree" class="block px-4 py-2 text-white hover:text-burnt-orange">Tree</a>
                <a href="?to=family/users" class="block px-4 py-2 text-white hover:text-burnt-orange">Members</a>
                <a href="?to=communications/discussions" class="block px-4 py-2 text-white hover:text-burnt-orange">Chat</a>
                <a href="?to=family/gallery" class="block px-4 py-2 text-white hover:text-burnt-orange">Gallery</a>
                <?php endif; ?>
            </div>
            
            <!-- User Account/Login -->
            <div class="text-right">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== ""): ?>
                    <?php
                    // Assuming you have the user's first and last name stored in the session
                    $firstName = $_SESSION['first_name'];
                    $lastName = $_SESSION['last_name'];
                    $initials = strtoupper($firstName[0] . $lastName[0]);
                    ?>
                    <div class="relative inline-block">
                        <button class="ml-4 text-white hover:text-burnt-orange focus:outline-none" id="user-menu-button">
                            <span class="inline-block w-8 h-8 bg-gray-500 rounded-full text-center leading-8" title="Logged in as <?= $firstName." ".$lastName ?>">
                                <?php echo $initials; ?>
                            </span>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20 hidden" id="user-menu">
                            <a href="?to=account" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">My Account</a>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="?to=admin/" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Adminstrator Options</a>
                            <?php endif; ?>
                            <a href="?action=logout" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="?to=login" class="ml-4 text-white hover:text-burnt-orange">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <script>
        document.getElementById('nav-toggle').addEventListener('click', function() {
            var navMenu = document.getElementById('nav-menu');
            if (navMenu.classList.contains('hidden')) {
                navMenu.classList.remove('hidden');
            } else {
                navMenu.classList.add('hidden');
            }
        });
        document.addEventListener('DOMContentLoaded', function() {
            var userMenuButton = document.getElementById('user-menu-button');
            if (userMenuButton) {
                userMenuButton.addEventListener('click', function() {
                    var menu = document.getElementById('user-menu');
                    if (menu) {
                        menu.classList.toggle('hidden');
                    }
                });
            }
        });
    </script>
