<?php
if ($auth->getUserRole() !== 'admin') { exit('Administrator access required.'); }
require_once dirname(__DIR__) . '/public/format.php';
$message = $_SESSION['public_admin_message'] ?? '';
unset($_SESSION['public_admin_message']);
?>
<main class="container mx-auto px-4 py-20">
    <h1 class="text-3xl mb-4">Database migrations</h1>
    <p class="mb-4">Review the pending database updates, then run them together. Completed migrations are skipped.</p>
    <p class="mb-4">Keep a current database backup before running updates. Structure changes cannot always be rolled back. Failed or interrupted migrations can be retried after their cause is resolved.</p>
    <?php if ($message): ?><p role="status" class="p-4 border mb-4"><?= ancestorEscape($message) ?></p><?php endif; ?>
    <?php if ($migrationError): ?><p role="alert"><?= ancestorEscape($migrationError) ?></p><?php endif; ?>
    <table class="w-full text-left mb-6">
        <thead><tr><th class="p-2">Migration</th><th class="p-2">Status</th><th class="p-2">Completed (UTC)</th></tr></thead>
        <tbody>
        <?php foreach ($migrationStates as $version => $migration): ?>
            <tr class="border-t">
                <td class="p-2"><strong><?= ancestorEscape($version) ?></strong><p><?= ancestorEscape($migration['description']) ?></p>
                <?php if ($migration['last_error']): ?><pre class="whitespace-pre-wrap"><?= ancestorEscape($migration['last_error']) ?></pre><?php endif; ?></td>
                <td class="p-2"><?= ancestorEscape($migration['status'] === 'running' ? 'Running or interrupted' : ucfirst($migration['status'])) ?></td>
                <td class="p-2"><?= ancestorEscape($migration['applied_at'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($migrationNotice && !$migrationError): ?>
    <form action="index.php?to=admin/migrations" method="post">
        <input type="hidden" name="csrf_token" value="<?= ancestorEscape(RequestSecurity::token()) ?>">
        <input type="hidden" name="action" value="run_migrations">
        <button class="bg-deep-green text-white rounded px-4 py-2">Run migration(s)</button>
    </form>
    <?php elseif (!$migrationError): ?><p>All database migrations have been applied.</p><?php endif; ?>
    <p class="mt-6"><a class="underline" href="index.php?to=admin/public_access">Manage public ancestor access</a></p>
</main>
