<?php

if (!function_exists('nvFeedSanitizeHtml')) {
    function nvFeedSanitizeHtml(string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $allowedTags = [
            'p', 'br', 'div',
            'strong', 'b', 'em', 'i', 'u',
            'ul', 'ol', 'li',
            'a', 'blockquote', 'span',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'pre', 'code',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'img', 'figure', 'figcaption',
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
            'h1'          => $allowedGlobalAttributes,
            'h2'          => $allowedGlobalAttributes,
            'h3'          => $allowedGlobalAttributes,
            'h4'          => $allowedGlobalAttributes,
            'h5'          => $allowedGlobalAttributes,
            'h6'          => $allowedGlobalAttributes,
            'pre'         => $allowedGlobalAttributes,
            'code'        => $allowedGlobalAttributes,
            'table'       => $allowedGlobalAttributes,
            'thead'       => $allowedGlobalAttributes,
            'tbody'       => $allowedGlobalAttributes,
            'tr'          => $allowedGlobalAttributes,
            'th'          => $allowedGlobalAttributes,
            'td'          => $allowedGlobalAttributes,
            'img'         => array_merge($allowedGlobalAttributes, ['src', 'alt', 'title', 'width', 'height', 'loading', 'decoding']),
            'figure'      => $allowedGlobalAttributes,
            'figcaption'  => $allowedGlobalAttributes,
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
            $fallbackAllowed = '<p><br><div><strong><b><em><i><u><ul><ol><li><a><blockquote><span><h1><h2><h3><h4><h5><h6><pre><code><table><thead><tbody><tr><th><td><img><figure><figcaption>';
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
    $feedInitialLimit = 3;
    $feedService = new FeedService($db, $auth, $web);
    $feedData = $feedService->getFeedSlice($user_id, 0, $feedInitialLimit);
    $feedEntries = $feedData['entries'];
    $summary = $feedData['summary'];
    $feedHasMore = $feedData['has_more'];
    $feedNextOffset = $feedData['next_offset'];
    $feedTotal = $feedData['total'];
    $feedSince = $feedData['last_view'] ?? null;
    $currentUserId = $feedData['current_user_id'];
    $isCurrentUserAdmin = $feedData['is_admin'];
    $reactionEmojiMap = $feedData['emoji'];

    $user = Utils::getUser($user_id);
    $descendancy = [];
    if ($user && !empty($user['individuals_id'])) {
        $descendancy = Utils::getLineOfDescendancy(Web::getRootId(), $user['individuals_id']);
    }
?>
<?php if (!empty($descendancy)): ?>
    <section class="container mx-auto pt-6 pb-2 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap justify-center items-center text-xxs sm:text-sm">
            <?php foreach ($descendancy as $index => $descendant): ?>
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

<?php
    $summaryCounts = $feedData['summary_counts'] ?? array();
    $summaryMeta = array(
        'discussions'   => array('label' => 'Discussions',   'icon' => 'fas fa-comments'),
        'individuals'   => array('label' => 'Individuals',   'icon' => 'fas fa-user-plus'),
        'relationships' => array('label' => 'Relationships', 'icon' => 'fas fa-link'),
        'items'         => array('label' => 'Events',        'icon' => 'fas fa-book-open'),
        'files'         => array('label' => 'Files',         'icon' => 'fas fa-photo-video'),
    );
?>
<section class="feed-summary-section" id="feedSummarySection">
    <div class="feed-summary-surface shadow-sm">
        <?php foreach ($summaryMeta as $key => $meta): ?>
            <?php
                $count = isset($summaryCounts[$key]) ? (int) $summaryCounts[$key] : 0;
                $label = $meta['label'];
                $summaryText = $summary[$label] ?? '';
            ?>
            <button type="button"
                class="summary-filter bg-white border border-gray-200 shadow-sm rounded-lg px-4 py-3 transition hover:border-ocean-blue focus:outline-none focus:ring-2 focus:ring-ocean-blue"
                aria-pressed="false"
                data-feed-filter="<?= $key ?>">
                <div class="summary-filter-main">
                    <span class="summary-filter-icon text-xl text-ocean-blue"><i class="<?= $meta['icon'] ?>"></i></span>
                    <div class="summary-filter-copy">
                        <span class="summary-filter-label text-sm font-semibold text-brown"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (!empty($summaryText)): ?>
                            <span class="summary-filter-text text-xs text-gray-500"><?= htmlspecialchars($summaryText, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($count > 0): ?>
                    <span class="summary-filter-badge bg-red-500 text-white inline-flex items-center justify-center text-xs font-semibold"><?= $count ?></span>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>
</section>

<section class="container mx-auto py-2 px-4 sm:px-6 lg:px-8 pt-2">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h3 class="text-2xl font-bold text-ocean-blue flex items-center gap-3">
            <i class="fas fa-stream"></i>
            Latest updates
        </h3>
    </div>
    <?php if (empty($feedEntries)): ?>
        <div class="feed-empty text-center text-gray-500 bg-white shadow-sm border rounded-lg py-12">No updates to show right now.</div>
    <?php else: ?>
        <div class="feed-stream space-y-4" id="feedStream">
            <?php foreach ($feedEntries as $entry): ?>
                <?= $entry['html'] ?>
            <?php endforeach; ?>
            <?php if ($feedHasMore): ?>
                <div id="feedSentinel" class="feed-sentinel" aria-hidden="true"></div>
            <?php endif; ?>
        </div>
        <div class="feed-loading text-center text-gray-500 py-4 hidden" id="feedLoading">
            <i class="fas fa-circle-notch fa-spin mr-2"></i> Loading more updates&hellip;
        </div>
    <?php endif; ?>
</section>

    <script>
        window.NV_FEED_BOOTSTRAP = {
            limit: <?= (int) $feedInitialLimit ?>,
            nextOffset: <?= (int) $feedNextOffset ?>,
            total: <?= (int) $feedTotal ?>,
            hasMore: <?= $feedHasMore ? 'true' : 'false' ?>,
            since: <?= $feedSince === null ? 'null' : json_encode($feedSince) ?>
        };
    </script>
    <script>
        window.NV_FEED_CONFIG = {
            currentUserId: <?= (int) $currentUserId ?>,
            isAdmin: <?= $isCurrentUserAdmin ? 'true' : 'false' ?>,
            emoji: <?= json_encode($reactionEmojiMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
        };
    </script>
    <script>
        (function () {
            var stream = document.getElementById('feedStream');
            var sentinel = document.getElementById('feedSentinel');
            var loading = document.getElementById('feedLoading');
            var bootstrap = window.NV_FEED_BOOTSTRAP || {};
            var offset = bootstrap.nextOffset || (bootstrap.limit || 0);
            var limit = bootstrap.limit || 3;
            var hasMore = Boolean(bootstrap.hasMore);
            var since = bootstrap.since;
            var isLoading = false;

            function appendEntries(entries) {
                if (!stream || !Array.isArray(entries)) {
                    return;
                }
                entries.forEach(function (entry) {
                    if (!entry || !entry.html) {
                        return;
                    }
                    var range = document.createRange();
                    range.selectNode(stream);
                    var fragment = range.createContextualFragment(entry.html);
                    stream.insertBefore(fragment, sentinel);
                });
            }

            function fetchMore() {
                if (isLoading || !hasMore || !stream) {
                    return;
                }
                isLoading = true;
                if (loading) {
                    loading.classList.remove('hidden');
                }
                fetch('ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        method: 'get_feed_entries',
                        data: {
                            offset: offset,
                            limit: limit,
                            since: since
                        }
                    })
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Feed request failed');
                        }
                        return response.json();
                    })
                    .then(function (payload) {
                        if (!payload || !payload.success || !payload.data) {
                            throw new Error(payload && payload.message ? payload.message : 'Invalid response');
                        }
                        appendEntries(payload.data.entries || []);
                        offset = payload.data.nextOffset;
                        hasMore = Boolean(payload.data.hasMore);
                        if (!hasMore && sentinel && sentinel.parentNode) {
                            sentinel.parentNode.removeChild(sentinel);
                        }
                    })
                    .catch(function (error) {
                        console.error(error);
                    })
                    .finally(function () {
                        isLoading = false;
                        if (loading) {
                            loading.classList.add('hidden');
                        }
                    });
            }

            if ('IntersectionObserver' in window && sentinel) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            fetchMore();
                        }
                    });
                }, { rootMargin: '200px 0px' });
                observer.observe(sentinel);
            } else if (sentinel) {
                var legacyHandler = function () {
                    if (!hasMore) {
                        window.removeEventListener('scroll', legacyHandler);
                        return;
                    }
                    if ((window.innerHeight + window.pageYOffset) >= (document.body.offsetHeight - 200)) {
                        fetchMore();
                    }
                };
                window.addEventListener('scroll', legacyHandler);
            }
        })();
    </script>
    <script>
        (function () {
            var stream = document.getElementById('feedStream');
            var filterButtonsNodeList = document.querySelectorAll('[data-feed-filter]');
            if (!stream || !filterButtonsNodeList || filterButtonsNodeList.length === 0) {
                return;
            }

            if (typeof window.Set !== 'function') {
                return;
            }

            var filterButtons = Array.prototype.slice.call(filterButtonsNodeList);
            var filterTypeMap = {
                discussions: ['discussion', 'comment'],
                individuals: ['individual'],
                relationships: ['relationship'],
                items: ['item'],
                files: ['file']
            };
            var activeFilters = new Set(Object.keys(filterTypeMap));

            function normalizeKey(rawKey) {
                return (rawKey || '').toString().trim().toLowerCase();
            }

            function updateButtonState(button, isActive) {
                if (!button) {
                    return;
                }
                if (isActive) {
                    button.classList.add('summary-filter-active');
                    button.setAttribute('aria-pressed', 'true');
                } else {
                    button.classList.remove('summary-filter-active');
                    button.setAttribute('aria-pressed', 'false');
                }
            }

            function typeMatchesFilters(type) {
                if (activeFilters.size === 0) {
                    return true;
                }
                if (!type) {
                    return false;
                }
                var matches = false;
                activeFilters.forEach(function (key) {
                    if (matches) {
                        return;
                    }
                    var mappedTypes = filterTypeMap[key];
                    if (!mappedTypes) {
                        return;
                    }
                    if (mappedTypes.indexOf(type) !== -1) {
                        matches = true;
                    }
                });
                return matches;
            }

            function applyFiltersToElement(element) {
                if (!element || element.nodeType !== 1) {
                    return;
                }
                var type = normalizeKey(element.getAttribute('data-feed-type'));
                var shouldShow = typeMatchesFilters(type);
                if (shouldShow) {
                    element.classList.remove('feed-item-hidden');
                } else {
                    element.classList.add('feed-item-hidden');
                }
            }

            function applyFilters() {
                if (!stream) {
                    return;
                }
                var items = stream.querySelectorAll('[data-feed-type]');
                Array.prototype.forEach.call(items, function (item) {
                    applyFiltersToElement(item);
                });
            }

            filterButtons.forEach(function (button) {
                var key = normalizeKey(button.getAttribute('data-feed-filter'));
                var initiallyActive = activeFilters.has(key);
                updateButtonState(button, initiallyActive);
                button.addEventListener('click', function () {
                    if (!key) {
                        return;
                    }
                    if (activeFilters.has(key)) {
                        activeFilters.delete(key);
                        updateButtonState(button, false);
                    } else {
                        activeFilters.add(key);
                        updateButtonState(button, true);
                    }
                    applyFilters();
                });
            });

            applyFilters();

            if (typeof MutationObserver === 'function') {
                var observer = new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        if (!mutation.addedNodes || mutation.addedNodes.length === 0) {
                            return;
                        }
                        Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                            if (!node || node.nodeType !== 1) {
                                return;
                            }
                            if (node.hasAttribute('data-feed-type')) {
                                applyFiltersToElement(node);
                                return;
                            }
                            var descendants = node.querySelectorAll('[data-feed-type]');
                            if (descendants && descendants.length > 0) {
                                Array.prototype.forEach.call(descendants, function (descendant) {
                                    applyFiltersToElement(descendant);
                                });
                            }
                        });
                    });
                });
                observer.observe(stream, { childList: true });
            }
        })();
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






