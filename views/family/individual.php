<?php

//Handle form submission for updating individuals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_individual') {
    include("helpers/update_individual.php");
}

$individual_id = $_GET['individual_id'] ?? null;
if(!isset($rootId)) {
    $rootId = Web::getRootId();
}

if ($individual_id) {
    // Fetch the individual details
    $individual = $db->fetchOne("SELECT * FROM individuals WHERE id = ?", [$individual_id]);

    //Fetch parents
    $parents = Utils::getParents($individual_id);

    //Fetch children
    $children = Utils::getChildren($individual_id);

    // Fetch associated files (photos and documents)
    $photos = $db->fetchAll("SELECT * FROM files WHERE individual_id = ? AND file_type = 'photo'", [$individual_id]);
    $documents = $db->fetchAll("SELECT * FROM files WHERE individual_id = ? AND file_type = 'document'", [$individual_id]);

    // Extract the birth details
    $year = $individual['birth_year'];
    $month = isset($individual['birth_month']) && $individual['birth_month'] > 0 ? $individual['birth_month'] : null;
    $day = isset($individual['birth_date']) && $individual['birth_date'] > 0 ? $individual['birth_date'] : null;

    $birthdate="";
    // Check which parts of the date are available
    if ($day && $month && $year) {
        // If all parts are available, show the full date with the day of the week
        $birthdate=date("l, d M Y", strtotime("$year-$month-$day")); // E.g., "Monday, 01 Jan 1840"
    } elseif ($month && $year) {
        // If only the month and year are available, show the month name and year
        $birthdate=date("F Y", strtotime("$year-$month")); // E.g., "January 1840"
    } elseif ($year) {
        // If only the year is available, show just the year
        $birthdate= $year; // E.g., "1840"
    } else {
        // If no birth information is available, show a default message (optional)
        $birthdate= "Unknown";
    }

    // Extract the death details
    $year = $individual['death_year'];
    $month = isset($individual['death_month']) && $individual['death_month'] > 0 ? $individual['death_month'] : null;
    $day = isset($individual['death_date']) && $individual['death_date'] > 0 ? $individual['death_date'] : null;
    
    $deathdate="";
    // Check which parts of the date are available
    if ($day && $month && $year) {
        // If all parts are available, show the full date with the day of the week
        $deathdate=date("l, d M Y", strtotime("$year-$month-$day")); // E.g., "Monday, 01 Jan 1840"
    } elseif ($month && $year) {
        // If only the month and year are available, show the month name and year
        $deathdate=date("F Y", strtotime("$year-$month")); // E.g., "January 1840"
    } elseif ($year) {
        // If only the year is available, show just the year
        $deathdate= $year; // E.g., "1840"
    } else {
        // If no birth information is available, show a default message (optional)
        $deathdate= "";
    }
}

include("helpers/quickedit.php");
?>

<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold"><?php echo $individual['first_names'] . ' ' . $individual['last_name']; ?></h2>
        <p class="mt-4 text-lg"><?= $individual['birth_prefix']; ?> <?= $birthdate ?> - <?= $individual['death_prefix'] ?> <?= $deathdate ?></p>
    </div>
</section>

<section class="container mx-auto py-12">
    <div class="grid grid-cols-1 lg:grid-cols-2">
        <div class="text-center p-2">
            <!-- Display Parents -->
            <h3 class="text-2xl font-bold mt-8 mb-4">Parents</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6 bg-white shadow-lg rounded-lg grid-scrollable-3 place-items-center">
                <?php foreach ($parents as $parent): ?>
                    <div class="individual-item text-center p-1 shadow-lg rounded-lg gender_<?= $parent['gender'] ?>">
                        <h4 class="text-xl font-bold"><a href="?to=family/individual&individual_id=<?= $parent['id'] ?>"><?php echo $parent['first_names'] . ' ' . $parent['last_name']; ?></a></h4>
                        <p class="mt-2 text-sm text-gray-600"><?php echo $parent['birth_prefix']; ?> <?php echo $parent['birth_year']; ?> - <?php echo $parent['death_prefix']; ?> <?php echo $parent['death_year']; ?></p>
                        <button class="edit-btn" data-individual-id="<?= $parent['id'] ?>" title="Edit">&#9998;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="text-center p-2">
            <!-- Display Children -->
            <h3 class="text-2xl font-bold mt-8 mb-4">Children (<?= count($children) ?>)</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6 bg-white shadow-lg rounded-lg grid-scrollable-3 place-items-center">
                <?php foreach ($children as $child): ?>
                    <div class="individual-item text-center p-1 shadow-lg rounded-lg gender_<?= $child['gender'] ?>">
                        <h4 class="text-xl font-bold"><a href="?to=family/individual&individual_id=<?= $child['id'] ?>"><?php echo $child['first_names'] . ' ' . $child['last_name']; ?></a></h4>
                        <p class="mt-2 text-sm text-gray-600"><?php echo $child['birth_prefix']; ?> <?php echo $child['birth_year']; ?> - <?php echo $child['death_prefix']; ?> <?php echo $child['death_year']; ?></p>
                        <button class="edit-btn" data-individual-id="<?= $child['id'] ?>" title="Edit">&#9998;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="container mx-auto py-12">    
    <!-- Display Photos -->
    <h3 class="text-2xl font-bold mt-8 mb-4">Photos</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6 bg-white shadow-lg rounded-lg">
        <?php foreach ($photos as $photo): ?>
            <div class="photo-item mb-4 text-center p-1 shadow-lg rounded-lg">
                <img src="<?php echo $photo['file_path']; ?>" alt="Photo of <?php echo $individual['first_name']; ?>" class="w-full h-auto rounded-lg">
                <?php if (!empty($photo['file_description'])): ?>
                    <p class="mt-2 text-sm text-gray-600"><?php echo $photo['file_description']; ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Display Documents -->
    <h3 class="text-2xl font-bold mt-8 mb-4">Documents</h3>
    <div class="document-list grid grid-cols-1 md:grid-cols-3 gap-6 p-6 bg-white shadow-lg rounded-lg">
        <?php foreach ($documents as $document): ?>
            <div class="document-item mb-4 text-center p-1 shadow-lg rounded-lg">
                <a href="<?php echo $document['file_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                    View Document
                </a>
                <?php if (!empty($document['file_description'])): ?>
                    <p class="mt-1 text-sm text-gray-600"><?php echo $document['file_description']; ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
