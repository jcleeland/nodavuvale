<?php
// Assuming $individual_id is passed in the URL, e.g., index.php?to=upload_file&individual_id=1
$individual_id = $_GET['individual_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $individual_id) {
    $file_type = $_POST['file_type'];
    $file_description = $_POST['file_description'];
    $target_dir = ($file_type === 'photo') ? "uploads/photos/" : "uploads/documents/";
    $target_file = $target_dir . basename($_FILES["file_upload"]["name"]);

    // Ensure the uploads directory exists
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Attempt to move the uploaded file
    if (move_uploaded_file($_FILES["file_upload"]["tmp_name"], $target_file)) {
        // Store the file information in the database
        $db->query(
            "INSERT INTO files (individual_id, file_type, file_path, file_description) VALUES (?, ?, ?, ?)",
            [$individual_id, $file_type, $target_file, $file_description]
        );
        echo "File successfully uploaded.";
    } else {
        echo "Error uploading file.";
    }
}
?>

<section class="container mx-auto py-12">
    <h2 class="text-2xl font-bold mb-6">Upload a File for Family Member</h2>
    
    <form action="index.php?to=upload_file&individual_id=<?php echo $individual_id; ?>" method="POST" enctype="multipart/form-data">
        <div class="mb-4">
            <label for="file_type" class="block text-gray-700">File Type</label>
            <select id="file_type" name="file_type" class="w-full px-4 py-2 border rounded-lg">
                <option value="photo">Photo</option>
                <option value="document">Document</option>
            </select>
        </div>

        <div class="mb-4">
            <label for="file_description" class="block text-gray-700">File Description</label>
            <textarea id="file_description" name="file_description" class="w-full px-4 py-2 border rounded-lg" placeholder="Enter description (optional)"></textarea>
        </div>

        <div class="mb-4">
            <label for="file_upload" class="block text-gray-700">Choose File</label>
            <input type="file" id="file_upload" name="file_upload" class="w-full px-4 py-2 border rounded-lg" required>
        </div>

        <div class="text-center">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">Upload</button>
        </div>
    </form>
</section>
