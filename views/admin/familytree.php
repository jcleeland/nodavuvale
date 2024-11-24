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


?>
<!-- Orphaned Individuals -->
<section class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center">
        <h1 class="text-4xl font-bold mb-6">Orphaned Individuals</h1>
    </div>
    <div class="mb-4 p-2 pb-4 border border-blue-500 rounded bg-white">
        <div class="mt-4 max-h-96 overflow-auto" id="orphaned-files-section" >
            <?php
            if (count($orphans) > 0) {
            ?>
            <ul class='grid grid-cols-2 sm:grid-cols-4'>
            <?php
                foreach ($orphans as $orphan) {
                    echo "<li class='flex justify-start items-center space-x-4 m-1'><a href='?to=family/indidivual&individual_id={$orphan['id']}'>";
                    echo "{$orphan['first_name']} {$orphan['last_name']}";
                    echo "</a></li>";
                } ?>
            </ul> <?php
            } else {
                echo "<p class='text-center'>No orphaned individuals found</p>";
            }                
            ?>
        </div>
    </div>
</section>
<section class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <form method="post">
        <div class="flex justify-between items-center">
            <h1 class="text-4xl font-bold mb-6">Orphaned Files</h1>
            <?php if(count($orphanedFiles) > 0) { ?>
                <button type="submit" class="float-right bg-blue-500 text-white px-2 py-1 mb-2 rounded"><i class='fas fa-trash'></i> Delete Selected Files</button>
            <?php } ?>
        </div>
        <div class="mb-4 p-2 pb-4 border border-blue-500 rounded bg-white">
            <div class="mt-4 max-h-96 overflow-auto" id="orphaned-files-section" >
                    <?php
                    if(count($orphanedFiles) > 0) { ?>
                        <ul class='grid grid-cols-2 sm:grid-cols-4'> <?php

                        $i=1;
                        foreach ($orphanedFiles as $orphanedFile) {
                            echo "<li class='flex justify-start items-center space-x-4 m-1'><div>";
                            echo "<input type='checkbox' id='delfile$i' name='files[]' value='{$orphanedFile}'></div>";
                            echo "<label for='delfile$i'><img src='{$orphanedFile}' class='object-cover rounded-lg w-24 h-24'></label>";
                            //echo "{$orphanedFile}";
                            echo "</li>";
                            $i++;
                        } ?>
                        </ul> <?php
                    } else {
                        echo "<p class='text-center'>No orphaned files found</p>";
                    }
                    ?>
            </div>
        </div>
    </form>
</section>
