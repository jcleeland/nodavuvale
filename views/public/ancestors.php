<?php
require_once __DIR__ . '/format.php';
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$pageNumber = min(100000, max(1, (int) ($_GET['p'] ?? 1)));
$listing = $publicAccess->directory($search, $pageNumber, $publicPreview);
?>
<main class="container mx-auto px-4 py-20">
    <h1 class="text-3xl mb-4">Our ancestors</h1>
    <?php if ($publicPreview): ?><p class="p-4 mb-4 bg-yellow-100">Administrator preview of the public directory.</p><?php endif; ?>
    <p class="mb-6">Explore family records whose recorded deaths are more than <?= (int) $publicAccess->settings()['threshold_years'] ?> years ago. Some records remain private.</p>
    <form method="get" action="index.php" class="mb-6">
        <input type="hidden" name="to" value="public/ancestors">
        <?php if ($publicPreview): ?><input type="hidden" name="preview" value="1"><?php endif; ?>
        <label for="ancestor-search">Search names</label>
        <input id="ancestor-search" name="q" value="<?= ancestorEscape($search) ?>" maxlength="100" class="border rounded p-2">
        <button class="bg-deep-green text-white rounded p-2">Search</button>
    </form>
    <p class="mb-4"><?= (int) $listing['total'] ?> public records found.</p>
    <ul class="space-y-4">
        <?php foreach ($listing['people'] as $person): ?>
        <li class="border-b pb-4">
            <a class="text-xl underline" href="<?= ancestorEscape(ancestorUrl((int) $person['id'], $publicPreview)) ?>"><?= ancestorEscape(ancestorName($person)) ?></a>
            <p>Born <?= ancestorEscape(ancestorDate($person, 'birth')) ?> · Died <?= ancestorEscape(ancestorDate($person, 'death')) ?></p>
        </li>
        <?php endforeach; ?>
    </ul>
    <nav aria-label="Directory pages" class="mt-6 flex gap-6">
        <?php foreach (['Previous' => $pageNumber - 1, 'Next' => $pageNumber + 1] as $label => $number): ?>
            <?php if ($number >= 1 && $number <= $listing['pages']): ?>
            <a class="underline" href="index.php?<?= ancestorEscape(http_build_query(['to' => 'public/ancestors', 'q' => $search, 'p' => $number] + ($publicPreview ? ['preview' => '1'] : []))) ?>"><?= $label ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</main>
