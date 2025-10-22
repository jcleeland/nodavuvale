<?php

// Check if the user is logged in
$is_logged_in = isset($_SESSION['user_id']);
// Set the default "view new" as being the last login time
$viewnewsince=isset($_SESSION['last_login']) ? date("Y-m-d H:i:s", strtotime('-1 day', strtotime($_SESSION['last_login']))) : date("Y-m-d H:i:s", strtotime('1 week ago'));
if(isset($_GET['changessince']) && $_GET['changessince'] != "lastlogin") {
    $viewnewsince=$_GET['changessince'];
}   

?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold">Welcome to <i><?= $site_name ?></i></h2>
        <p class="mt-4 text-lg">Connecting our family and preserving our cultural heritage.</p>
        <?php if (!$is_logged_in): ?>
            <!-- Button-styled anchor tag -->
            <a href="?to=about/aboutvirtualnataleira" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
                Our website
            </a>
            <a href="?to=about/aboutnataleira" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
                Our village
            </a>
        <?php else: ?>
            <a href="?to=family/tree" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
                Browse The Tree
            </a>
            <a href="?to=communications/discussions" class="mt-8 inline-block px-6 py-3 bg-warm-red text-white rounded-lg hover:bg-burnt-orange transition">
                Chat with Family
            </a>
        <?php endif; ?>

    </div>
</section>

