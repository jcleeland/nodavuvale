<?php
if ($auth->getUserRole() !== 'admin') { exit('Administrator access required.'); }
require_once dirname(__DIR__) . '/public/format.php';
$message = $_SESSION['public_admin_message'] ?? '';
unset($_SESSION['public_admin_message']);
?>
<main class="container mx-auto px-4 py-20">
    <h1 class="text-3xl mb-4">Public ancestor access</h1>
    <?php if ($message): ?><p role="status" class="p-4 border mb-4"><?= ancestorEscape($message) ?></p><?php endif; ?>
    <?php if (!$publicAccess->ready()): ?>
        <p>Public access is disabled until its database migration is complete.</p>
        <a class="underline" href="index.php?to=admin/migrations">View database migrations</a>
    <?php else:
        $settings = $publicAccess->settings();
        $search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
        $selectedId = max(0, (int) ($_GET['individual_id'] ?? 0));
        $pageNumber = min(100000, max(1, (int) ($_GET['p'] ?? 1)));
        $query = $db->connection()->prepare('SELECT id, first_names, last_name, death_prefix, death_year, death_month,
            death_date, exclude_from_public FROM individuals WHERE (? = 0 OR id = ?)
            AND LOCATE(?, REPLACE(CONCAT(first_names, \' \', last_name), \'_\', \' \')) > 0
            ORDER BY last_name, first_names, id LIMIT 31 OFFSET ' . (($pageNumber - 1) * 30));
        $query->execute([$selectedId, $selectedId, $search]);
        $people = $query->fetchAll(PDO::FETCH_ASSOC);
        $hasNext = count($people) > 30;
        $people = array_slice($people, 0, 30);
    ?>
        <p class="mb-4">Eligible ancestors become public automatically. Individual exclusions always take precedence. Public profiles include names, dates, and eligible relatives; stories, files, and member details remain private.</p>
        <form action="index.php?to=admin/public_access" method="post" class="border p-4 mb-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= ancestorEscape(RequestSecurity::token()) ?>">
            <input type="hidden" name="action" value="public_settings">
            <p><label><input type="checkbox" name="enabled" value="1" <?= (int) $settings['enabled'] === 1 ? 'checked' : '' ?>> Enable public ancestor access</label></p>
            <p><label>Years since death <input class="border p-2" type="number" min="1" max="500" name="threshold_years" value="<?= (int) $settings['threshold_years'] ?>" required></label></p>
            <p><label>Site timezone <input class="border p-2" name="timezone" value="<?= ancestorEscape($settings['timezone']) ?>" required></label></p>
            <p>Before enabling, verify that logged-out visitors cannot download private uploads through their direct URLs. Deployment instructions are in <code>docs/public-access.md</code>.</p>
            <button class="bg-deep-green text-white rounded px-4 py-2">Save settings</button>
        </form>
        <p class="mb-6"><a class="underline" href="index.php?to=public/ancestors&amp;preview=1">Preview the public directory</a></p>
        <h2 class="text-2xl mb-4">Individual exclusions</h2>
        <form action="index.php" method="get" class="mb-4">
            <input type="hidden" name="to" value="admin/public_access">
            <label>Find a person <input class="border p-2" name="q" value="<?= ancestorEscape($search) ?>" maxlength="100"></label>
            <button class="border rounded p-2">Search</button>
        </form>
        <?php foreach ($people as $person): ?>
        <div class="border-t py-4">
            <a class="font-bold underline" href="index.php?to=family/individual&amp;individual_id=<?= (int) $person['id'] ?>"><?= ancestorEscape(ancestorName($person)) ?></a>
            <p><?= ancestorEscape($publicAccess->statusLabel($person)) ?> · Died <?= ancestorEscape(ancestorDate($person, 'death')) ?></p>
            <form action="index.php?to=admin/public_access" method="post" class="my-2">
                <input type="hidden" name="csrf_token" value="<?= ancestorEscape(RequestSecurity::token()) ?>">
                <input type="hidden" name="action" value="public_exclusion">
                <input type="hidden" name="individual_id" value="<?= (int) $person['id'] ?>">
                <label><input type="checkbox" name="excluded" value="1" <?= (int) $person['exclude_from_public'] === 1 ? 'checked' : '' ?>> Exclude from public access</label>
                <button class="border rounded px-3 py-1">Save exclusion</button>
            </form>
            <?php if ($publicAccess->qualifies($person)): ?><a class="underline" href="<?= ancestorEscape(ancestorUrl((int) $person['id'], true)) ?>">Preview public profile</a><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <nav class="flex gap-6 mt-4" aria-label="Individuals pages">
        <?php foreach (['Previous' => $pageNumber - 1, 'Next' => $pageNumber + 1] as $label => $number): ?>
            <?php if ($number > 0 && ($label === 'Previous' || $hasNext)): ?>
            <a class="underline" href="index.php?<?= ancestorEscape(http_build_query(['to' => 'admin/public_access', 'q' => $search, 'p' => $number, 'individual_id' => $selectedId])) ?>"><?= $label ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</main>
