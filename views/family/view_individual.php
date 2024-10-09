<?php
$individual_id = $_GET['individual_id'] ?? null;

if ($individual_id) {
    // Fetch the individual details
    $individual = $db->fetchOne("SELECT * FROM individuals WHERE id = ?", [$individual_id]);

    // Fetch associated files (photos and documents)
    $photos = $db->fetchAll("SELECT * FROM files WHERE individual_id = ? AND file_type = 'photo'", [$individual_id]);
    $documents = $db->fetchAll("SELECT * FROM files WHERE individual_id = ? AND file_type = 'document'", [$individual_id]);
}
?>

<section class="container mx-auto py-12">
    <h2 class="text-3xl font-bold mb-6"><?php echo $individual['first_name'] . ' ' . $individual['last_name']; ?></h2>
    
    <!-- Display Photos -->
    <h3 class="text-2xl font-bold mb-4">Photos</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($photos as $photo): ?>
            <div class="photo-item">
                <img src="<?php echo $photo['file_path']; ?>" alt="Photo of <?php echo $individual['first_name']; ?>" class="w-full h-auto rounded-lg">
                <?php if (!empty($photo['file_description'])): ?>
                    <p class="mt-2 text-sm text-gray-600"><?php echo $photo['file_description']; ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Display Documents -->
    <h3 class="text-2xl font-bold mt-8 mb-4">Documents</h3>
    <div class="document-list">
        <?php foreach ($documents as $document): ?>
            <div class="document-item mb-4">
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
