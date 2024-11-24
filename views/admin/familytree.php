<?php
//A series of database checks to maintain the tree and site

//Check if the user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

//Process for deleting orphaned files
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['files'])) {
        foreach ($_POST['files'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
    //Refresh the page to show the updated list of orphaned files
    ?>
    <script>
        window.location.href = 'index.php?to=admin/familytree';
    </script>
    <?php
}

//Check for family tree entries without any relationship links
$query = "SELECT *
FROM individuals
WHERE id NOT IN (
    select distinct individual_1_id from relationships
    union
    select distinct individual_2_id from relationships
    )";
$orphans = $db->fetchAll($query);

echo "<h1>Orphaned Individuals</h1>";
if (count($orphans) > 0) {
    echo "<ul class='grid grid-cols-2 sm:grid-cols-4'>";
    foreach ($orphans as $orphan) {
        echo "<li><a href='?to=family/indidivual&individual_id={$orphan['id']}'>";
        echo "{$orphan['first_name']} {$orphan['last_name']}";
        echo "</a></li>";
    }
    echo "</ul>";
} else {
    echo "<p>No orphaned individuals found</p>";
}

//Check for orphaned files
// Firstly by getting a list of  in the uploads directories (including subdirectories)
$files = [];
$directories = ['uploads'];
while ($directory = array_shift($directories)) {
    $filesInDirectory = scandir($directory);
    foreach ($filesInDirectory as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        //If the file begins with . then ignore
        if (substr($file, 0, 1) == '.') {
            continue;
        }
        $path = $directory . '/' . $file;
        if (is_dir($path)) {
            $directories[] = $path;
        } else {
            $files[] = $path;
        }
    }
}



//Then by getting a list of the file paths in the database,
// located in the tables "files" and "discussion_files"
$databaseFiles = $db->fetchAll("SELECT file_path FROM files");
$discussionFiles = $db->fetchAll("SELECT file_path FROM discussion_files");
$useravatarFiles = $db->fetchAll("SELECT avatar as file_path FROM users");
$databaseFiles = array_merge($databaseFiles, $discussionFiles, $useravatarFiles);

//Now we compare the two lists to find orphaned files
$orphanedFiles = [];
foreach ($files as $file) {
    $found = false;
    foreach ($databaseFiles as $databaseFile) {
        if ($file == $databaseFile['file_path']) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $orphanedFiles[] = $file;
    }
}

echo "<h1>Orphaned Files</h1>";
//Present the list of orphaned files in a submitable form
// so I can check the ones I want to delete
if(count($orphanedFiles) > 0) {
    echo "<form method='post' class='max-h-min overflow-x-scroll'>";
    echo "<center><button type='submit' class='rounded-lg p-2 text-white border bg-gray-400 hover:bg-gray-800 value='Delete Selected Files'><i class='fas fa-trash-alt'></i> Delete Selected Files</button></center>";
    echo "<ul class='grid grid-cols-2 sm:grid-cols-4'>";
    $i=1;
    foreach ($orphanedFiles as $orphanedFile) {
        echo "<li class='flex justify-start items-center space-x-4 m-1'><div>";
        echo "<input type='checkbox' id='delfile$i' name='files[]' value='{$orphanedFile}'></div>";
        echo "<label for='delfile$i'><img src='{$orphanedFile}' class='object-cover rounded-lg w-24 h-24'></label>";
        //echo "{$orphanedFile}";
        echo "</li>";
        $i++;
    }
    echo "</ul>";
    echo "</form>";
} else {
    echo "<p>No orphaned files found</p>";
}

