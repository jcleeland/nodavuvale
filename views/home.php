<?php

if (!function_exists('nvFeedSanitizeHtml')) {
    function nvFeedSanitizeHtml(string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $allowedTags = [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'blockquote', 'span',
        ];
        $allowedGlobalAttributes = ['class', 'id', 'title', 'data-*', 'aria-*'];
        $allowedAttributes = [
            'a'           => array_merge($allowedGlobalAttributes, ['href', 'target', 'rel', 'name']),
            'span'        => array_merge($allowedGlobalAttributes, ['role', 'onclick']),
            'blockquote'  => array_merge($allowedGlobalAttributes, ['cite']),
            'p'           => $allowedGlobalAttributes,
            'ul'          => $allowedGlobalAttributes,
            'ol'          => $allowedGlobalAttributes,
            'li'          => $allowedGlobalAttributes,
            'strong'      => $allowedGlobalAttributes,
            'b'           => $allowedGlobalAttributes,
            'em'          => $allowedGlobalAttributes,
            'i'           => $allowedGlobalAttributes,
            'u'           => $allowedGlobalAttributes,
            'div'         => $allowedGlobalAttributes,
        ];

        $allowedTagLookup = array_flip($allowedTags);

        $encodeHtml = function_exists('mb_convert_encoding')
            ? @mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')
            : $html;
        if ($encodeHtml === false) {
            $encodeHtml = $html;
        }

        $fragment = '<div>' . $encodeHtml . '</div>';
        $previousLibxml = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8" ?>' . $fragment,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxml);
            $fallbackAllowed = '<p><br><strong><b><em><i><u><ul><ol><li><a><blockquote><span>';
            $clean = strip_tags($html, $fallbackAllowed);
            if ($clean === null || $clean === '') {
                return '';
            }
            return preg_replace_callback('/<a\b[^>]*>/i', static function ($matches) {
                $tag = $matches[0];
                $hasTarget = stripos($tag, 'target=') !== false;
                $hasRel = stripos($tag, 'rel=') !== false;
                $result = rtrim($tag, '>');
                if (!$hasTarget) {
                    $result .= ' target="_blank"';
                }
                if (!$hasRel) {
                    $result .= ' rel="noopener"';
                }
                return $result . '>';
            }, $clean) ?? $clean;
        }

        $wrapper = $document->getElementsByTagName('div')->item(0);
        if (!$wrapper instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxml);
            return '';
        }

        $attributeAllowed = static function (string $name, array $allowedList): bool {
            foreach ($allowedList as $allowed) {
                if (substr($allowed, -1) === '*') {
                    $prefix = substr($allowed, 0, -1);
                    if ($prefix === '' || strpos($name, $prefix) === 0) {
                        return true;
                    }
                } elseif ($name === $allowed) {
                    return true;
                }
            }
            return false;
        };

        $sanitizeNode = static function (DOMNode $node) use (
            &$sanitizeNode,
            $allowedTagLookup,
            $allowedAttributes,
            $allowedGlobalAttributes,
            $attributeAllowed
        ): void {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($node->nodeName);
                if (!isset($allowedTagLookup[$tagName])) {
                    if ($node->parentNode) {
                        while ($node->firstChild) {
                            $node->parentNode->insertBefore($node->firstChild, $node);
                        }
                        $node->parentNode->removeChild($node);
                    }
                    return;
                }

                if ($node instanceof DOMElement && $node->hasAttributes()) {
                    $allowedForTag = $allowedAttributes[$tagName] ?? $allowedGlobalAttributes;
                    for ($i = $node->attributes->length - 1; $i >= 0; $i--) {
                        $attr = $node->attributes->item($i);
                        if (!$attr) {
                            continue;
                        }
                        $attrName = strtolower($attr->nodeName);
                        if (!$attributeAllowed($attrName, $allowedForTag)) {
                            $node->removeAttributeNode($attr);
                        }
                    }

                    if ($tagName === 'a') {
                        $href = $node->getAttribute('href');
                        if ($href !== '' && !preg_match('/^(https?:|mailto:|\/|#)/i', $href)) {
                            $node->removeAttribute('href');
                        }
                        if ($node->hasAttribute('href')) {
                            if (!$node->hasAttribute('target')) {
                                $node->setAttribute('target', '_blank');
                            }
                            $currentRel = $node->getAttribute('rel');
                            $relTokens = array_filter(preg_split('/\s+/', strtolower($currentRel)) ?: []);
                            if (!in_array('noopener', $relTokens, true)) {
                                $relTokens[] = 'noopener';
                            }
                            $node->setAttribute('rel', trim(implode(' ', array_unique($relTokens))));
                        } else {
                            $node->removeAttribute('target');
                            $node->removeAttribute('rel');
                        }
                    }

                    if ($tagName === 'span' && $node->hasAttribute('onclick')) {
                        $onclick = trim($node->getAttribute('onclick'));
                        $isExpand = preg_match('/^\s*expandStory\s*\(\s*[\'"][A-Za-z0-9_\-]+[\'"]\s*\)\s*;?\s*$/', $onclick) === 1;
                        $isShow = preg_match('/^\s*showStory\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*[\'"][A-Za-z0-9_\-]+[\'"]\s*\)\s*;?\s*$/', $onclick) === 1;
                        if (!$isExpand && !$isShow) {
                            $node->removeAttribute('onclick');
                        }
                    }
                }
            }

            for ($child = $node->firstChild; $child !== null; $child = $next) {
                $next = $child->nextSibling;
                $sanitizeNode($child);
            }
        };

        $sanitizeNode($wrapper);

        $output = '';
        foreach ($wrapper->childNodes as $childNode) {
            $output .= $document->saveHTML($childNode);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxml);

        return $output;
    }
}
// Check if the user is logged in
$is_logged_in = isset($_SESSION['user_id']);
// Load all updates unless a specific cutoff is requested
$defaultChangeSince = '1900-01-01 00:00:00';
$viewnewsince = isset($_GET['changessince']) && $_GET['changessince'] !== ''
    ? $_GET['changessince']
    : $defaultChangeSince;

