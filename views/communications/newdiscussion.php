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

        <?php if ($discussionError !== ''): ?>
            <div role="alert" class="bg-red-100 text-red-700 p-4 rounded mb-6">
                <?= htmlspecialchars($discussionError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(RequestSecurity::token(), ENT_QUOTES, 'UTF-8') ?>">

            <!-- Title -->
            <div class="mb-4">
                <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" name="title" id="title" class="mt-1 block w-full px-3 py-2 border rounded-md" maxlength="255" value="<?= htmlspecialchars($discussionDraft['title'], ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <!-- Content -->
            <div class="mb-4">
                <label for="content" class="block text-sm font-medium text-gray-700">Content</label>
                <textarea name="content" id="content" rows="5" class="mt-1 block w-full px-3 py-2 border rounded-md" required><?= htmlspecialchars($discussionDraft['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <!-- Is News -->
            <div class="mb-4">
                <label for="is_news" class="inline-flex items-center">
                    <input type="checkbox" name="is_news" id="is_news" class="mr-2" <?= $discussionDraft['is_news'] ? 'checked' : '' ?>>
                    This is a News item
                </label>
            </div>

            <!-- Sticky Option -->
            <div class="mb-4">
                <label for="is_sticky" class="inline-flex items-center">
                    <input type="checkbox" name="is_sticky" id="is_sticky" class="mr-2" <?= $discussionDraft['is_sticky'] ? 'checked' : '' ?>>
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
