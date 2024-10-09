<?php
// Include your database class
require_once 'system/nodavuvale_database.php';

// Get database instance
$db = Database::getInstance();

// Initialize error and success messages
$error_message = '';
$success_message = '';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $is_news = isset($_POST['is_news']) ? 1 : 0;
    $is_sticky = isset($_POST['is_sticky']) ? 1 : 0;

    // Validate the form data
    if (empty($title) || empty($content)) {
        $error_message = 'Title and content are required.';
    } else {
        // Insert the new discussion/news into the database
        $inserted = $db->insert(
            "INSERT INTO discussions (user_id, title, content, is_sticky, is_news, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$title, $content, $is_sticky, $is_news]
        );

        if ($inserted) {
            $success_message = 'Discussion or news item successfully created!';
            // Optionally, redirect the user after success
            header('Location: index.php?to=communications/discussions');
            exit;
        } else {
            $error_message = 'Failed to create discussion or news item. Please try again.';
        }
    }
}
?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold">Start a New Discussion or Share News</h2>
        <p class="mt-4 text-lg">Share your thoughts, ask questions, or post updates for the Soli diaspora.</p>
    </div>
</section>

<!-- New Discussion/News Form -->
<section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="bg-white shadow-lg rounded-lg p-6">

        <!-- Display error or success messages -->
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                <?= htmlspecialchars($error_message); ?>
            </div>
        <?php elseif (!empty($success_message)): ?>
            <div class="bg-green-100 text-green-700 p-4 rounded mb-6">
                <?= htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="">
            <input type='hidden' name='user_id' value='<?= $_SESSION['user_id'] ?>'>

            <!-- Title -->
            <div class="mb-4">
                <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" name="title" id="title" class="mt-1 block w-full px-3 py-2 border rounded-md" required>
            </div>

            <!-- Content -->
            <div class="mb-4">
                <label for="content" class="block text-sm font-medium text-gray-700">Content</label>
                <textarea name="content" id="content" rows="5" class="mt-1 block w-full px-3 py-2 border rounded-md" required></textarea>
            </div>

            <!-- Is News -->
            <div class="mb-4">
                <label for="is_news" class="inline-flex items-center">
                    <input type="checkbox" name="is_news" id="is_news" class="mr-2">
                    This is a News item
                </label>
            </div>

            <!-- Sticky Option -->
            <div class="mb-4">
                <label for="is_sticky" class="inline-flex items-center">
                    <input type="checkbox" name="is_sticky" id="is_sticky" class="mr-2">
                    Make this a Sticky discussion
                </label>
            </div>

            <!-- Submit Button -->
            <div>
                <button type="submit" class="px-6 py-2 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
                    Submit
                </button>
            </div>

        </form>
    </div>
</section>
