<?php
require_once __DIR__ . '/format.php';
$relatives = $publicAccess->relatives((int) $publicPerson['id'], $publicPreview);
$visibleGroups = array_filter($relatives);
?>
<section class="hero bg-deep-green text-white py-20" aria-labelledby="public-individual-heading">
    <div class="container hero-content">
        <h1 id="public-individual-heading" class="text-4xl font-bold break-words"><?= ancestorEscape(ancestorName($publicPerson)) ?></h1>
        <?php if (!empty($publicPerson['aka_names'])): ?>
            <p class="mt-4 text-lg">Also known as <?= ancestorEscape(str_replace('_', ' ', $publicPerson['aka_names'])) ?></p>
        <?php endif; ?>
        <p class="mt-4 text-lg">A glimpse into our family history.</p>
        <p class="mt-4">Explore the life dates and public family connections recorded for this ancestor.</p>
    </div>
</section>

<main class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <?php if ($publicPreview): ?>
        <p class="p-4 mb-6 bg-yellow-100 rounded-lg" role="status">Administrator preview: this is the information available in the public view.</p>
    <?php endif; ?>
    <nav aria-label="Ancestor navigation" class="mb-8">
        <a class="text-blue-600 hover:text-blue-800 underline" href="index.php?to=public/ancestors<?= $publicPreview ? '&amp;preview=1' : '' ?>">Back to our ancestors</a>
    </nav>

    <section class="p-6 bg-white shadow-lg rounded-lg mb-8" aria-labelledby="life-details-heading">
        <h2 id="life-details-heading" class="text-2xl font-bold mb-6">Life details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="border-t border-gray-200 pt-4">
                <dt class="font-bold text-gray-600">Born</dt>
                <dd class="mt-2 text-xl"><?= ancestorEscape(ancestorDate($publicPerson, 'birth')) ?></dd>
            </div>
            <div class="border-t border-gray-200 pt-4">
                <dt class="font-bold text-gray-600">Died</dt>
                <dd class="mt-2 text-xl"><?= ancestorEscape(ancestorDate($publicPerson, 'death')) ?></dd>
            </div>
        </dl>
    </section>

    <?php if ($visibleGroups): ?>
        <section class="mb-8" aria-labelledby="family-connections-heading">
            <h2 id="family-connections-heading" class="text-2xl font-bold mb-4">Family connections</h2>
            <p class="mb-6">Explore relatives whose records are also available publicly.</p>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <?php foreach ($visibleGroups as $label => $people): ?>
                    <section class="p-6 bg-white shadow-lg rounded-lg">
                        <h3 class="text-xl font-bold mb-4"><?= ancestorEscape($label) ?></h3>
                        <ul>
                            <?php foreach ($people as $relative): ?>
                                <li class="border-t border-gray-200 py-4">
                                    <a class="text-xl text-blue-600 hover:text-blue-800 underline break-words" href="<?= ancestorEscape(ancestorUrl((int) $relative['id'], $publicPreview)) ?>"><?= ancestorEscape(ancestorName($relative)) ?></a>
                                    <p class="mt-2 text-gray-600">Born <?= ancestorEscape(ancestorDate($relative, 'birth')) ?></p>
                                    <p class="text-gray-600">Died <?= ancestorEscape(ancestorDate($relative, 'death')) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="p-6 bg-white shadow-lg rounded-lg" aria-labelledby="public-profile-heading">
        <h2 id="public-profile-heading" class="text-2xl font-bold mb-4">About this public profile</h2>
        <p>This limited family information is supplied for research and general information. Additional information is available to approved members.</p>
        <a class="mt-6 inline-block bg-warm-red text-white rounded-lg px-6 py-3 hover:bg-burnt-orange transition" href="index.php?to=login">Member login</a>
    </section>
</main>
