<?php
require_once __DIR__ . '/format.php';
$relatives = $publicAccess->relatives((int) $publicPerson['id'], $publicPreview);
?>
<main class="container mx-auto px-4 py-20">
    <?php if ($publicPreview): ?><p class="p-4 mb-4 bg-yellow-100">Administrator preview: this is the information available in the public view.</p><?php endif; ?>
    <a class="underline" href="index.php?to=public/ancestors<?= $publicPreview ? '&amp;preview=1' : '' ?>">Browse ancestors</a>
    <h1 class="text-3xl mt-6 mb-4"><?= ancestorEscape(ancestorName($publicPerson)) ?></h1>
    <?php if (!empty($publicPerson['aka_names'])): ?><p>Also known as <?= ancestorEscape(str_replace('_', ' ', $publicPerson['aka_names'])) ?></p><?php endif; ?>
    <dl class="my-6">
        <dt class="font-bold">Born</dt><dd><?= ancestorEscape(ancestorDate($publicPerson, 'birth')) ?></dd>
        <dt class="font-bold mt-3">Died</dt><dd><?= ancestorEscape(ancestorDate($publicPerson, 'death')) ?></dd>
    </dl>
    <?php foreach ($relatives as $label => $people): ?>
        <?php if ($people): ?>
        <h2 class="text-2xl mt-6 mb-3"><?= $label ?></h2>
        <ul class="space-y-2">
            <?php foreach ($people as $relative): ?>
            <li><a class="underline" href="<?= ancestorEscape(ancestorUrl((int) $relative['id'], $publicPreview)) ?>"><?= ancestorEscape(ancestorName($relative)) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    <?php endforeach; ?>
    <p class="mt-8">This public profile shows basic family history. Additional information is available to approved members.</p>
    <a class="underline" href="index.php?to=login">Member login</a>
</main>