?>

<!-- Hero Section -->
<section class="hero hero-collapsible text-white py-20" data-hero-collapsible>
    <div class="container hero-content" id="homeHeroContent">
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
<script>
    (function () {
        var hero = document.querySelector('[data-hero-collapsible]');
        if (!hero) {
            return;
        }

        var heroContentId = 'homeHeroContent';
        var mobileQuery = window.matchMedia('(max-width: 640px)');
        var collapseDelay = 5000;
        var collapseTimer = null;

        function setAriaExpanded(state) {
            if (mobileQuery.matches) {
                hero.setAttribute('aria-expanded', state ? 'true' : 'false');
            } else {
                hero.removeAttribute('aria-expanded');
            }
        }

        function collapseHero() {
            if (hero.classList.contains('hero-collapsed')) {
                return;
            }
            hero.classList.add('hero-collapsed');
            setAriaExpanded(false);
        }

        function expandHero() {
            if (!hero.classList.contains('hero-collapsed')) {
                setAriaExpanded(true);
                return;
            }
            hero.classList.remove('hero-collapsed');
            setAriaExpanded(true);
        }

        function clearCollapseTimer() {
            if (collapseTimer) {
                window.clearTimeout(collapseTimer);
                collapseTimer = null;
            }
        }

        function scheduleInitialCollapse() {
            clearCollapseTimer();
            if (!mobileQuery.matches) {
                hero.classList.remove('hero-collapsed');
                return;
            }
            hero.classList.remove('hero-collapsed');
            setAriaExpanded(true);
            collapseTimer = window.setTimeout(collapseHero, collapseDelay);
        }

        function enableMobileInteraction() {
            hero.setAttribute('role', 'button');
            hero.setAttribute('tabindex', '0');
            hero.setAttribute('aria-controls', heroContentId);
            scheduleInitialCollapse();
        }

        function disableMobileInteraction() {
            clearCollapseTimer();
            hero.classList.remove('hero-collapsed');
            hero.removeAttribute('role');
            hero.removeAttribute('tabindex');
            hero.removeAttribute('aria-controls');
            hero.removeAttribute('aria-expanded');
        }

        function handleModeChange(matches) {
            if (matches) {
                enableMobileInteraction();
            } else {
                disableMobileInteraction();
            }
        }

        handleModeChange(mobileQuery.matches);

        var mqListener = function (event) {
            handleModeChange(Boolean(event.matches));
        };
        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', mqListener);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(mqListener);
        }

        hero.addEventListener('click', function (event) {
            if (!mobileQuery.matches) {
                return;
            }
            if (event.target.closest('a')) {
                return;
            }
            if (hero.classList.contains('hero-collapsed')) {
                expandHero();
                return;
            }
            collapseHero();
        });

        hero.addEventListener('keydown', function (event) {
            if (!mobileQuery.matches) {
                return;
            }
            if (event.key === ' ' || event.key === 'Spacebar' || event.key === 'Enter') {
                event.preventDefault();
                if (hero.classList.contains('hero-collapsed')) {
                    expandHero();
                } else {
                    collapseHero();
                }
            }
        });
    })();
