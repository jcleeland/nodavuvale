<?php
//A series of database checks to maintain the tree and site

//Check if the user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

//Check to see if the parent admin page has loaded, and if not then "require" it first
if (!isset($admin_page) || !$admin_page) {
    $admin_backload=true;
    require_once('views/admin/index.php');
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
    <div class="flex justify-between items-center gap-4">
        <h1 class="text-4xl font-bold mb-6">File Integrity</h1>
    </div>
    <div class="mb-4 p-4 border border-blue-500 rounded bg-white">
        <p>File and item cleanup has moved to the guarded Database Management scan. It checks structured links, rich-text images, recent-file grace periods, and writes a recovery manifest before any mutation.</p>
        <a href="index.php?to=admin/&amp;section=database#database-cleanup" class="inline-block mt-3 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Open Database and Upload Cleanup</a>
    </div>
</section>
