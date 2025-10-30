<?php
//If this is an individual page, get their name so it can be shown in the page title
$pagetitlesuffix="";
if(isset($_GET['individual_id'])) {
    $individual_id = $_GET['individual_id'];
    $pagetitlesuffix = Utils::getIndividualName($individual_id);
}

$bodyClasses = ['bg-cream', 'text-brown', 'font-sans'];
$currentRoute = isset($_GET['to']) ? strtolower($_GET['to']) : '';
$isIndividualPage = $currentRoute === 'family/individual';
if ($isIndividualPage) {
    $bodyClasses[] = 'individual-page';
} else {
    $bodyClasses[] = 'non-individual-page';
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
    <link href="styles/styles.css?v=4" rel="stylesheet">

    <!-- Link to dTree CSS -->
    <link rel="stylesheet" href="styles/dTree.css">

    <!-- Link to Flatpickr CSS -->
    <link rel="stylesheet" href="styles/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Link to tinymce editor -->
    <script src="js/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
    
    <!-- Link to main js file -->
    <script src="js/index.js?v=2"></script>

    <!-- Link to dTree JavaScript -->
    <script src="vendor/lodash/lodash.js"></script>
    <script src="https://d3js.org/d3.v5.min.js"></script>
    <script src="vendor/dTree/dist/dTree.js"></script>


</head>

<?php
//If user is admin show the SESSION information
if($auth->getUserRole() == 'admin') {
    /* echo "<!-- SESSION INFO: ";
    print_r($_SESSION);
    echo " -->"; */
}
?>


<body class="<?= implode(' ', $bodyClasses) ?>">
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
    <header class="site-header fixed top-0 left-0 right-0 bg-deep-green text-white shadow-lg z-50">
        <div class="container mx-auto flex items-center justify-between p-4 relative">
            <!-- Sitename -->
            <div class="text-left whitespace-nowrap">
                <h1 class="text-xl font-bold"><a href="index.php"><?= $site_name ?></a></h1>
            </div>
            
            <!-- Navigation Options -->
            <nav class="hidden sm:flex flex-grow justify-end text-right space-x-6">
                <a href='?to=origins/' class="text-white hover:bg-burnt-orange-800 px-2 pb-1 rounded-md">Origins</a>
                <?php if ($auth->isLoggedIn()) : ?>
                <a href="?to=family/tree" class="text-white hover:bg-burnt-orange-800 px-2 pb-1 rounded-md">Tree</a>
                <span class="text-white hover:bg-burnt-orange-800 px-2 pb-1 rounded-md cursor-pointer" onClick="document.getElementById('findFamily').style.display = 'block';">Find</span>              
                <a href="?to=family/users" class="text-white hover:bg-burnt-orange-800 px-2 pb-1 rounded-md">Members</a>
                <a href="?to=communications/discussions" class="text-white hover:bg-burnt-orange-800 px-2 pb-1 rounded-md">Chat</a>
                <a href="?to=family/gallery" class="text-white hover:bg-burnt-orange-800 px-2 pb-1 rounded-md">Gallery</a>
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
            <div id="nav-menu" class="hidden sm:hidden flex-grow absolute top-[calc(100%+0.5rem)] right-0 w-full bg-deep-green text-white z-40 text-center rounded-b-lg shadow-lg">
                <a href='?to=origins/' class="block px-4 py-2 text-white">Origins</a>
                <?php if ($auth->isLoggedIn()) : ?>
                <a href="?to=family/tree" class="block px-4 py-2 text-white">Tree</a>
                <span class="block text-white hover:bg-burnt-orange-800 px-4 py-2" onClick="document.getElementById('findFamily').style.display = 'block';">Find</span>
                <a href="?to=family/users" class="block px-4 py-2 text-white">Members</a>
                <a href="?to=communications/discussions" class="block px-4 py-2 text-white">Chat</a>
                <a href="?to=family/gallery" class="block px-4 py-2 text-white">Gallery</a>
                <?php endif; ?>
            </div>
            
            <!-- User Account/Login -->
            <div class="text-right pb-1">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== ""): ?>
                    <?php
                    // Assuming you have the user's first and last name stored in the session
                    $firstName = $_SESSION['first_name'];
                    $lastName = $_SESSION['last_name'];
                    $initials = strtoupper($firstName[0] . $lastName[0]);
                    ?>
                    <div class="relative inline-block">
                        <button class="ml-4 text-white focus:outline-none" id="user-menu-button">
                            <span class="inline-block w-8 h-8 bg-gray-500 hover:bg-burnt-orange-800 rounded-full text-center leading-8" title="Logged in as <?= $firstName." ".$lastName ?>">
                                <?php echo $initials; ?>
                            </span>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20 hidden" id="user-menu">
                            <a href="?to=account" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">My Account</a>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="?to=admin/" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Administrator Options</a>
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


    <div id="findFamily" class="modal relative">
        <div class="modal-content modal-top w-3/4 min-w-xs max-h-screen my-5 top-20">
            <div class="modal-header">
                <span id="findFamilyClose" class="close-story-btn" onclick="document.getElementById('findFamily').style.display='none';">&times;</span>
                <h2 id="findFamilyTitle" class="text-xl font-bold mb-4 text-center">Find family</h2>
            </div>
            <div class="modal-body">
                <label for='lookupfamily_display'>
                    Find someone
                </label>
                <input 
                    type='text' 
                    placeholder='Find someone in the family' 
                    id='lookupfamily_name' 
                    name='lookupfamily_name' 
                    class='w-full border rounded-lg p-2 mb-2' 
                    oninput='showFamilySuggestions(this.value)'>
                <div id='lookupfamily_suggestions' class='autocomplete-suggestions'></div>
            </div>
                <script type='text/javascript'>
                    const individuals = [
                        <?php
                            foreach($individuals as $individual) {
                                $thisname=$individual['first_names']." ".$individual['last_name'];
                                // Escape any apostrophes in the name:
                                $thisname = str_replace("'", "\'", $thisname);
                                if($individual['birth_year'] && $individual['birth_year'] != "") {
                                    $thisname .= " (b.".$individual['birth_year'].")";
                                }
                                if($individual['keyimagepath'] && $individual['keyimagepath'] != "") {
                                    $thisname = '<img src="'.$individual['keyimagepath'].'" class="w-8 h-8 object-cover rounded-full inline-block mr-2">'.$thisname;
                                } else {
                                    $thisname = '<img src="images/default_avatar.webp" class="w-8 h-8 object-cover rounded-full inline-block mr-2">'.$thisname;
                                }
                                echo "{id: ".$individual['id'].", name: '".$thisname."'},";
                            }
                        ?>
                    ];

                    function showFamilySuggestions(value) {
                        const suggestionsContainer = document.getElementById('lookupfamily_suggestions');
                        suggestionsContainer.innerHTML = '';
                        if (value.length === 0) {
                            return;
                        }

                        const filteredIndividuals = individuals.filter(ind => ind.name.toLowerCase().includes(value.toLowerCase()));
                        filteredIndividuals.forEach(ind => {
                            const suggestion = document.createElement('div');
                            suggestion.className = 'autocomplete-suggestion';
                            suggestion.innerHTML = ind.name;
                            suggestion.onclick = () => selectFamilySuggestion(ind);
                            suggestionsContainer.appendChild(suggestion);
                        });
                    }

                    function selectFamilySuggestion(individual) {
                        const input = document.getElementById('lookupfamily_name');

                        // Create a temporary DOM element to parse the HTML
                        const tempElement = document.createElement('div');
                        tempElement.innerHTML = individual.name;
                        const textContent = tempElement.textContent || tempElement.innerText || '';

                        // Assign the text content to the input value
                        input.value = textContent;

                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'lookupfamily';
                        hiddenInput.value = individual.id;
                        input.parentNode.appendChild(hiddenInput);
                        document.getElementById('lookupfamily_suggestions').innerHTML = '';
                        window.location.href = '?to=family/individual&individual_id=' + individual.id;
                    }
                    </script>
                <br />&nbsp;<br />
            </div>
        </div>
    </div>      

    <script>
        (function () {
            function onReady(callback) {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', callback, { once: true });
                } else {
                    callback();
                }
            }

            onReady(function () {
                var header = document.querySelector('.site-header');
                function getHeaderHeight() {
                    return header ? header.offsetHeight : 0;
                }

                function applyHeaderOffset() {
                    if (!header) {
                        header = document.querySelector('.site-header');
                    }
                    if (!header) {
                        return;
                    }
                    document.body.classList.add('has-fixed-header');
                    document.body.style.setProperty('--header-height', getHeaderHeight() + 'px');
                }

                applyHeaderOffset();
                window.addEventListener('resize', applyHeaderOffset);

                var navToggle = document.getElementById('nav-toggle');
                var navMenu = document.getElementById('nav-menu');

                function positionNavMenu() {
                    if (!navMenu || navMenu.classList.contains('hidden')) {
                        return;
                    }
                    var headerHeight = getHeaderHeight();
                    navMenu.style.position = 'fixed';
                    navMenu.style.top = headerHeight + 8 + 'px';
                    navMenu.style.left = '0';
                    navMenu.style.right = '0';
                    navMenu.style.zIndex = '900';
                    navMenu.style.maxHeight = 'calc(100vh - ' + (headerHeight + 12) + 'px)';
                    navMenu.style.overflowY = 'auto';
                }

                function closeNavMenu() {
                    if (!navMenu || navMenu.classList.contains('hidden')) {
                        return;
                    }
                    navMenu.classList.add('hidden');
                    navMenu.style.position = '';
                    navMenu.style.top = '';
                    navMenu.style.left = '';
                    navMenu.style.right = '';
                    navMenu.style.maxHeight = '';
                    navMenu.style.overflowY = '';
                }

                if (navToggle && navMenu) {
                    navToggle.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (navMenu.classList.contains('hidden')) {
                            navMenu.classList.remove('hidden');
                            requestAnimationFrame(positionNavMenu);
                        } else {
                            closeNavMenu();
                        }
                    });

                    navMenu.addEventListener('click', function (event) {
                        event.stopPropagation();
                    });

                    document.addEventListener('click', function (event) {
                        if (navMenu.classList.contains('hidden')) {
                            return;
                        }
                        if (!navMenu.contains(event.target) && !navToggle.contains(event.target)) {
                            closeNavMenu();
                        }
                    });

                    window.addEventListener('resize', function () {
                        if (!navMenu.classList.contains('hidden')) {
                            positionNavMenu();
                        }
                    });

                    window.addEventListener('scroll', function () {
                        if (!navMenu.classList.contains('hidden')) {
                            positionNavMenu();
                        }
                    }, true);
                }

                var userMenuButton = document.getElementById('user-menu-button');
                var userMenu = document.getElementById('user-menu');

                function positionUserMenu() {
                    if (!userMenu || userMenu.classList.contains('hidden')) {
                        return;
                    }
                    var headerHeight = getHeaderHeight();
                    var buttonRect = userMenuButton.getBoundingClientRect();
                    var topOffset = headerHeight + 8;
                    var menuWidth = userMenu.offsetWidth || 192;
                    var leftOffset = buttonRect.right - menuWidth;
                    if (leftOffset + menuWidth > window.innerWidth - 8) {
                        leftOffset = window.innerWidth - menuWidth - 8;
                    }
                    if (leftOffset < 8) {
                        leftOffset = 8;
                    }
                    userMenu.style.position = 'fixed';
                    userMenu.style.zIndex = '1000';
                    userMenu.style.top = Math.max(8, topOffset) + 'px';
                    userMenu.style.left = leftOffset + 'px';
                    userMenu.style.right = 'auto';
                    userMenu.style.maxHeight = '';
                    userMenu.style.overflowY = '';
                    var menuRect = userMenu.getBoundingClientRect();
                    if (menuRect.bottom > window.innerHeight - 8) {
                        var availableHeight = window.innerHeight - menuRect.top - 12;
                        if (availableHeight > 120) {
                            userMenu.style.maxHeight = availableHeight + 'px';
                            userMenu.style.overflowY = 'auto';
                        }
                    }
                }

                function closeUserMenu() {
                    if (!userMenu || userMenu.classList.contains('hidden')) {
                        return;
                    }
                    userMenu.classList.add('hidden');
                    userMenu.style.position = '';
                    userMenu.style.top = '';
                    userMenu.style.left = '';
                    userMenu.style.right = '';
                    userMenu.style.maxHeight = '';
                    userMenu.style.overflowY = '';
                }

                if (userMenuButton && userMenu) {
                    userMenuButton.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (userMenu.classList.contains('hidden')) {
                            userMenu.classList.remove('hidden');
                            requestAnimationFrame(positionUserMenu);
                        } else {
                            closeUserMenu();
                        }
                    });

                    userMenu.addEventListener('click', function (event) {
                        event.stopPropagation();
                    });

                    document.addEventListener('click', function (event) {
                        if (userMenu.classList.contains('hidden')) {
                            return;
                        }
                        if (!userMenu.contains(event.target) && !userMenuButton.contains(event.target)) {
                            closeUserMenu();
                        }
                    });

                    window.addEventListener('resize', function () {
                        if (!userMenu.classList.contains('hidden')) {
                            positionUserMenu();
                        }
                    });

                    window.addEventListener('scroll', function () {
                        if (!userMenu.classList.contains('hidden')) {
                            positionUserMenu();
                        }
                    }, true);
                }
            });
        })();
    </script>