</script>

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
        'visitor'    => ['label' => 'Latest visit',      'icon' => 'fas fa-door-open'],
    ];

    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $isCurrentUserAdmin = ($auth->getUserRole() === 'admin');
    $reactionEmojiMap = [
        'like'  => '👍',
        'love'  => '❤️',
        'haha'  => '😂',
        'wow'   => '😮',
        'sad'   => '😢',
        'angry' => '😡',
        'care'  => '🤗',
    ];

    $rootIndividualId = (int) Web::getRootId();
    $currentUserIndividualId = (int) ($_SESSION['individuals_id'] ?? 0);
    $descendancyCache = [];
    $getDescendancyTrail = static function ($individualId) use (&$descendancyCache, $rootIndividualId) {
        $individualId = (int) $individualId;
        if ($individualId <= 0 || $rootIndividualId <= 0) {
            return [];
        }
        if (!array_key_exists($individualId, $descendancyCache)) {
            $line = Utils::getLineOfDescendancy($rootIndividualId, $individualId);
            $descendancyCache[$individualId] = is_array($line) ? $line : [];
        }
        return $descendancyCache[$individualId];
    };
    $relationshipCache = [];
    $getRelationshipToUser = static function ($individualId) use (&$relationshipCache, $currentUserIndividualId) {
        $individualId = (int) $individualId;
        if ($individualId <= 0 || $currentUserIndividualId <= 0 || $individualId === $currentUserIndividualId) {
            return '';
        }
        if (!array_key_exists($individualId, $relationshipCache)) {
            $label = Utils::getRelationshipLabel($currentUserIndividualId, $individualId);
            $relationshipCache[$individualId] = is_string($label) ? trim($label) : '';
        }
        return $relationshipCache[$individualId];
    };


    $discussionIds = [];
    foreach ($changes['discussions'] as $discussionMeta) {
        if (!empty($discussionMeta['discussionId'])) {
            $discussionIds[] = (int) $discussionMeta['discussionId'];
        }
    }
    $discussionIds = array_values(array_unique(array_filter($discussionIds)));
    $discussionReactionSummaries = Utils::getDiscussionReactionSummaryByIds($discussionIds);
    $discussionCommentsLookup = Utils::getDiscussionCommentsByIds($discussionIds);

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

    foreach ($changes['visitors'] as $visitor) {
        $timestampString = $visitor['last_view'] ?? null;
        if (!$timestampString) {
            continue;
        }
        $timestamp = strtotime($timestampString);
        if (!$timestamp) {
            continue;
        }
        $feedEntries[] = [
            'type'      => 'visitor',
            'title'     => trim(($visitor['first_name'] ?? '') . ' ' . ($visitor['last_name'] ?? '')),
            'content'   => 'Checked in recently.',
            'meta'      => [
                'actor_name'   => trim(($visitor['first_name'] ?? '') . ' ' . ($visitor['last_name'] ?? '')),
                'actor_id'     => $visitor['user_id'] ?? null,
                'context'      => 'Latest logins',
                'subject_name' => '',
            ],
            'url'       => isset($visitor['user_id']) ? "?to=family/users&user_id={$visitor['user_id']}" : '',
            'timestamp' => $timestamp,
            'raw_time'  => $timestampString,
        ];
    }

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
        $discussionContentRaw = isset($discussion['content']) ? stripslashes($discussion['content']) : '';
        $discussionSnippetHtml = '';
        if ($discussionContentRaw !== '') {
            $discussionSnippetHtml = nvFeedSanitizeHtml(
                $web->truncateText(
                    nl2br($discussionContentRaw),
                    80,
                    'Read more',
                    'feed_discussion_' . $discussion['discussionId'],
                    'expand'
                )
            );
        }
        $feedEntries[] = [
            'type'      => $discussion['change_type'] === 'comment' ? 'comment' : 'discussion',
            'title'     => stripslashes($discussion['title']),
            'content'   => $createSnippet($discussionContentRaw, 28),
            'content_html' => $discussionSnippetHtml,
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
            'descendancy' => $isTreeDiscussion ? $getDescendancyTrail($discussion['individual_id']) : [],
            'interactions' => [
                'type'             => 'discussion',
                'target_id'        => (int) $discussion['discussionId'],
                'reaction_summary' => $discussionReactionSummaries[(int) $discussion['discussionId']] ?? [],
                'comments'         => $discussionCommentsLookup[(int) $discussion['discussionId']] ?? [],
            ],
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
            'descendancy' => $getDescendancyTrail($individual['individualId'] ?? 0),
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

    $itemInteractionMeta = [];
    $itemTargetIds = [];
    foreach ($itemGroupings as $groupKey => $group) {
        if (empty($group['items']) || !is_array($group['items'])) {
            $itemInteractionMeta[$groupKey] = null;
            continue;
        }
        $primaryItemId = null;
        $itemIdentifier = null;
        foreach ($group['items'] as $groupItem) {
            if ($itemIdentifier === null && !empty($groupItem['item_identifier'])) {
                $itemIdentifier = (int) $groupItem['item_identifier'];
            }
            if (!empty($groupItem['item_id'])) {
                $candidateId = (int) $groupItem['item_id'];
                if ($primaryItemId === null) {
                    $primaryItemId = $candidateId;
                }
                if (isset($groupItem['detail_type']) && strtolower((string) $groupItem['detail_type']) === 'story') {
                    $primaryItemId = $candidateId;
                }
            }
        }
        if ($primaryItemId !== null) {
            $itemInteractionMeta[$groupKey] = [
                'item_id' => $primaryItemId,
                'item_identifier' => $itemIdentifier,
                'group_name' => $group['item_group_name'] ?? '',
            ];
            $itemTargetIds[$primaryItemId] = true;
        } else {
            $itemInteractionMeta[$groupKey] = null;
        }
    }
    $itemTargetIdList = array_keys($itemTargetIds);
    $itemReactionSummaries = Utils::getItemReactionSummaryByItemIds($itemTargetIdList);
    $itemCommentsLookup = Utils::getItemCommentsByItemIds($itemTargetIdList);
    $nonInteractiveItemGroups = ['Birth', 'Death', 'Name'];

    foreach ($itemGroupings as $groupKey => $itemGroup) {
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
        $groupTitleTrimmed = trim($groupTitle);
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

            if (strcasecmp($detailLabel, 'GPS') === 0) {
                $coordinateValue = preg_replace('/\s+/', ' ', $detailValue);
                if ($coordinateValue !== '') {
                    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($coordinateValue);
                    $addDetailRow('GPS', $coordinateValue, $mapsUrl, false);
                    continue;
                }
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
        $interaction = null;
        $interactionMeta = $itemInteractionMeta[$groupKey] ?? null;
        if ($interactionMeta && !in_array($groupTitleTrimmed, $nonInteractiveItemGroups, true)) {
            $itemTargetId = $interactionMeta['item_id'];
            $interaction = [
                'type'             => 'item',
                'target_id'        => $itemTargetId,
                'item_identifier'  => $interactionMeta['item_identifier'],
                'reaction_summary' => $itemReactionSummaries[$itemTargetId] ?? [],
                'comments'         => $itemCommentsLookup[$itemTargetId] ?? [],
            ];
        }
        $feedEntries[] = [
            'type'      => 'item',
            'title'     => $groupTitleTrimmed !== '' ? $groupTitleTrimmed . ' update for ' . $personName : 'New update for ' . $personName,
            'content'   => $snippet,
            'meta'      => [
                'actor_name'   => trim(($firstItem['first_name'] ?? '') . ' ' . ($firstItem['last_name'] ?? '')),
                'actor_id'     => $firstItem['user_id'] ?? null,
                'subject_name' => $personName,
                'privacy'      => $itemGroup['privacy'] ?? 'private',
            ],
            'url'       => "?to=family/individual&individual_id={$firstItem['individualId']}&tab=eventstab",
            'timestamp' => $timestamp,
            'raw_time'  => $timestampString,
            'details'   => $detailRows,
            'media'     => $mediaItems,
            'files'     => $fileLinks,
            'descendancy' => $getDescendancyTrail($firstItem['individualId'] ?? 0),
            'interactions' => $interaction,
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
            'descendancy' => $getDescendancyTrail($file['individualId'] ?? 0),
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

    $descendancy = [];
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
        </div>
        <?php
            $renderReactionSummary = static function (array $summary, array $emojiMap): string {
                $html = '';
                foreach ($emojiMap as $reactionType => $emoji) {
                    $count = isset($summary[$reactionType]) ? (int) $summary[$reactionType] : 0;
                    if ($count > 0) {
                        $html .= '<span class="reaction-item" title="' . htmlspecialchars(ucfirst($reactionType), ENT_QUOTES, 'UTF-8') . '">' .
                            $emoji . ' <span class="reaction-count">' . $count . '</span></span> ';
                    }
                }
                return trim($html);
            };

            $renderFeedEntry = static function (array $entry) use ($feedTypeMeta, $web, $currentUserId, $isCurrentUserAdmin, $reactionEmojiMap, $renderReactionSummary, $getRelationshipToUser) {
                $type = $entry['type'];
                $meta = $feedTypeMeta[$type] ?? ['label' => ucfirst($type), 'icon' => 'fas fa-circle'];
                $timestampLabel = isset($entry['timestamp']) ? date('l, d F Y g:ia', $entry['timestamp']) : '';
                $actorInitial = '';
                if (empty($entry['meta']['actor_id']) && !empty($entry['meta']['actor_name'])) {
                    $actorInitial = strtoupper(substr($entry['meta']['actor_name'], 0, 1));
                }
                $descendancyTrail = [];
                if (!empty($entry['descendancy']) && is_array($entry['descendancy'])) {
                    foreach ($entry['descendancy'] as $descendant) {
                        $label = trim((string) ($descendant[0] ?? ''));
                        $id = isset($descendant[1]) ? (int) $descendant[1] : 0;
                        if ($label === '') {
                            continue;
                        }
                        $relationship = $getRelationshipToUser($id);
                        $descendancyTrail[] = [
                            'label' => $label,
                            'id'    => $id,
                            'relationship' => $relationship,
                        ];
                    }
                }

                ob_start();
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
                        <?php if (!empty($descendancyTrail)): ?>
                            <div class="feed-item-descendancy text-xs text-gray-500 mb-2">
                                <span class="font-semibold text-brown mr-1">Line:</span>
                                <?php foreach ($descendancyTrail as $index => $descendant): ?>
                                    <?php if ($descendant['id'] > 0): ?>
                                        <a href="?to=family/individual&individual_id=<?= $descendant['id'] ?>" class="hover:text-burnt-orange"><?= htmlspecialchars($descendant['label']) ?></a>
                                    <?php else: ?>
                                        <span><?= htmlspecialchars($descendant['label']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($descendant['relationship'])): ?>
                                        <span class="text-gray-400"> (<?= htmlspecialchars($descendant['relationship']) ?>)</span>
                                    <?php endif; ?>
                                    <?php if ($index < count($descendancyTrail) - 1): ?>
                                        <span class="mx-1 text-gray-400">
                                            <i class="fas fa-angle-right"></i>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($entry['title'])): ?>
                            <h4 class="feed-item-title text-lg font-semibold text-brown mb-1">
                                <?php if (!empty($entry['url'])): ?>
                                    <a class="hover:text-burnt-orange" href="<?= $entry['url'] ?>"><?= htmlspecialchars($entry['title']) ?></a>
                                <?php else: ?>
                                    <?= htmlspecialchars($entry['title']) ?>
                                <?php endif; ?>
                            </h4>
                        <?php endif; ?>
                        <?php if (!empty($entry['content_html'])): ?>
                            <div class="feed-item-summary text-sm text-gray-600 mb-3"><?= $entry['content_html'] ?></div>
                        <?php elseif (!empty($entry['content'])): ?>
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
                    <?php
                        $interaction = $entry['interactions'] ?? null;
                        if ($interaction && !empty($interaction['type']) && !empty($interaction['target_id'])):
                            $interactionType = $interaction['type'];
                            $isDiscussionInteraction = ($interactionType === 'discussion');
                            $targetId = (int) $interaction['target_id'];
                            $reactionContainerClass = $isDiscussionInteraction ? 'discussion-reactions' : 'item-reactions';
                            $targetAttributeName = $isDiscussionInteraction ? 'data-discussion-id' : 'data-item-id';
                            $itemIdentifierAttribute = (!$isDiscussionInteraction && !empty($interaction['item_identifier']))
                                ? ' data-item-identifier="' . (int) $interaction['item_identifier'] . '"'
                                : '';
                            $reactionSummaryHtml = $renderReactionSummary($interaction['reaction_summary'] ?? [], $reactionEmojiMap);
                            $comments = is_array($interaction['comments'] ?? null) ? $interaction['comments'] : [];
                    ?>
                        <div class="feed-item-interactions mt-4" data-feed-interaction="<?= htmlspecialchars($interactionType, ENT_QUOTES, 'UTF-8') ?>" <?= $targetAttributeName ?>="<?= $targetId ?>">
                            <div class="<?= $reactionContainerClass ?> feed-reactions flex items-center gap-3" <?= $targetAttributeName ?>="<?= $targetId ?>"<?= $itemIdentifierAttribute ?>>
                                <button type="button" class="like-image inline-flex items-center justify-center pl-2 h-8 w-8 rounded-full bg-gray-100 text-lg" title="React">
                                    <svg alt="Like" class="like-image flex-item" viewBox="0 0 32 32" xml:space="preserve" width="18px" height="18px" fill="#000000">
                                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                        <g id="SVGRepo_iconCarrier">
                                            <style type="text/css">
                                                .st0{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:10;}
                                                .st1{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
                                                .st2{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:5.2066,0;}
                                            </style>
                                            <path class="st0" d="M11,24V14H5v12h6v-2.4l0,0c1.5,1.6,4.1,2.4,6.2,2.4h6.5c1.1,0,2.1-0.8,2.3-2l1.5-8.6c0.3-1.5-0.9-2.4-2.3-2.4H20V6.4C20,5.1,18.7,4,17.4,4h0C16.1,4,15,5.1,15,6.4v0c0,1.6-0.5,3.1-1.4,4.4L11,13.8"></path>
                                        </g>
                                    </svg>
                                </button>
                                <div class="reaction-buttons" title="Reactions">
                                    <?php foreach ($reactionEmojiMap as $reactionKey => $emoji): ?>
                                        <button class="reaction-btn" data-reaction="<?= htmlspecialchars($reactionKey, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(ucfirst($reactionKey), ENT_QUOTES, 'UTF-8') ?>"><?= $emoji ?></button>
                                    <?php endforeach; ?>
                                    <button class="reaction-btn" data-reaction="remove" title="Remove reaction">&times;</button>
                                </div>
                                <div class="reaction-summary-container">
                                    <div class="reaction-summary"><?= $reactionSummaryHtml ?></div>
                                </div>
                            </div>
                            <div class="feed-comments mt-3">
                                <div class="feed-comment-list space-y-3<?= empty($comments) ? ' hidden' : '' ?>" data-comment-list>
                                    <?php foreach ($comments as $comment): ?>
                                        <?php
                                            $commentUserId = (int) ($comment['user_id'] ?? 0);
                                            $commentId = (int) ($comment['id'] ?? 0);
                                            $commentName = trim(($comment['first_name'] ?? '') . ' ' . ($comment['last_name'] ?? ''));
                                            $commentCreatedAt = $comment['created_at'] ?? '';
                                            $canDeleteComment = ($commentUserId === $currentUserId) || $isCurrentUserAdmin;
                                        ?>
                                        <div class="feed-comment flex items-start gap-3" data-comment-id="<?= $commentId ?>" data-user-id="<?= $commentUserId ?>">
                                            <div class="feed-comment-avatar">
                                                <?php if ($commentUserId > 0): ?>
                                                    <?= $web->getAvatarHTML($commentUserId, "xs", "feed-comment-avatar"); ?>
                                                <?php else: ?>
                                                    <div class="feed-comment-avatar-placeholder">?</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="feed-comment-body flex-1">
                                                <div class="feed-comment-meta text-xs text-gray-500 flex items-center gap-2">
                                                    <span class="font-semibold text-gray-700"><?= htmlspecialchars($commentName) ?></span>
                                                    <?php if ($commentCreatedAt !== ''): ?>
                                                        <span class="feed-comment-timestamp"><?= $web->timeSince($commentCreatedAt) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($canDeleteComment): ?>
                                                        <button type="button" class="feed-comment-delete ml-auto text-gray-400 hover:text-warm-red" title="Delete comment" data-comment-delete>&times;</button>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="feed-comment-text text-sm text-gray-700"><?= nl2br(htmlspecialchars($comment['comment'] ?? '')) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="feed-comment-empty text-xs text-gray-400<?= empty($comments) ? '' : ' hidden' ?>" data-comment-empty>No comments yet.</div>
                                <form class="feed-comment-form mt-2 flex gap-2" data-comment-form>
                                    <textarea class="flex-1 border rounded-lg p-2 text-sm" placeholder="Add a comment..." required data-comment-input></textarea>
                                    <button type="submit" class="px-3 py-2 text-sm text-white bg-ocean-blue rounded-md hover:bg-ocean-blue-700" title="Post comment">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>
                </article>
                <?php
                return trim(ob_get_clean());
            };

            $initialFeedBatchSize = 10;
            $subsequentFeedBatchSize = 5;
            $initialFeedEntries = array_slice($feedEntries, 0, $initialFeedBatchSize);
            $remainingFeedEntries = array_slice($feedEntries, $initialFeedBatchSize);
            $initialFeedHtml = array_map($renderFeedEntry, $initialFeedEntries);
            $remainingFeedHtml = array_map($renderFeedEntry, $remainingFeedEntries);
        ?>
        <?php if (empty($initialFeedHtml)): ?>
            <div class="feed-empty text-center text-gray-500 bg-white shadow-sm border rounded-lg py-12">No updates to show right now.</div>
        <?php else: ?>
            <div class="feed-stream space-y-4" id="feedStream">
                <?php foreach ($initialFeedHtml as $entryHtml): ?>
                    <?= $entryHtml ?>
                <?php endforeach; ?>
                <div id="feedSentinel" class="feed-sentinel" aria-hidden="true"></div>
            </div>
            <div class="feed-loading hidden text-center text-gray-500 py-4" id="feedLoading">
                <i class="fas fa-circle-notch fa-spin mr-2"></i> Loading more updates&hellip;
            </div>
        <?php endif; ?>
        <script>
            (function () {
                var feedStream = document.getElementById('feedStream');
                var feedLoading = document.getElementById('feedLoading');
                var feedSentinel = document.getElementById('feedSentinel');
                var feedQueue = <?=
                    json_encode(
                        $remainingFeedHtml,
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    );
                ?>;
                var batchSize = <?= (int) $subsequentFeedBatchSize ?>;
                var isLoading = false;
                var observer = null;

                if (!feedStream || !feedSentinel || !Array.isArray(feedQueue)) {
                    return;
                }

                if (feedQueue.length === 0) {
                    if (feedSentinel.parentNode) {
                        feedSentinel.parentNode.removeChild(feedSentinel);
                    }
                    return;
                }

                function appendHtml(html) {
                    if (!html) {
                        return;
                    }
                    var range = document.createRange();
                    range.selectNodeContents(feedStream);
                    var fragment = range.createContextualFragment(html);
                    feedStream.insertBefore(fragment, feedSentinel);
                }

                function loadNextBatch() {
                    if (isLoading) {
                        return;
                    }
                    if (!feedQueue.length) {
                        if (observer) {
                            observer.disconnect();
                        }
                        if (feedSentinel && feedSentinel.parentNode) {
                            feedSentinel.parentNode.removeChild(feedSentinel);
                        }
                        if (feedLoading) {
                            feedLoading.classList.add('hidden');
                        }
                        return;
                    }
                    isLoading = true;
                    if (feedLoading) {
                        feedLoading.classList.remove('hidden');
                    }
                    window.requestAnimationFrame(function () {
                        var batch = feedQueue.splice(0, batchSize);
                        batch.forEach(appendHtml);
                        isLoading = false;
                        if (feedLoading) {
                            feedLoading.classList.add('hidden');
                        }
                        if (!feedQueue.length) {
                            if (observer) {
                                observer.disconnect();
                            }
                            if (feedSentinel && feedSentinel.parentNode) {
                                feedSentinel.parentNode.removeChild(feedSentinel);
                            }
                        }
                    });
                }

                if ('IntersectionObserver' in window) {
                    observer = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                loadNextBatch();
                            }
                        });
                    }, { rootMargin: '200px 0px' });
                    observer.observe(feedSentinel);
                } else {
                    function legacyScrollHandler() {
                        if (feedQueue.length === 0) {
                            window.removeEventListener('scroll', legacyScrollHandler);
                            return;
                        }
                        if ((window.innerHeight + window.pageYOffset) >= (document.body.offsetHeight - 200)) {
                            loadNextBatch();
                        }
                    }
                    window.addEventListener('scroll', legacyScrollHandler);
                    legacyScrollHandler();
                }

                if (feedQueue.length && feedStream.getBoundingClientRect().bottom < window.innerHeight) {
                    loadNextBatch();
                }
            })();
        </script>
    </section>
    <script>
        (function () {
            var toolbar = document.querySelector('.dashboard-toolbar');
            var panelHost = document.getElementById('dashboardDropdownPanels');
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
            panels.forEach(function (panel) {
                panel.addEventListener('click', function (event) {
                    var closeTarget = event.target.closest('[data-dashboard-close], a[href]:not([href="#"]), button[type="submit"], button[data-dashboard-close], [role="menuitem"]');
                    if (closeTarget) {
                        closePanels();
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
            function repositionActivePanels() {
                panels.forEach(function (panel) {
                    if (!panel.classList.contains('active')) {
                        return;
                    }
                    var panelKey = panel.getAttribute('data-panel');
                    if (!panelKey) {
                        return;
                    }
                    var trigger = document.querySelector('[data-dashboard-trigger="' + panelKey + '"]');
                    if (trigger) {
                        positionPanel(trigger, panel);
                    }
                });
            }
            window.addEventListener('resize', repositionActivePanels);
            var scrollScheduled = false;
            window.addEventListener('scroll', function () {
                if (scrollScheduled) {
                    return;
                }
                scrollScheduled = true;
                requestAnimationFrame(function () {
                    repositionActivePanels();
                    scrollScheduled = false;
                });
            }, true);
        })();
    </script>
    <script>
        window.NV_FEED_CONFIG = {
            currentUserId: <?= (int) $currentUserId ?>,
            isAdmin: <?= $isCurrentUserAdmin ? 'true' : 'false' ?>,
            emoji: <?= json_encode($reactionEmojiMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
        };
    </script>
    <script src="js/feed_interactions.js?v=<?= file_exists('js/feed_interactions.js') ? filemtime('js/feed_interactions.js') : '1' ?>"></script>
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
