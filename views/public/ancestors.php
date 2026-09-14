<?php
require_once __DIR__ . '/format.php';
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$pageNumber = min(100000, max(1, (int) ($_GET['p'] ?? 1)));
$listing = $publicAccess->directory($search, $pageNumber, $publicPreview);
$surnameGroups = [];
foreach ($listing['people'] as $person) {
    $surname = trim(str_replace('_', ' ', (string) $person['last_name']));
    $key = 'surname:' . mb_strtolower($surname, 'UTF-8');
    if (!isset($surnameGroups[$key])) {
        $surnameGroups[$key] = ['label' => $surname !== '' ? $surname : 'Surname not recorded', 'people' => []];
    }
    $surnameGroups[$key]['people'][] = $person;
}
?>
<section class="hero bg-deep-green text-white py-20" aria-labelledby="ancestors-heading">
    <div class="container hero-content">
        <h1 id="ancestors-heading" class="text-4xl font-bold">Our ancestors</h1>
        <p class="mt-4 text-lg">Discover our family history.</p>
        <p class="mt-4 text-lg">
            This public information is supplied for research and general information.
            It displays limited information about family ancestors who died more than
            <?= (int) $publicAccess->settings()['threshold_years'] ?> years ago.
        </p>
        <p class="mt-4">Some family records remain private. Select a name below to explore the information available publicly.</p>
    </div>
</section>

<main class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <?php if ($publicPreview): ?>
        <p class="p-4 mb-6 bg-yellow-100 rounded-lg" role="status">Administrator preview of the public directory.</p>
    <?php endif; ?>

    <div class="p-6 bg-white shadow-lg rounded-lg mb-8">
        <h2 class="text-2xl font-bold mb-4">Explore by surname</h2>
        <p class="mb-6">Surnames are listed alphabetically. Within each surname, individuals are ordered from oldest to newest by birth date, or death date where birth is unknown.</p>
        <form method="get" action="index.php" class="mb-4">
            <input type="hidden" name="to" value="public/ancestors">
            <?php if ($publicPreview): ?><input type="hidden" name="preview" value="1"><?php endif; ?>
            <label for="ancestor-search" class="block font-bold mb-2">Search names</label>
            <div class="flex flex-col sm:flex-row gap-4">
                <input id="ancestor-search" name="q" value="<?= ancestorEscape($search) ?>" maxlength="100" placeholder="First name or surname" class="w-full border border-gray-200 p-3 rounded-lg">
                <button class="bg-warm-red text-white rounded-lg px-6 py-3 hover:bg-burnt-orange transition">Search</button>
            </div>
        </form>
        <p class="text-gray-600"><?= (int) $listing['total'] ?> public <?= $listing['total'] === 1 ? 'record' : 'records' ?> found.<?php if ($listing['pages'] > 1 && $pageNumber <= $listing['pages']): ?> Page <?= $pageNumber ?> of <?= (int) $listing['pages'] ?>.<?php endif; ?></p>
    </div>

    <?php if (!$surnameGroups): ?>
        <div class="p-6 bg-white shadow-lg rounded-lg">
            <p><?= $listing['total'] > 0 ? 'There are no records on this page.' : ($search !== '' ? 'No public ancestors match your search.' : 'There are no public ancestor records available yet.') ?></p>
            <?php if ($search !== '' || $pageNumber > 1): ?>
                <a class="mt-4 inline-block text-blue-600 hover:text-blue-800 underline" href="index.php?to=public/ancestors<?= $publicPreview ? '&amp;preview=1' : '' ?>">Browse all public ancestors</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($surnameGroups as $group): ?>
        <section class="p-6 bg-white shadow-lg rounded-lg mb-8">
            <h2 class="text-2xl font-bold mb-4"><?= ancestorEscape($group['label']) ?></h2>
            <ul>
                <?php foreach ($group['people'] as $person): ?>
                    <li class="border-t border-gray-200 py-4">
                        <a class="text-xl text-blue-600 hover:text-blue-800 underline" href="<?= ancestorEscape(ancestorUrl((int) $person['id'], $publicPreview)) ?>"><?= ancestorEscape(ancestorName($person)) ?></a>
                        <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-gray-600">
                            <div><dt class="inline font-bold">Born:</dt> <dd class="inline"><?= ancestorEscape(ancestorDate($person, 'birth')) ?></dd></div>
                            <div><dt class="inline font-bold">Died:</dt> <dd class="inline"><?= ancestorEscape(ancestorDate($person, 'death')) ?></dd></div>
                        </dl>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>

    <?php if ($listing['pages'] > 1): ?>
        <nav aria-label="Directory pages" class="mt-6 flex gap-6">
            <?php foreach (['Previous' => $pageNumber - 1, 'Next' => $pageNumber + 1] as $label => $number): ?>
                <?php if ($number >= 1 && $number <= $listing['pages']): ?>
                    <a class="bg-warm-red text-white rounded-lg px-6 py-3 hover:bg-burnt-orange transition" href="index.php?<?= ancestorEscape(http_build_query(['to' => 'public/ancestors', 'q' => $search, 'p' => $number] + ($publicPreview ? ['preview' => '1'] : []))) ?>"><?= $label ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</main>