<!-- Conditional Content Section Based on Login Status -->
<?php if ($is_logged_in): ?>
<?php
    $changes=Utils::getNewStuff($user_id, $viewnewsince, "items.updated ASC");
    $summary=array();
    $summary['Discussions']=count($changes['discussions'])>0 ? count($changes['discussions'])." new discussions" : "";
    $summary['Individuals']=count($changes['individuals'])>0 ? count($changes['individuals'])." new individuals" : "";
    $summary['Relationships']=count($changes['relationships'])>0 ? count($changes['relationships'])." new relationships" : "";
    $summary['Items']=count($changes['items'])>0 ? count($changes['items'])." new items" : "";
    $summary['Files']=count($changes['files'])>0 ? count($changes['files'])." new files" : "";

    $item_types = Utils::getItemTypes();
    $item_styles= Utils::getItemStyles();

    $feedEntries = [];
    $feedTypeMeta = [
        'discussion' => ['label' => 'Discussion update', 'icon' => 'fas fa-comments'],
        'comment'    => ['label' => 'New comment',       'icon' => 'fas fa-comment-dots'],
        'individual' => ['label' => 'New family member', 'icon' => 'fas fa-user-plus'],
        'item'       => ['label' => 'Story update',      'icon' => 'fas fa-book-open'],
        'file'       => ['label' => 'New file',          'icon' => 'fas fa-photo-video'],
    ];

    $createSnippet = function ($text, $wordLimit = 24) {
        $clean = trim(strip_tags((string) $text));
        if ($clean === '') {
            return '';
        }
        $words = preg_split('/\s+/', $clean);
        if (!is_array($words) || count($words) === 0) {
            return '';
        }
        if (count($words) <= $wordLimit) {
            return implode(' ', $words);
        }
        return implode(' ', array_slice($words, 0, $wordLimit)) . '...';
    };

    foreach ($changes['discussions'] as $discussion) {
        $timestampString = $discussion['updated_at'] ?? null;
        if (!$timestampString) {
            continue;
        }
        $timestamp = strtotime($timestampString);
        if (!$timestamp) {
            continue;
        }
        $isTreeDiscussion = !empty($discussion['individual_id']);
        $feedEntries[] = [
            'type'      => $discussion['change_type'] === 'comment' ? 'comment' : 'discussion',
            'title'     => stripslashes($discussion['title']),
            'content'   => $createSnippet($discussion['content'], 28),
            'meta'      => [
                'actor_name' => trim(($discussion['user_first_name'] ?? '') . ' ' . ($discussion['user_last_name'] ?? '')),
                'actor_id'   => $discussion['user_id'] ?? null,
                'context'    => $isTreeDiscussion ? 'Family Tree Chat' : 'Community Chat',
            ],
            'url'       => $isTreeDiscussion
                ? "?to=family/individual&individual_id={$discussion['individual_id']}&discussion_id={$discussion['discussionId']}"
                : "?to=communications/discussions&discussion_id={$discussion['discussionId']}",
            'timestamp' => $timestamp,
            'raw_time'  => $timestampString,
        ];
    }

    foreach ($changes['individuals'] as $individual) {
        $timestampString = $individual['updated'] ?? $individual['created'] ?? null;
        if (!$timestampString) {
            continue;
        }
        $timestamp = strtotime($timestampString);
        if (!$timestamp) {
            continue;
        }
        $personName = trim(($individual['tree_first_name'] ?? '') . ' ' . ($individual['tree_last_name'] ?? ''));
        $feedEntries[] = [
            'type'       => 'individual',
            'title'      => $personName !== '' ? $personName : 'New family member',
            'content'    => 'Added to the family tree.',
            'meta'       => [
                'actor_name'   => trim(($individual['user_first_name'] ?? '') . ' ' . ($individual['user_last_name'] ?? '')),
                'actor_id'     => $individual['created_by'] ?? null,
                'subject_name' => $personName,
            ],
            'cover'      => $individual['keyimagepath'] ?: 'images/default_avatar.webp',
            'url'        => "?to=family/individual&individual_id={$individual['individualId']}",
            'timestamp'  => $timestamp,
            'raw_time'   => $timestampString,
        ];
    }

    $itemGroupings = [];
    foreach ($changes['items'] as $key => $itemGroup) {
        if (isset($itemGroup['items']) && is_array($itemGroup['items']) && count($itemGroup['items']) > 0) {
            foreach ($itemGroup['items'] as $item) {
                $groupKey = 'group_' . ($item['unique_id'] ?? $item['item_id']);
                if (!isset($itemGroupings[$groupKey])) {
                    $itemGroupings[$groupKey] = [
                        'items'            => [],
                        'privacy'          => $itemGroup['privacy'] ?? 'private',
                        'group_identifier' => $key,
                    ];
                }
                $itemGroupings[$groupKey]['items'][] = $item;
            }
        } else {
            foreach ($itemGroup as $item) {
                $groupKey = 'item_' . ($item['item_id'] ?? uniqid('', true));
                if (!isset($itemGroupings[$groupKey])) {
                    $itemGroupings[$groupKey] = [
                        'items'   => [],
                        'privacy' => $itemGroup['privacy'] ?? 'private',
                    ];
                }
                $itemGroupings[$groupKey]['items'][] = $item;
            }
        }
    }

    foreach ($itemGroupings as $itemGroup) {
        if (empty($itemGroup['items'])) {
            continue;
        }
        $firstItem = $itemGroup['items'][0];
        $timestampString = $firstItem['updated'] ?? $firstItem['item_updated'] ?? null;
        if (!$timestampString) {
            continue;
        }
        $timestamp = strtotime($timestampString);
        if (!$timestamp) {
            continue;
        }
        $personName = trim(($firstItem['tree_first_names'] ?? '') . ' ' . ($firstItem['tree_last_name'] ?? ''));
        $groupTitle = !empty($firstItem['item_group_name'])
            ? $firstItem['item_group_name']
            : ($firstItem['detail_type'] ?? 'Update');
        $snippet = '';
        $detailRows = [];
        $detailRowKeys = [];
        $mediaItems = [];
        $mediaKeys = [];
        $fileLinks = [];
        $fileKeys = [];
        $addDetailRow = function ($label, $value, $link = '', $isHtml = false) use (&$detailRows, &$detailRowKeys) {
            $normalizedLabel = trim((string) $label);
            $normalizedValue = is_string($value) ? trim($value) : (is_null($value) ? '' : (string) $value);
            if ($normalizedLabel === '' || $normalizedValue === '') {
                return;
            }
            $key = strtolower($normalizedLabel) . '|' . strtolower($normalizedValue) . '|' . strtolower((string) $link) . '|' . ($isHtml ? '1' : '0');
            if (isset($detailRowKeys[$key])) {
                return;
            }
            $detailRowKeys[$key] = true;
            $detailRows[] = [
                'label' => $normalizedLabel,
                'value' => $value,
                'link'  => $link,
                'is_html' => $isHtml,
            ];
        };
        foreach ($itemGroup['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $detailType = $item['detail_type'] ?? '';
            $detailLabel = $detailType ? $detailType : ($item['item_group_name'] ?? $groupTitle);
            $detailStyle = $item_styles[$detailType] ?? 'text';
            $detailValue = isset($item['detail_value']) ? trim((string) $item['detail_value']) : '';

            if (!empty($item['file_path'])) {
                $fileDesc = trim((string) ($item['file_description'] ?? $detailLabel));
                $fileType = strtolower((string) ($item['file_type'] ?? ''));
                if ($fileType === 'image') {
                    $mediaKey = $item['file_path'];
                    if (!isset($mediaKeys[$mediaKey])) {
                        $mediaKeys[$mediaKey] = true;
                        $mediaItems[] = [
                            'src' => $item['file_path'],
                            'alt' => $fileDesc !== '' ? $fileDesc : $groupTitle,
                        ];
                    }
                } else {
                    $fallbackLabel = strtoupper((string) ($item['file_format'] ?? $fileType));
                    if ($fallbackLabel === '') {
                        $fallbackLabel = 'Download file';
                    }
                    $fileKey = $item['file_path'];
                    if (!isset($fileKeys[$fileKey])) {
                        $fileKeys[$fileKey] = true;
                        $fileLinks[] = [
                            'url'   => $item['file_path'],
                            'label' => $fileDesc !== '' ? $fileDesc : $fallbackLabel,
                        ];
                    }
                }
                if ($snippet === '' && $fileDesc !== '') {
                    $snippet = $createSnippet($fileDesc, 18);
                }
            }

            if ($detailValue === '' && $detailStyle !== 'file') {
                continue;
            }

            if ($snippet === '' && $detailValue !== '' && $detailType !== 'Private') {
                $snippet = $createSnippet($detailValue, 20);
            }

            if ($detailStyle === 'file') {
                continue;
            }

            if ($detailStyle === 'individual') {
                $linkTarget = !empty($item['individual_name_id'])
                    ? "?to=family/individual&individual_id={$item['individual_name_id']}"
                    : '';
                $individualName = trim((string) ($item['individual_name'] ?? $detailValue));
                $addDetailRow($detailLabel, $individualName, $linkTarget, false);
                continue;
            }

            $displayValue = $detailValue;
            $allowsBreaks = ($detailStyle === 'textarea');
            $detailLength = function_exists('mb_strlen') ? mb_strlen($detailValue) : strlen($detailValue);
            if ($detailStyle === 'textarea' || $detailLength > 360) {
                $displayValue = $createSnippet($detailValue, 60);
            } elseif ($detailStyle === 'date') {
                $dateCandidate = strtotime($detailValue);
                if ($dateCandidate) {
                    $displayValue = date('j M Y', $dateCandidate);
                }
            }

            $addDetailRow($detailLabel, $displayValue, '', $allowsBreaks);
        }
        if ($snippet === '' && !empty($firstItem['file_path'])) {
            $snippet = 'New file attached.';
        }
        $feedEntries[] = [
            'type'      => 'item',
            'title'     => trim($groupTitle) !== '' ? $groupTitle . ' update for ' . $personName : 'New update for ' . $personName,
            'content'   => $snippet,
            'meta'      => [
                'actor_name'   => trim(($firstItem['first_name'] ?? '') . ' ' . ($firstItem['last_name'] ?? '')),
                'actor_id'     => $firstItem['user_id'] ?? null,
                'subject_name' => $personName,
                'privacy'      => $itemGroup['privacy'] ?? 'private',
            ],
            'url'       => "?to=family/individual&individual_id={$firstItem['individualId']}&tab=generaltab",
            'timestamp' => $timestamp,
            'raw_time'  => $timestampString,
            'details'   => $detailRows,
            'media'     => $mediaItems,
            'files'     => $fileLinks,
        ];
    }

    foreach ($changes['files'] as $file) {
        $timestampString = $file['upload_date'] ?? $file['updated'] ?? null;
        if (!$timestampString) {
            continue;
        }
        $timestamp = strtotime($timestampString);
        if (!$timestamp) {
            continue;
        }
        $personName = trim(($file['tree_first_name'] ?? '') . ' ' . ($file['tree_last_name'] ?? ''));
        $feedEntries[] = [
            'type'      => 'file',
            'title'     => $personName !== '' ? $personName . ' media added' : 'New media uploaded',
            'content'   => $createSnippet($file['file_description'] ?? '', 18),
            'meta'      => [
                'actor_name'   => trim(($file['user_first_name'] ?? '') . ' ' . ($file['user_last_name'] ?? '')),
                'actor_id'     => $file['user_id'] ?? null,
                'subject_name' => $personName,
                'file_type'    => $file['file_type'] ?? '',
            ],
            'url'       => "?to=family/individual&individual_id={$file['individualId']}&tab=mediatab&file_id={$file['id']}",
            'timestamp' => $timestamp,
            'raw_time'  => $timestampString,
            'media'     => $file['file_path'] ?? '',
        ];
    }

    usort($feedEntries, function ($a, $b) {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });

    $user = Utils::getUser($user_id);
    $dashboardLayout = 'dropdown';
    $dashboardDropdownMeta = [
        'notifications' => 0,
        'profile'       => 0,
        'controls'      => 0,
    ];
    ob_start();
    include("family/helpers/user.php");
    $dashboardDropdownPanels = trim(ob_get_clean());
    $hasDropdownPanels = trim($dashboardDropdownPanels) !== '';
    $changeSince = isset($_GET['changessince']) && $_GET['changessince'] !== '' ? $_GET['changessince'] : 'lastlogin';

    $descendancy = null;
    if($user && !empty($user['individuals_id'])) {
        $descendancy=Utils::getLineOfDescendancy(Web::getRootId(), $user['individuals_id']);
    }
?>
<?php if(isset($descendancy) && $descendancy): ?>
    <section class="container mx-auto pt-6 pb-2 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap justify-center items-center text-xxs sm:text-sm">
            <?php foreach($descendancy as $index => $descendant): ?>
                <div class="bg-burnt-orange-800 nv-bg-opacity-20 text-center p-1 sm:p-2 my-1 sm:my-2 rounded-lg">
                    <a href='?to=family/individual&individual_id=<?= $descendant[1] ?>'><?= $descendant[0] ?></a>
                </div>
                <?php if ($index < count($descendancy) - 1): ?>
                    <i class="fas fa-arrow-right mx-2"></i>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($hasDropdownPanels): ?>
    <section class="container mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        <div class="dashboard-toolbar flex flex-wrap items-center gap-4">
            <button type="button" class="dashboard-toolbar-button" data-dashboard-trigger="notifications" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <span class="dashboard-toolbar-label">Notifications</span>
                <?php if (!empty($dashboardDropdownMeta['notifications'])): ?>
                    <span class="dashboard-badge"><?= (int) $dashboardDropdownMeta['notifications'] ?></span>
                <?php endif; ?>
            </button>
            <button type="button" class="dashboard-toolbar-button" data-dashboard-trigger="profile" aria-label="Your profile" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-user"></i>
                <span class="dashboard-toolbar-label">Your profile</span>
                <?php if (!empty($dashboardDropdownMeta['profile'])): ?>
                    <span class="dashboard-badge"><?= (int) $dashboardDropdownMeta['profile'] ?></span>
                <?php endif; ?>
            </button>
            <button type="button" class="dashboard-toolbar-button" data-dashboard-trigger="controls" aria-label="Your controls" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-cog"></i>
                <span class="dashboard-toolbar-label">Your controls</span>
                <?php if (!empty($dashboardDropdownMeta['controls'])): ?>
                    <span class="dashboard-badge"><?= (int) $dashboardDropdownMeta['controls'] ?></span>
                <?php endif; ?>
            </button>
        </div>
        <div class="dashboard-dropdown-panels mt-4" id="dashboardDropdownPanels">
            <?= $dashboardDropdownPanels ?>
        </div>
    </section>
<?php endif; ?>

    <section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8 pt-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <h3 class="text-2xl font-bold text-ocean-blue flex items-center gap-3">
                <i class="fas fa-stream"></i>
                Latest updates
            </h3>
            <div class="flex items-center gap-2 feed-controls">
                <button type="button" class="feed-date-toggle text-sm text-ocean-blue hover:text-burnt-orange" onclick="toggleDateSelect()">
                    <i class="fas fa-clock mr-2"></i>
                    Change window
                </button>
                <select id="dateSelect" class="hidden border rounded px-2 py-1 text-sm" onchange="reloadWithDate()" onfocus="storeOriginalValue()" onblur="hideIfSameOption()">
                    <option value="lastlogin" <?= $changeSince === 'lastlogin' ? 'selected' : '' ?>>Since your last login</option>
                    <option value="<?= date("Y-m-d", strtotime('-1 week')) ?>" <?= $changeSince === date("Y-m-d", strtotime('-1 week')) ? 'selected' : '' ?>>The last week (since <?= date("l, d F Y", strtotime('-1 week')) ?>)</option>
                    <option value="<?= date("Y-m-d", strtotime('-2 weeks')) ?>" <?= $changeSince === date("Y-m-d", strtotime('-2 weeks')) ? 'selected' : '' ?>>The last fortnight (since <?= date("l, d F Y", strtotime('-2 weeks')) ?>)</option>
                    <option value="<?= date("Y-m-d", strtotime('-1 month')) ?>" <?= $changeSince === date("Y-m-d", strtotime('-1 month')) ? 'selected' : '' ?>>The last month (since <?= date("l, d F Y", strtotime('-1 month')) ?>)</option>
                </select>
            </div>
        </div>
        <?php if (empty($feedEntries)): ?>
            <div class="feed-empty text-center text-gray-500 bg-white shadow-sm border rounded-lg py-12">No updates to show right now.</div>
        <?php else: ?>
            <div class="feed-stream space-y-4">
                <?php foreach ($feedEntries as $entry): ?>
                    <?php
                        $type = $entry['type'];
                        $meta = $feedTypeMeta[$type] ?? ['label' => ucfirst($type), 'icon' => 'fas fa-circle'];
                        $timestampLabel = isset($entry['timestamp']) ? date('l, d F Y g:ia', $entry['timestamp']) : '';
                        $actorInitial = '';
                        if (empty($entry['meta']['actor_id']) && !empty($entry['meta']['actor_name'])) {
                            $actorInitial = strtoupper(substr($entry['meta']['actor_name'], 0, 1));
                        }
                    ?>
                    <article class="feed-item bg-white shadow-sm border border-gray-200 rounded-lg p-4 flex gap-4 items-start" data-feed-type="<?= htmlspecialchars($type) ?>">
                        <div class="feed-item-icon text-xl text-ocean-blue flex-shrink-0">
                            <i class="<?= htmlspecialchars($meta['icon']) ?>"></i>
                        </div>
                        <div class="feed-item-body flex-1">
                            <div class="feed-item-meta flex items-center gap-3 text-sm text-gray-500 mb-2">
                                <?php if (!empty($entry['meta']['actor_id'])): ?>
                                    <?= $web->getAvatarHTML($entry['meta']['actor_id'], "sm", "feed-item-avatar"); ?>
                                <?php elseif ($actorInitial !== ''): ?>
                                    <div class="feed-item-avatar-placeholder"><?= htmlspecialchars($actorInitial) ?></div>
                                <?php endif; ?>
                                <span class="font-semibold text-brown"><?= htmlspecialchars($meta['label']) ?></span>
                                <?php if (!empty($entry['meta']['context'])): ?>
                                    <span class="hidden sm:inline">&bull; <?= htmlspecialchars($entry['meta']['context']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($entry['meta']['subject_name']) && $type !== 'individual'): ?>
                                    <span class="hidden sm:inline">&bull; <?= htmlspecialchars($entry['meta']['subject_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($entry['raw_time'])): ?>
                                    <span class="feed-item-timestamp ml-auto" title="<?= htmlspecialchars($timestampLabel) ?>">
                                        <?= $web->timeSince($entry['raw_time']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($entry['title'])): ?>
                                <h4 class="feed-item-title text-lg font-semibold text-brown mb-1">
                                    <?php if (!empty($entry['url'])): ?>
                                        <a class="hover:text-burnt-orange" href="<?= $entry['url'] ?>"><?= htmlspecialchars($entry['title']) ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($entry['title']) ?>
                                    <?php endif; ?>
                                </h4>
                            <?php endif; ?>
                            <?php if (!empty($entry['content'])): ?>
                                <p class="feed-item-summary text-sm text-gray-600 mb-3"><?= htmlspecialchars($entry['content']) ?></p>
                            <?php endif; ?>
                            <?php if ($type === 'item' && !empty($entry['media'])): ?>
                                <div class="feed-item-media-grid mb-3">
                                    <?php foreach ($entry['media'] as $media): ?>
                                        <img src="<?= htmlspecialchars($media['src']) ?>" alt="<?= htmlspecialchars($media['alt']) ?>" class="feed-item-thumb feed-item-media-image">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($type === 'item' && !empty($entry['details'])): ?>
                                <ul class="feed-item-details text-sm text-gray-700 mb-3">
                                    <?php foreach ($entry['details'] as $detail): ?>
                                        <li class="feed-item-detail-row">
                                            <span class="feed-item-detail-label"><?= htmlspecialchars($detail['label']) ?>:</span>
                                            <?php if (!empty($detail['link'])): ?>
                                                <a href="<?= htmlspecialchars($detail['link']) ?>" class="feed-item-detail-link hover:text-burnt-orange"><?= htmlspecialchars($detail['value']) ?></a>
                                            <?php else: ?>
                                                <span class="feed-item-detail-text">
                                                    <?php if (!empty($detail['is_html'])): ?>
                                                        <?= nl2br(htmlspecialchars($detail['value'])) ?>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($detail['value']) ?>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ($type === 'item' && !empty($entry['files'])): ?>
                                <ul class="feed-item-files text-xs text-ocean-blue mb-3">
                                    <?php foreach ($entry['files'] as $file): ?>
                                        <li><a href="<?= htmlspecialchars($file['url']) ?>" class="hover:text-burnt-orange" target="_blank" rel="noopener"><?= htmlspecialchars($file['label']) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <div class="feed-item-footer flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                <?php if (!empty($entry['meta']['actor_name'])): ?>
                                    <span>by <?= htmlspecialchars($entry['meta']['actor_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($type === 'item' && !empty($entry['meta']['privacy']) && $entry['meta']['privacy'] === 'private'): ?>
                                    <span class="inline-flex items-center gap-1 text-warm-red"><i class="fas fa-lock"></i> Private</span>
                                <?php endif; ?>
                                <?php if ($type === 'file' && !empty($entry['media']) && ($entry['meta']['file_type'] ?? '') === 'image'): ?>
                                    <img src="<?= htmlspecialchars($entry['media']) ?>" alt="Preview" class="feed-item-thumb h-12 w-12 object-cover rounded-md border">
                                <?php endif; ?>
                                <?php if ($type === 'individual' && !empty($entry['cover'])): ?>
                                    <img src="<?= htmlspecialchars($entry['cover']) ?>" alt="Profile" class="feed-item-thumb h-12 w-12 object-cover rounded-md border">
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <script>
        (function () {
            var dateSelect = document.getElementById('dateSelect');
            var originalDateValue = dateSelect ? dateSelect.value : null;
            var selectDefault = "<?= htmlspecialchars($changeSince, ENT_QUOTES, 'UTF-8') ?>";
            var toolbar = document.querySelector('.dashboard-toolbar');
            var panelHost = document.getElementById('dashboardDropdownPanels');
            if (dateSelect && selectDefault) {
                dateSelect.value = selectDefault;
                originalDateValue = selectDefault;
            }
            window.toggleDateSelect = function () {
                if (!dateSelect) {
                    return;
                }
                dateSelect.classList.toggle('hidden');
                if (!dateSelect.classList.contains('hidden')) {
                    dateSelect.focus();
                }
            };
            window.reloadWithDate = function () {
                if (!dateSelect) {
                    return;
                }
                var params = new URLSearchParams(window.location.search);
                var selectedValue = dateSelect.value;
                if (!selectedValue || selectedValue === 'lastlogin') {
                    params.delete('changessince');
                } else {
                    params.set('changessince', selectedValue);
                }
                var query = params.toString();
                var newUrl = window.location.pathname + (query ? '?' + query : '');
                window.location.href = newUrl;
            };
            window.storeOriginalValue = function () {
                if (dateSelect) {
                    originalDateValue = dateSelect.value;
                }
            };
            window.hideIfSameOption = function () {
                if (dateSelect && dateSelect.value === originalDateValue) {
                    dateSelect.classList.add('hidden');
                }
            };
            function positionPanel(button, panel) {
                if (!toolbar || !panelHost || !panel) {
                    return;
                }
                var hostRect = panelHost.getBoundingClientRect();
                var buttonRect = button.getBoundingClientRect();
                var panelRect = panel.getBoundingClientRect();
                var left = buttonRect.left - hostRect.left;
                var top = buttonRect.bottom - hostRect.top + 12;
                var maxLeft = window.innerWidth - panelRect.width - 16 - hostRect.left;
                if (!isNaN(maxLeft)) {
                    left = Math.min(left, Math.max(0, maxLeft));
                }
                if (left < 0) {
                    left = 0;
                }
                panel.style.left = left + 'px';
                panel.style.top = top + 'px';
            }
            var triggers = document.querySelectorAll('[data-dashboard-trigger]');
            var panels = document.querySelectorAll('.dashboard-dropdown-panel');
            function closePanels() {
                panels.forEach(function (panel) {
                    panel.classList.remove('active');
                    panel.style.left = '';
                    panel.style.top = '';
                    panel.style.visibility = '';
                });
                triggers.forEach(function (button) {
                    button.classList.remove('active');
                    button.setAttribute('aria-expanded', 'false');
                });
            }
            triggers.forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    var target = button.getAttribute('data-dashboard-trigger');
                    var panel = document.querySelector('.dashboard-dropdown-panel[data-panel="' + target + '"]');
                    if (!panel) {
                        return;
                    }
                    var alreadyOpen = panel.classList.contains('active');
                    closePanels();
                    if (!alreadyOpen) {
                        panel.classList.add('active');
                        button.classList.add('active');
                        button.setAttribute('aria-expanded', 'true');
                        panel.style.visibility = 'hidden';
                        requestAnimationFrame(function () {
                            positionPanel(button, panel);
                            panel.style.visibility = '';
                        });
                    }
                });
            });
            document.addEventListener('click', function (event) {
                if (!event.target.closest('.dashboard-toolbar') && !event.target.closest('.dashboard-dropdown-panel')) {
                    closePanels();
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closePanels();
                }
            });
            window.addEventListener('resize', closePanels);
            window.addEventListener('scroll', function () {
                closePanels();
            }, true);
        })();
    </script>
<?php else: ?>

    <!-- Public Information for Visitors -->
    <section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-3 gap-8">
            <!-- About Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">Origins</h3>
                <p class="mt-2">Learn about the rich history and culture of our shared ancestors.</p>
                <a href="?to=origins/" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Origins</a>
            </div>

            <!-- Login/Register Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">About this site</h3>
                <p class="mt-2">Find out more about this site, it's story and who it is for.</p>
                <a href="?to=about/aboutvirtualnataleira.php" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">About <i>Soli's Children</i></a>
            </div>            

            <!-- Login/Register Section -->
            <div class="p-6 bg-white shadow-lg rounded-lg">
                <h3 class="text-2xl font-bold">Login or Register</h3>
                <p class="mt-2">Soli's Children can enter the village by logging in or registering.</p>
                <a href="?to=login" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Login</a> | 
                <a href="?to=register" class="mt-4 inline-block text-ocean-blue hover:text-burnt-orange">Register</a>
            </div>
        </div>
    </section>

<?php endif; ?>

