<?php
// Assuming $individual_id is passed in the URL, e.g., index.php?to=upload_file&individual_id=1
$individual_id = $_GET['individual_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $individual_id) {
    $file_type = $_POST['file_type'];
    $file_description = trim((string) ($_POST['file_description'] ?? ''));
    $target_dir = ($file_type === 'photo') ? "uploads/images/" : "uploads/documents/";
    $target_file = $target_dir . uniqid('', true) . '_' . basename($_FILES["file_upload"]["name"]);

    // Ensure the uploads directory exists
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Attempt to move the uploaded file
    if (move_uploaded_file($_FILES["file_upload"]["tmp_name"], $target_file)) {
        $mediaDateInput = [
            'year' => $_POST['media_date_year'] ?? null,
            'month' => $_POST['media_date_month'] ?? null,
            'day' => $_POST['media_date_day'] ?? null,
            'approx' => !empty($_POST['media_date_is_approximate']) ? 1 : 0,
        ];
        [$mediaDateValue, $mediaDatePrecision, $mediaDateApprox] = Utils::prepareFlexibleDateFromArray($mediaDateInput);

        $fileTypeForDb = ($file_type === 'photo') ? 'image' : 'document';
        $fileFormat = pathinfo($target_file, PATHINFO_EXTENSION);
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        $fileId = $db->insert(
            "INSERT INTO files (file_type, file_path, file_format, file_description, user_id, media_date, media_date_precision, media_date_is_approximate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$fileTypeForDb, $target_file, $fileFormat, $file_description, $userId, $mediaDateValue, $mediaDatePrecision, $mediaDateApprox]
        );

        $db->insert(
            "INSERT INTO file_links (file_id, individual_id, item_id, link_date, link_date_precision, link_date_is_approximate) VALUES (?, ?, ?, ?, ?, ?)",
            [$fileId, $individual_id, null, $mediaDateValue, $mediaDatePrecision, $mediaDateApprox]
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
            <label class="block text-gray-700">Timeline Date (optional)</label>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mt-2">
                <div>
                    <label for="media_date_year" class="block text-xs text-slate-500 mb-1">Year</label>
                    <input type="text" id="media_date_year" name="media_date_year" class="w-full px-4 py-2 border rounded-lg" maxlength="4" pattern="\d{4}">
                </div>
                <div>
                    <label for="media_date_month" class="block text-xs text-slate-500 mb-1">Month</label>
                    <select id="media_date_month" name="media_date_month" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">--</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="media_date_day" class="block text-xs text-slate-500 mb-1">Day</label>
                    <select id="media_date_day" name="media_date_day" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">--</option>
                        <?php for ($d = 1; $d <= 31; $d++): ?>
                            <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex items-center gap-2 mt-6 sm:mt-0">
                    <input type="checkbox" id="media_date_is_approximate" name="media_date_is_approximate" value="1">
                    <label for="media_date_is_approximate" class="text-xs text-slate-500">Approximate</label>
                </div>
            </div>
            <p class="mt-2 text-xs text-slate-500">Leave blank if the date is unknown. Photos without a date will not appear in the timeline.</p>
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
