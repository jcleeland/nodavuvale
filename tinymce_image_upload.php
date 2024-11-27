<?php
// upload_image.php

if ($_FILES['file']['name']) {
    $filename = $_FILES['file']['name'];
    $location = 'uploads/tinymce/' . $filename; // Adjust the upload directory as needed
    if (move_uploaded_file($_FILES['file']['tmp_name'], $location)) {
        echo json_encode(['location' => $location]);
    } else {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => 'Failed to move uploaded file.']);
    }
} else {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'No file uploaded.']);
}
?>