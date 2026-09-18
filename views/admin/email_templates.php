<?php
if (!isset($admin_page) || !$admin_page) {
    $admin_backload = true;
    require_once('views/admin/index.php');
}

require_once __DIR__ . '/../../system/EmailTemplate.php';

$templateDefinitions = EmailTemplate::definitions();
$templateMessage = null;
$templateError = null;
$pendingTemplate = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_email_template') {
    if (!RequestSecurity::validToken($_POST['csrf_token'] ?? null)) {
        $templateError = 'The form expired. Reload the page and try again.';
    } else {
        $templateName = (string) ($_POST['template_name'] ?? '');
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $pendingTemplate = ['name' => $templateName, 'subject' => $subject, 'body' => $body];

        if (!isset($templateDefinitions[$templateName])) {
            $templateError = 'Unknown email template.';
        } elseif ($subject === '' || $body === '') {
            $templateError = 'The subject and message are required.';
        } elseif (str_contains($subject, "\r") || str_contains($subject, "\n") || strlen($subject) > 255) {
            $templateError = 'The subject must be one line and no more than 255 characters.';
        } elseif (strlen($body) > 60000) {
            $templateError = 'The message is too long. Keep it under 60,000 bytes.';
        } else {
            $definition = $templateDefinitions[$templateName];
            $subjectSaved = $db->setSiteSetting($definition['subject_setting'], $subject);
            $bodySaved = $db->setSiteSetting($definition['body_setting'], $body);
            if ($subjectSaved && $bodySaved) {
                $templateMessage = $definition['label'] . ' template saved.';
                $pendingTemplate = null;
            } else {
                $templateError = 'The template could not be saved. Check the server log and try again.';
            }
        }
    }
}

$siteSettings = $db->getSiteSettings();
?>

<section class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h1 class="text-4xl font-bold mb-3">User Email Templates</h1>
    <p class="mb-6">Edit the automatic HTML emails sent from the administration account. Placeholder values are safely inserted when a message is sent.</p>

    <?php if ($templateMessage): ?>
        <div class="mb-6 p-4 rounded bg-green-100 text-green-800"><?= htmlspecialchars($templateMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($templateError): ?>
        <div class="mb-6 p-4 rounded bg-red-100 text-red-800"><?= htmlspecialchars($templateError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php foreach ($templateDefinitions as $templateName => $definition):
        $template = EmailTemplate::template($siteSettings, $templateName);
        if ($pendingTemplate && $pendingTemplate['name'] === $templateName) {
            $template = ['subject' => $pendingTemplate['subject'], 'body' => $pendingTemplate['body']];
        }
    ?>
        <form action="index.php?to=admin/&amp;section=email_templates" method="POST" class="mb-8 p-6 bg-white shadow-lg rounded-lg" onsubmit="if (typeof tinymce !== 'undefined') { tinymce.triggerSave(); }">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(RequestSecurity::token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="save_email_template">
            <input type="hidden" name="template_name" value="<?= htmlspecialchars($templateName, ENT_QUOTES, 'UTF-8') ?>">

            <h2 class="text-2xl font-bold mb-2"><?= htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mb-4 text-gray-700"><?= htmlspecialchars($definition['description'], ENT_QUOTES, 'UTF-8') ?></p>

            <div class="mb-4">
                <label for="subject_<?= htmlspecialchars($templateName, ENT_QUOTES, 'UTF-8') ?>" class="block text-gray-700 font-semibold">Subject</label>
                <input type="text" id="subject_<?= htmlspecialchars($templateName, ENT_QUOTES, 'UTF-8') ?>" name="subject" maxlength="255" class="w-full px-4 py-2 border rounded-lg" value="<?= htmlspecialchars($template['subject'], ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="mb-3">
                <label for="body_<?= htmlspecialchars($templateName, ENT_QUOTES, 'UTF-8') ?>" class="block text-gray-700 font-semibold">Message</label>
                <textarea id="body_<?= htmlspecialchars($templateName, ENT_QUOTES, 'UTF-8') ?>" name="body" rows="16" class="email-template-body w-full px-4 py-2 border rounded-lg" required><?= htmlspecialchars($template['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
            </div>

            <p class="mb-4 text-sm text-gray-600">
                Available placeholders:
                <?php foreach ($definition['tokens'] as $token): ?>
                    <code class="inline-block bg-gray-100 px-1 rounded mr-1">{{<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>}}</code>
                <?php endforeach; ?>
            </p>

            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">Save <?= htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') ?></button>
        </form>
    <?php endforeach; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof tinymce === 'undefined') return;
    tinymce.init({
        selector: '.email-template-body',
        height: 420,
        menubar: false,
        plugins: 'lists link code',
        toolbar: 'undo redo | bold italic | bullist numlist | link | code',
        convert_urls: false
    });
});
</script>
