<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $site_name ?></title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="styles/styles.css" rel="stylesheet">
    <style>
        /* Custom colors reflecting Fijian traditional artwork */
        :root {
            --brown: #6b4226;
            --cream: #f5f5dc;
            --burnt-orange: #cc5500;
            --deep-green: #2f4f4f;
            --ocean-blue: #0077b6;
            --warm-red: #b22222;
        }
    </style>
    <!-- Link to dTree CSS -->
    <link rel="stylesheet" href="vendor/dTree/dist/dTree.css">

    <!-- Link to main js file -->
    <script src="js/index.js"></script>
    <!-- Link to dTree JavaScript -->
    <script src="vendor/lodash/lodash.js"></script>
    <script src="https://d3js.org/d3.v5.min.js"></script>
    <script src="vendor/dTree/dist/dTree.js"></script>


</head>
<body class="bg-cream text-brown font-sans">
<input type='hidden' id='js_user_id' value='<?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '' ?>'>

    <!-- Header -->
    <header class="bg-brown text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold"><a href="index.php"><?= $site_name ?></a></h1>
            <nav class="relative">
                <a href="index.php" class="ml-4 text-white hover:text-burnt-orange">Home</a>
                <a href="?to=family/" class="ml-4 text-white hover:text-burnt-orange">Family</a>
                <a href="?to=village/" class="ml-4 text-white hover:text-burnt-orange">Village</a>
                <a href="?to=communications/" class="ml-4 text-white hover:text-burnt-orange">Communications</a>
                <?php 
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== ""): ?>
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
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20 hidden" id="user-menu" >
                            <a href="?to=account" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Account</a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="?to=admin/" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Admin</a>
                        <?php endif; ?>
                            <a href="?action=logout" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="?to=login" class="ml-4 text-white hover:text-burnt-orange">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    
    <script>
    document.getElementById('user-menu-button').addEventListener('click', function() {
        var menu = document.getElementById('user-menu');
        menu.classList.toggle('hidden');
    });
    </script>
