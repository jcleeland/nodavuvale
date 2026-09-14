<?php
// Only included inside a member profile after the individual has been loaded.
if ($auth->getUserRole() !== 'admin') { return; }
require_once dirname(__DIR__) . '/public/format.php';
?>
<aside class="border rounded p-4 my-4">
    <p class="font-bold">Public access</p>
    <p><?= ancestorEscape($publicAccess->statusLabel($individual)) ?></p>
    <?php if ($publicAccess->ready()): ?>
    <form action="index.php?to=admin/public_access" method="post" class="my-2">
        <input type="hidden" name="csrf_token" value="<?= ancestorEscape(RequestSecurity::token()) ?>">
        <input type="hidden" name="action" value="public_exclusion">
        <input type="hidden" name="individual_id" value="<?= (int) $individual['id'] ?>">
        <label><input type="checkbox" name="excluded" value="1" <?= !empty($individual['exclude_from_public']) ? 'checked' : '' ?>> Exclude from public access</label>
        <button class="border rounded px-3 py-1">Save exclusion</button>
    </form>
    <?php if ($publicAccess->qualifies($individual)): ?><a class="underline" href="<?= ancestorEscape(ancestorUrl((int) $individual['id'], true)) ?>">Preview public profile</a><?php endif; ?>
    <?php else: ?><a class="underline" href="index.php?to=admin/migrations">Run database migrations</a><?php endif; ?>
</aside>
