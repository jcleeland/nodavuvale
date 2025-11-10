<?php

/**
 * Lightweight feed service that centralises activity aggregation.
 *
 * Subsequent steps will allow the home page and API to share the same data
 * source. For now, this class mirrors the logic that previously lived in
 * views/home.php.
 */

if (!function_exists('nvFeedSanitizeHtml')) {
    /**
     * HTML sanitizer originally defined in views/home.php. Keeping it in a
     * shared location avoids duplicate implementations as the feed is
     * extracted into a service layer.
     */
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

class FeedService
{
    /**
     * @var Database
     */
    private $db;

    /**
     * @var Auth
     */
    private $auth;

    /**
     * @var Web
     */
    private $web;

    private const FETCH_BUFFER = 10;
    private const MIN_FETCH_SIZE = 25;
    private const FEED_HISTORY_START = '1970-01-01 00:00:00';

    public function __construct(Database $db, Auth $auth, Web $web)
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->web = $web;
    }

    private function getSummaryChanges(int $userId, ?string $since, ?int $perTypeLimit = null): array
    {
        $effectiveSince = $this->resolveSummarySince($since);
        $options = [];
        if ($perTypeLimit !== null) {
            $options['limit_per_type'] = $perTypeLimit;
        }
        return Utils::getNewStuff($userId, $effectiveSince, $options);
    }

    private function getHistoricalChanges(int $userId, ?int $perTypeLimit = null): array
    {
        $options = [];
        if ($perTypeLimit !== null) {
            $options['limit_per_type'] = $perTypeLimit;
        }
        return Utils::getNewStuff($userId, self::FEED_HISTORY_START, $options);
    }

    private function resolveSummarySince(?string $since): string
    {
        $candidate = null;

        if ($since !== null && $since !== '') {
            $candidate = $since;
        } elseif (!empty($_SESSION['last_login'])) {
            $candidate = (string) $_SESSION['last_login'];
        } elseif (!empty($_SESSION['last_view'])) {
            $candidate = (string) $_SESSION['last_view'];
        }

        $timestamp = $candidate !== null ? strtotime($candidate) : false;
        if ($timestamp === false) {
            $timestamp = strtotime('-14 days');
        }

        $startOfToday = strtotime('today');
        if ($startOfToday !== false && $timestamp !== false && $timestamp >= $startOfToday) {
            $previousMoment = strtotime('-1 second', $startOfToday);
            if ($previousMoment !== false) {
                $timestamp = $previousMoment;
            } else {
                $timestamp = $startOfToday - 1;
            }
        }

        if ($timestamp === false) {
            $timestamp = time();
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Returns the full feed payload for a user including summary metadata.
     *
     * @param int         $userId
     * @param string|null $since
     *
     * @return array{summary: array, entries: array, last_view: ?string, emoji: array, current_user_id: int, is_admin: bool}
     */
    public function buildFeed(int $userId, ?string $since = null): array
    {
        $summaryChanges = $this->getSummaryChanges($userId, $since);
        $counts = $summaryChanges['counts'] ?? [];
        $summary = $this->buildSummary($summaryChanges, $counts);
        $lastView = $summaryChanges['last_view'] ?? null;

        $historicalChanges = $this->getHistoricalChanges($userId);
        if (!isset($historicalChanges['last_view'])) {
            $historicalChanges['last_view'] = $lastView;
        } else {
            $historicalChanges['last_view'] = $lastView ?? $historicalChanges['last_view'];
        }

        $entries = $this->buildFeedEntries($userId, $historicalChanges);

        return [
            'summary'         => $summary,
            'summary_counts'  => $counts,
            'entries'         => $entries,
            'last_view'       => $lastView,
            'emoji'           => $this->getReactionEmojiMap(),
            'current_user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'is_admin'        => $this->auth->getUserRole() === 'admin',
        ];
    }

    /**
     * Helper that slices the feed for pagination purposes.
     *
     * @param int         $userId
     * @param int         $offset
     * @param int         $limit
     * @param string|null $since
     *
     * @return array{summary: array, entries: array, total: int, has_more: bool, next_offset: int, last_view: ?string, emoji: array, current_user_id: int, is_admin: bool}
     */
    public function getFeedSlice(int $userId, int $offset = 0, int $limit = 10, ?string $since = null): array
    {
        $offset = max(0, $offset);
        $limit = max(1, $limit);

        $perTypeLimit = max($offset + $limit + self::FETCH_BUFFER, self::MIN_FETCH_SIZE);

        $summaryChanges = $this->getSummaryChanges($userId, $since, $perTypeLimit);
        $counts = $summaryChanges['counts'] ?? [];
        $summary = $this->buildSummary($summaryChanges, $counts);
        $lastView = $summaryChanges['last_view'] ?? null;
        if ($lastView === null && $since !== null && $since !== '') {
            $lastView = $since;
        }

        $historicalChanges = $this->getHistoricalChanges($userId, $perTypeLimit);
        if (!isset($historicalChanges['last_view'])) {
            $historicalChanges['last_view'] = $lastView;
        } else {
            $historicalChanges['last_view'] = $lastView ?? $historicalChanges['last_view'];
        }

        $descriptors = $this->buildEventDescriptors($historicalChanges);
        $total = count($descriptors);
        $entries = [];

        if ($total > 0) {
            $pageDescriptors = array_slice($descriptors, $offset, $limit);
            if (!empty($pageDescriptors)) {
                $selectedChanges = $this->selectChangesForDescriptors($historicalChanges, $pageDescriptors);
                $entries = $this->buildFeedEntries($userId, $selectedChanges);
            }
        }

        return [
            'summary'         => $summary,
            'summary_counts'  => $counts,
            'entries'         => $entries,
            'total'           => $total,
            'has_more'        => ($offset + $limit) < $total,
            'next_offset'     => min($total, $offset + $limit),
            'last_view'       => $lastView,
            'emoji'           => $this->getReactionEmojiMap(),
            'current_user_id' => (int) ($_SESSION['user_id'] ?? 0),
            'is_admin'        => $this->auth->getUserRole() === 'admin',
        ];
    }

    /**
     * Core builder that mirrors the feed assembly logic from views/home.php.
     *
     * @param int   $userId
     * @param array $changes
     *
     * @return array
     */
    private function buildFeedEntries(int $userId, array $changes): array
    {
        $feedEntries = [];
        $feedTypeMeta = [
            'discussion'   => ['label' => 'Discussion update',   'icon' => 'fas fa-comments'],
            'comment'      => ['label' => 'New comment',         'icon' => 'fas fa-comment-dots'],
            'individual'   => ['label' => 'New family member',   'icon' => 'fas fa-user-plus'],
            'relationship' => ['label' => 'Relationship update', 'icon' => 'fas fa-link'],
            'item'         => ['label' => 'Event update',        'icon' => 'fas fa-book-open'],
            'file'         => ['label' => 'New file',            'icon' => 'fas fa-photo-video'],
            'visitor'      => ['label' => 'Latest visit',        'icon' => 'fas fa-door-open'],
        ];
        $reactionEmojiMap = $this->getReactionEmojiMap();

        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
        $isCurrentUserAdmin = ($this->auth->getUserRole() === 'admin');
        $siteSettings = $this->db->getSiteSettings();
        $siteName = trim((string) ($siteSettings['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = 'this site';
        }

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
        $indirectConnectionCache = [];
        $getIndirectConnection = static function ($individualId) use (&$indirectConnectionCache, $rootIndividualId) {
            $individualId = (int) $individualId;
            if ($individualId <= 0 || $rootIndividualId <= 0) {
                return [];
            }
            if (!array_key_exists($individualId, $indirectConnectionCache)) {
                $result = Utils::getExtendedConnectionPath($individualId, $rootIndividualId, ['max_depth' => 5]);
                if (is_array($result) && !empty($result['found'])) {
                    $indirectConnectionCache[$individualId] = $result;
                } else {
                    $indirectConnectionCache[$individualId] = [];
                }
            }
            return $indirectConnectionCache[$individualId];
        };
        $relationshipCache = [];
        $getRelationshipToUser = static function ($individualId) use (&$relationshipCache, $currentUserIndividualId) {
            $individualId = (int) $individualId;
            if ($individualId <= 0 || $currentUserIndividualId <= 0 || $individualId === $currentUserIndividualId) {
                return '';
            }
            if (!array_key_exists($individualId, $relationshipCache)) {
                $relationshipCache[$individualId] = Utils::getRelationshipLabel($currentUserIndividualId, $individualId) ?: '';
            }
            return $relationshipCache[$individualId];
        };

        $decorateIndirectConnection = static function ($connection) use ($getRelationshipToUser) {
            if (!is_array($connection) || empty($connection)) {
                return $connection;
            }
            $viaId = isset($connection['via_individual_id']) ? (int) $connection['via_individual_id'] : 0;
            if ($viaId > 0) {
                $relationship = $getRelationshipToUser($viaId);
                if ($relationship !== '') {
                    $connection['user_relationship'] = $relationship;
                }
            }
            return $connection;
        };

        $normalizeValue = static function ($value) {
            $value = (string) $value;
            if ($value === '') {
                return '';
            }
            $hasHtml = strip_tags($value) !== $value;
            if ($hasHtml) {
                return nvFeedSanitizeHtml($value);
            }
            $value = preg_replace("/\r\n?/", "\n", $value);
            return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        };
        $createSnippet = static function ($text, $wordLimit = 24) {
            $text = (string) $text;
            if (trim($text) === '') {
                return '';
            }
            $replacements = [
                '/<\s*br\s*\/?>/i'              => "\n",
                '/<\s*\/p\s*>/i'                => "\n\n",
                '/<\s*p\b[^>]*>/i'              => '',
                '/<\s*\/div\s*>/i'              => "\n",
                '/<\s*div\b[^>]*>/i'            => '',
                '/<\s*li\b[^>]*>/i'             => "\n- ",
                '/<\s*\/li\s*>/i'               => '',
                '/<\s*blockquote\b[^>]*>/i'     => "\n",
                '/<\s*\/blockquote\s*>/i'       => "\n\n",
            ];
            $normalized = preg_replace(array_keys($replacements), array_values($replacements), $text);
            $decoded = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decoded = preg_replace("/\r\n?/", "\n", $decoded);
            $decoded = preg_replace("/\n{3,}/", "\n\n", $decoded);
            $plain = strip_tags($decoded);
            $plain = preg_replace("/[ \t]+/", ' ', $plain);
            $plain = preg_replace("/ ?\n ?/", "\n", $plain);
            $plain = trim($plain);
            if ($plain === '') {
                return '';
            }
            $tokens = preg_split('/(\s+)/u', $plain, -1, PREG_SPLIT_DELIM_CAPTURE);
            $result = '';
            $wordCount = 0;
            foreach ($tokens as $token) {
                if ($token === '') {
                    continue;
                }
                if (preg_match('/^\s+$/u', $token)) {
                    $result .= $token;
                    continue;
                }
                $wordCount++;
                if ($wordCount > $wordLimit) {
                    $result = rtrim($result);
                    $result .= '...';
                    break;
                }
                $result .= $token;
            }
            if ($wordCount <= $wordLimit) {
                return rtrim($result);
            }
            return $result;
        };
        $resolveName = static function (array $record, array $firstKeys, array $lastKeys) {
            $first = '';
            foreach ($firstKeys as $key) {
                if (!empty($record[$key])) {
                    $candidate = trim((string) $record[$key]);
                    if ($candidate !== '') {
                        $first = $candidate;
                        break;
                    }
                }
            }
            $last = '';
            foreach ($lastKeys as $key) {
                if (!empty($record[$key])) {
                    $candidate = trim((string) $record[$key]);
                    if ($candidate !== '') {
                        $last = $candidate;
                        break;
                    }
                }
            }
            $full = trim($first . ' ' . $last);
            if ($full !== '') {
                return $full;
            }
            return $first !== '' ? $first : $last;
        };

        $changes += [
            'visitors'      => [],
            'discussions'   => [],
            'individuals'   => [],
            'relationships' => [],
            'items'         => [],
            'files'         => [],
        ];

        $discussionIds = [];
        foreach ($changes['discussions'] as $discussionMeta) {
            if (!empty($discussionMeta['discussionId'])) {
                $discussionIds[] = (int) $discussionMeta['discussionId'];
            }
        }
        $discussionIds = array_values(array_unique(array_filter($discussionIds)));
        $discussionReactionSummaries = Utils::getDiscussionReactionSummaryByIds($discussionIds);
        $discussionCommentsLookup = Utils::getDiscussionCommentsByIds($discussionIds);

        $itemStyles = Utils::getItemStyles();
        $itemReactionSummaries = [];
        $itemCommentsLookup = [];
        $itemIds = [];
        $extractItemId = static function (array $group): int {
            if (!empty($group['item_id'])) {
                $candidate = (int) $group['item_id'];
                if ($candidate > 0) {
                    return $candidate;
                }
            }
            if (!empty($group['items']) && is_array($group['items'])) {
                foreach ($group['items'] as $itemRow) {
                    if (!is_array($itemRow)) {
                        continue;
                    }
                    $candidates = [
                        $itemRow['item_id'] ?? null,
                        $itemRow['item_links_item_id'] ?? null,
                        $itemRow['item_link_item_id'] ?? null,
                    ];
                    foreach ($candidates as $candidate) {
                        $candidate = (int) $candidate;
                        if ($candidate > 0) {
                            return $candidate;
                        }
                    }
                }
            }
            return 0;
        };
        foreach ($changes['items'] as $itemGroup) {
            $derivedItemId = $extractItemId($itemGroup);
            if ($derivedItemId > 0) {
                $itemIds[] = $derivedItemId;
            }
        }
        if (!empty($itemIds)) {
            $itemIds = array_values(array_unique($itemIds));
            $itemReactionSummaries = Utils::getItemReactionSummaryByItemIds($itemIds);
            $itemCommentsLookup = Utils::getItemCommentsByItemIds($itemIds);
        }

        $itemGroupSeen = [];

        foreach ($changes['visitors'] as $visitor) {
            $timestampString = $visitor['last_view'] ?? null;
            if (!$timestampString) {
                continue;
            }
            $timestamp = strtotime($timestampString);
            if (!$timestamp) {
                continue;
            }
            $visitorName = trim(($visitor['first_name'] ?? '') . ' ' . ($visitor['last_name'] ?? ''));
            $profileUrl = isset($visitor['user_id']) ? "?to=family/users&user_id={$visitor['user_id']}" : '';
            $title = $visitorName !== ''
                ? $visitorName . ' visited ' . $siteName
                : 'A family member visited ' . $siteName;
            $feedEntries[] = [
                'type'      => 'visitor',
                'title'     => $title,
                'content'   => '',
                'content_html' => '',
                'meta'      => [
                    'actor_name'   => $visitorName,
                    'actor_id'     => $visitor['user_id'] ?? null,
                    'site_name'    => $siteName,
                ],
                'url'       => $profileUrl,
                'timestamp' => $timestamp,
                'raw_time'  => $timestampString,
                'relationship_to_user' => '',
                'descendancy' => [],
                'indirect_connection' => [],
                'interactions' => null,
                'media'     => [],
                'files'     => [],
                'details'   => [],
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
            $changeType = $discussion['change_type'] ?? '';
            $discussionContentRaw = isset($discussion['content']) ? stripslashes($discussion['content']) : '';
            $discussionPostRaw = isset($discussion['discussion_content']) ? stripslashes($discussion['discussion_content']) : '';
            $discussionBodyForDisplay = $discussionPostRaw !== '' ? $discussionPostRaw : $discussionContentRaw;
            $discussionSnippetHtml = '';
            $contentExpandId = '';
            $hasHtmlContent = strip_tags($discussionBodyForDisplay) !== $discussionBodyForDisplay;
            if ($discussionBodyForDisplay !== '') {
                $discussionContentForHtml = $hasHtmlContent
                    ? $discussionBodyForDisplay
                    : nl2br($discussionBodyForDisplay);

                $contentExpandId = 'feed_discussion_' . $discussion['discussionId'];
                if ($changeType === 'comment' && !empty($discussion['comment_id'])) {
                    $contentExpandId .= '_comment_' . (int) $discussion['comment_id'];
                } else {
                    $hashSeed = ($discussion['updated_at'] ?? '') . '|' . ($discussion['discussionId'] ?? '');
                    $contentExpandId .= '_discussion_' . substr(md5($hashSeed), 0, 6);
                }
                $truncatedDiscussion = $this->web->truncateText(
                    $discussionContentForHtml,
                    80,
                    'Read more',
                    $contentExpandId,
                    'expand'
                );
                $discussionSnippetHtml = nvFeedSanitizeHtml(stripslashes($truncatedDiscussion));
            }
            $discussionDescendancy = [];
            $discussionIndirectConnection = [];
            if ($isTreeDiscussion && !empty($discussion['individual_id'])) {
                $discussionDescendancy = $getDescendancyTrail($discussion['individual_id']);
                if (empty($discussionDescendancy)) {
                    $discussionIndirectConnection = $getIndirectConnection($discussion['individual_id']);
                }
                $discussionIndirectConnection = $decorateIndirectConnection($discussionIndirectConnection);
            }

            $discussionId = (int) ($discussion['discussionId'] ?? 0);
            $reactionSummary = $discussionReactionSummaries[$discussionId] ?? [];
            $comments = $discussionCommentsLookup[$discussionId] ?? [];
            $recentCommentIds = [];
            if ($changeType === 'comment') {
                $recentCommentId = (int) ($discussion['comment_id'] ?? 0);
                if ($recentCommentId > 0) {
                    $recentCommentIds[] = $recentCommentId;
                }
            }
            $discussionSubjectName = $resolveName(
                $discussion,
                ['tree_first_name', 'tree_first_names', 'first_names', 'first_name'],
                ['tree_last_name', 'last_name']
            );

            $entryType = $changeType === 'comment' ? 'comment' : 'discussion';
            $originalDiscussionTitle = isset($discussion['title']) ? stripslashes($discussion['title']) : '';
            $plainDiscussionTitle = trim(strip_tags($originalDiscussionTitle));
            $displayTitle = $plainDiscussionTitle;
            $discussionHeadlineHtml = '';
            $plainSummary = $createSnippet($discussionBodyForDisplay, 28);

            if ($plainDiscussionTitle !== '') {
                $discussionHeadlineHtml = '<h4 class="feed-discussion-headline">' . htmlspecialchars($plainDiscussionTitle, ENT_QUOTES, 'UTF-8') . '</h4>';
            }

            if ($entryType === 'discussion') {
                if ($isTreeDiscussion && $discussionSubjectName !== '') {
                    $displayTitle = 'Discussion about ' . $discussionSubjectName;
                } elseif ($displayTitle === '') {
                    $displayTitle = 'Discussion update';
                }
            } elseif ($entryType === 'comment') {
                if ($isTreeDiscussion && $discussionSubjectName !== '') {
                    $displayTitle = 'New comment on discussion about ' . $discussionSubjectName;
                } else {
                    $displayTitle = 'New comment on discussion';
                }
            }

            if ($displayTitle === '') {
                $displayTitle = $entryType === 'comment' ? 'New comment on discussion' : 'Discussion update';
            }

            $badgeMap = [];
            if (!empty($discussion['is_historical_event'])) {
                $badgeMap['historical'] = 'Historical event';
            }
            if (!empty($discussion['is_event'])) {
                $badgeMap['family'] = 'Family event';
            }
            $badgeList = [];
            foreach ($badgeMap as $variant => $label) {
                $label = trim((string) $label);
                if ($label === '') {
                    continue;
                }
                $badgeList[] = [
                    'label'   => $label,
                    'variant' => $variant,
                ];
            }

            if ($discussionHeadlineHtml !== '') {
                if ($discussionSnippetHtml !== '') {
                    $discussionSnippetHtml = $discussionHeadlineHtml . $discussionSnippetHtml;
                } else {
                    $discussionSnippetHtml = $discussionHeadlineHtml;
                }
                if ($entryType === 'discussion') {
                    if ($plainSummary !== '') {
                        $plainSummary = $plainDiscussionTitle !== '' ? $plainDiscussionTitle . ' - ' . $plainSummary : $plainSummary;
                    } elseif ($plainDiscussionTitle !== '') {
                        $plainSummary = $plainDiscussionTitle;
                    }
                }
            }

            $expandIdentifier = $contentExpandId !== '' ? $contentExpandId : 'feed_discussion_' . $discussionId;
            $hasExpandableContent = false;
            if ($discussionSnippetHtml !== '') {
                $hasExpandableContent = stripos($discussionSnippetHtml, 'expandStory(') !== false || stripos($discussionSnippetHtml, 'showStory(') !== false;
            }
            $discussionFullHtml = '';
            if ($hasExpandableContent && $discussionBodyForDisplay !== '') {
                $discussionContentForFull = $hasHtmlContent
                    ? $discussionBodyForDisplay
                    : nl2br($discussionBodyForDisplay);
                $discussionFullHtml = nvFeedSanitizeHtml($discussionContentForFull);
                if ($discussionHeadlineHtml !== '') {
                    $discussionFullHtml = $discussionHeadlineHtml . $discussionFullHtml;
                }
            }

            $feedEntries[] = [
                'type'      => $entryType,
                'title'     => $displayTitle,
                'content'   => $plainSummary,
                'content_html' => $discussionSnippetHtml,
                'content_full_html' => $hasExpandableContent ? $discussionFullHtml : '',
                'content_expandable' => $hasExpandableContent,
                'content_expand_id' => $hasExpandableContent ? $expandIdentifier : '',
                'meta'      => [
                    'actor_name' => trim(($discussion['user_first_name'] ?? '') . ' ' . ($discussion['user_last_name'] ?? '')),
                    'actor_id'   => $discussion['user_id'] ?? null,
                    'context'    => $isTreeDiscussion ? 'Family Tree Chat' : 'Community Chat',
                    'subject_name' => $isTreeDiscussion ? $discussionSubjectName : '',
                    'subject_id'   => $isTreeDiscussion ? (int) ($discussion['individual_id'] ?? 0) : null,
                    'badges'       => $badgeList,
                    'recent_comment_ids' => $recentCommentIds,
                ],
                'url'       => $isTreeDiscussion
                    ? "?to=family/individual&individual_id={$discussion['individual_id']}&tab=storiestab&discussion_id={$discussion['discussionId']}"
                    : "?to=communications/discussions&discussion_id={$discussion['discussionId']}",
                'timestamp' => $timestamp,
                'raw_time'  => $timestampString,
                'relationship_to_user' => $isTreeDiscussion ? $getRelationshipToUser($discussion['individual_id']) : '',
                'descendancy' => $discussionDescendancy,
                'indirect_connection' => $discussionIndirectConnection,
                'interactions' => [
                    'type'             => 'discussion',
                    'target_id'        => $discussionId,
                    'reaction_summary' => $reactionSummary,
                    'comments'         => $comments,
                ],
                'media'     => [],
                'files'     => [],
                'details'   => [],
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

            $individualId = $individual['individualId'] ?? 0;
            $individualDescendancy = $getDescendancyTrail($individualId);
            $individualIndirectConnection = [];
            if (empty($individualDescendancy)) {
                $individualIndirectConnection = $getIndirectConnection($individualId);
            }
            $individualIndirectConnection = $decorateIndirectConnection($individualIndirectConnection);

            $snippet = '';
            $lifeEvents = [];
            $birthYear = $individual['birth_year'] ?? null;
            $deathYear = $individual['death_year'] ?? null;
            if ($birthYear) {
                $lifeEvents[] = 'Born ' . $birthYear;
            }
            if ($deathYear) {
                $lifeEvents[] = 'Died ' . $deathYear;
            }
            if (!empty($lifeEvents)) {
                $snippet = implode(' - ', $lifeEvents);
            }

            $individualName = $resolveName(
                $individual,
                ['tree_first_name', 'tree_first_names', 'first_names', 'first_name'],
                ['tree_last_name', 'last_name']
            );
            $feedEntries[] = [
                'type'      => 'individual',
                'title'     => $individualName,
                'content'   => $snippet,
                'content_html' => '',
                'meta'      => [
                    'actor_name' => trim(($individual['user_first_name'] ?? '') . ' ' . ($individual['user_last_name'] ?? '')),
                    'actor_id'   => $individual['user_id'] ?? null,
                    'subject_name' => $individualName,
                    'subject_id'   => (int) $individualId,
                ],
                'url'       => "?to=family/individual&individual_id={$individualId}",
                'timestamp' => $timestamp,
                'raw_time'  => $timestampString,
                'relationship_to_user' => $getRelationshipToUser($individualId),
                'descendancy' => $individualDescendancy,
                'indirect_connection' => $individualIndirectConnection,
                'interactions' => null,
                'media'     => [],
                'files'     => [],
                'details'   => [],
            ];
        }

        foreach ($changes['relationships'] as $relationship) {
            $timestampString = $relationship['updated_at'] ?? $relationship['created'] ?? null;
            if (!$timestampString) {
                continue;
            }
            $timestamp = strtotime($timestampString);
            if (!$timestamp) {
                continue;
            }

            $primaryIndividualId = $relationship['individual_id'] ?? 0;
            $relationshipDescendancy = $getDescendancyTrail($primaryIndividualId);
            $relationshipIndirect = [];
            if (empty($relationshipDescendancy)) {
                $relationshipIndirect = $getIndirectConnection($primaryIndividualId);
            }
            $relationshipIndirect = $decorateIndirectConnection($relationshipIndirect);

            $relationshipLabel = $relationship['relationship_label'] ?? '';
            $content = '';
            if (!empty($relationship['related_first_names']) || !empty($relationship['related_last_name'])) {
                $content = trim(($relationship['related_first_names'] ?? '') . ' ' . ($relationship['related_last_name'] ?? ''));
            }
            if ($relationshipLabel !== '') {
                $content = trim($relationshipLabel . ' ' . $content);
            }

            $relationshipName = $resolveName(
                $relationship,
                ['tree_first_name', 'tree_first_names', 'first_names', 'first_name'],
                ['tree_last_name', 'last_name']
            );
            $feedEntries[] = [
                'type'      => 'relationship',
                'title'     => $relationshipName,
                'content'   => $content,
                'content_html' => '',
                'meta'      => [
                    'actor_name' => trim(($relationship['user_first_name'] ?? '') . ' ' . ($relationship['user_last_name'] ?? '')),
                    'actor_id'   => $relationship['user_id'] ?? null,
                    'subject_name' => $relationshipName,
                    'subject_id'   => (int) $primaryIndividualId,
                ],
                'url'       => "?to=family/individual&individual_id={$primaryIndividualId}&tab=relationshipstab",
                'timestamp' => $timestamp,
                'raw_time'  => $timestampString,
                'relationship_to_user' => $getRelationshipToUser($primaryIndividualId),
                'descendancy' => $relationshipDescendancy,
                'indirect_connection' => $relationshipIndirect,
                'interactions' => null,
                'media'     => [],
                'files'     => [],
                'details'   => [],
            ];
        }

        $nonInteractiveItemGroups = ['Key Image'];
        foreach ($changes['items'] as $itemGroup) {
            $contentHtml = '';
            $mediaItems = [];
            $fileLinks = [];
            $detailRows = [];
            $gpsDetails = [];
            $spouseNames = [];
            $contentHtml = '';
            if (empty($itemGroup['items']) || !is_array($itemGroup['items'])) {
                continue;
            }
            $firstItem = $itemGroup['items'][0];
            $timestampString = $firstItem['updated'] ?? $firstItem['created'] ?? null;
            if (!$timestampString) {
                continue;
            }
            $timestamp = strtotime($timestampString);
            if (!$timestamp) {
                continue;
            }

            $groupUniqueId = (string) ($firstItem['unique_id'] ?? ($firstItem['item_identifier'] ?? ''));
            $groupIndividualId = (int) ($firstItem['item_links_individual_id'] ?? $firstItem['individualId'] ?? 0);
            $itemGroupKey = $groupUniqueId !== '' ? $groupUniqueId . ':' . $groupIndividualId : null;
            if ($itemGroupKey !== null) {
                if (isset($itemGroupSeen[$itemGroupKey])) {
                    continue;
                }
                $itemGroupSeen[$itemGroupKey] = true;
            }

        $groupTitle = trim((string) ($itemGroup['item_group_name'] ?? ''));
        $groupTitleTrimmed = $groupTitle !== '' ? $groupTitle : 'New update';
        $displayGroupTitle = $groupTitleTrimmed;
        if (strcasecmp($groupTitleTrimmed, 'Key Image') === 0) {
            $displayGroupTitle = 'Profile Picture';
        }
        $personName = $resolveName(
            $firstItem,
            ['tree_first_name', 'tree_first_names', 'first_names', 'first_name'],
            ['tree_last_name', 'last_name']
        );
            $changedItemIds = [];
            foreach ($itemGroup['items'] as $changedRow) {
                $changedId = isset($changedRow['item_id']) ? (int) $changedRow['item_id'] : 0;
                if ($changedId > 0) {
                    $changedItemIds[$changedId] = true;
                }
            }
            $snippet = '';
            $mediaKeys = [];
            $fileKeys = [];
            $detailRowLookup = [];
            $processedItemIds = [];

            $addDetailRow = static function ($label, $value, $link = '', $isHtml = false, $isRecent = false) use (&$detailRows, &$detailRowLookup) {
                $normalizedLabel = strtolower(trim((string) $label));
                $normalizedValue = is_string($value) ? trim($value) : (is_array($value) ? json_encode($value) : (string) $value);
                $lookupKey = $normalizedLabel . '|' . md5($normalizedValue . '|' . $link . '|' . ($isHtml ? '1' : '0'));
                if (isset($detailRowLookup[$lookupKey])) {
                    return;
                }
                $detailRowLookup[$lookupKey] = true;
                $detailRows[] = [
                    'label'     => $label,
                    'value'     => $value,
                    'link'      => $link,
                    'is_html'   => $isHtml,
                    'is_recent' => $isRecent,
                ];
            };

            foreach ($itemGroup['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemId = isset($item['item_id']) ? (int) $item['item_id'] : 0;
                if ($itemId > 0 && isset($processedItemIds[$itemId])) {
                    continue;
                }
                if ($itemId > 0) {
                    $processedItemIds[$itemId] = true;
                }
                $isRecentChange = $itemId > 0 && isset($changedItemIds[$itemId]);
                $detailType = $item['detail_type'] ?? '';
                $detailLabel = $detailType ? $detailType : ($item['item_group_name'] ?? $groupTitle);
                $detailStyle = $itemStyles[$detailType] ?? 'text';
                $detailValue = isset($item['detail_value']) ? trim((string) $item['detail_value']) : '';
                $labelLower = strtolower($detailLabel);
                $isUrlDetail = ($labelLower === 'url');
                $isGpsDetail = ($labelLower === 'gps');
                $isSpouseDetail = in_array($labelLower, ['spouse', 'partner', 'husband', 'wife'], true);

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
                                'full' => $item['file_path'],
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

                if ($isUrlDetail) {
                    $linkIconHtml = '<i class="fas fa-link" aria-hidden="true"></i><span class="sr-only">Open website</span>';
                    $addDetailRow($detailLabel, $linkIconHtml, $detailValue, true, $isRecentChange);
                    if ($snippet === '') {
                        $snippet = 'New website link added.';
                    }
                    continue;
                }

                if ($isGpsDetail) {
                    $coordinateValue = preg_replace('/\s+/', ' ', $detailValue);
                    if ($coordinateValue !== '') {
                        $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($coordinateValue);
                        $mapIconHtml = '<i class="fas fa-map-marker-alt" aria-hidden="true"></i><span class="sr-only">View on Google Maps</span>';
                        $addDetailRow('GPS', $mapIconHtml, $mapsUrl, true, $isRecentChange);
                        $gpsDetails[] = [
                            'url' => $mapsUrl,
                            'coordinates' => $coordinateValue,
                        ];
                    }
                    continue;
                }

                if ($detailStyle === 'file') {
                    continue;
                }

                if ($detailStyle === 'individual') {
                    $linkTarget = !empty($item['individual_name_id'])
                        ? "?to=family/individual&individual_id={$item['individual_name_id']}"
                        : '';
                    $individualName = trim((string) ($item['individual_name'] ?? $detailValue));
                    $addDetailRow($detailLabel, $individualName, $linkTarget, false, $isRecentChange);
                    if ($isSpouseDetail && $individualName !== '') {
                        $spouseNames[] = $individualName;
                    }
                    if ($snippet === '' && !$isSpouseDetail && $individualName !== '') {
                        $snippet = $createSnippet($individualName, 20);
                    }
                    continue;
                }

                if ($snippet === '' && $detailValue !== '' && $detailType !== 'Private' && !$isSpouseDetail) {
                    $snippet = $createSnippet($detailValue, 20);
                }

                $allowsBreaks = ($detailStyle === 'textarea');
                if ($detailStyle === 'textarea') {
                    $richValue = nvFeedSanitizeHtml($detailValue);
                    if ($richValue === '') {
                        $richValue = $normalizeValue($detailValue);
                    }
                    if ($richValue === '') {
                        continue;
                    }
                    $addDetailRow($detailLabel, $richValue, '', true, $isRecentChange);
                    continue;
                }

                $displayValue = $detailValue;
                $detailLength = function_exists('mb_strlen') ? mb_strlen($detailValue) : strlen($detailValue);
                if ($detailLength > 360) {
                    $displayValue = $createSnippet($detailValue, 60);
                } elseif ($detailStyle === 'date') {
                    $dateCandidate = strtotime($detailValue);
                    if ($dateCandidate) {
                        $displayValue = date('j M Y', $dateCandidate);
                    }
                }

                $addDetailRow($detailLabel, $allowsBreaks ? $normalizeValue($displayValue) : (string) $displayValue, '', $allowsBreaks, $isRecentChange);
            }

            if ($snippet === '' && !empty($firstItem['file_path'])) {
                $snippet = 'New file attached.';
            }

            $normalizedTitle = strtolower($groupTitleTrimmed);
            $feedEntryType = in_array($normalizedTitle, ['marriage', 'divorce'], true) ? 'relationship' : 'item';

            if (!empty($spouseNames)) {
                $normalizedSpouses = array_map(static function ($name) {
                    if (!is_string($name)) {
                        $name = (string) $name;
                    }
                    return trim($name);
                }, $spouseNames);
                $uniqueSpouses = array_values(array_unique($normalizedSpouses));
                $primaryNormalized = '';
                if ($personName !== '') {
                    $primaryNormalized = function_exists('mb_strtolower')
                        ? mb_strtolower($personName, 'UTF-8')
                        : strtolower($personName);
                }
                $uniqueSpouses = array_values(array_filter($uniqueSpouses, static function ($name) use ($primaryNormalized) {
                    if ($name === '') {
                        return false;
                    }
                    if ($primaryNormalized === '') {
                        return true;
                    }
                    $compareValue = function_exists('mb_strtolower')
                        ? mb_strtolower($name, 'UTF-8')
                        : strtolower($name);
                    return $compareValue !== $primaryNormalized;
                }));
                if (!empty($uniqueSpouses)) {
                    $spouseSummary = implode(' and ', $uniqueSpouses);
                    if ($normalizedTitle === 'marriage') {
                        $snippet = ($personName !== '' ? $personName . ' married ' . $spouseSummary : 'Married ' . $spouseSummary) . '.';
                    } elseif ($normalizedTitle === 'divorce') {
                        $snippet = ($personName !== '' ? $personName . ' divorced ' . $spouseSummary : 'Divorced ' . $spouseSummary) . '.';
                    }
                }
            }
            if (!empty($gpsDetails)) {
                $primaryGps = $gpsDetails[0];
                $mapUrlEscaped = htmlspecialchars($primaryGps['url'], ENT_QUOTES, 'UTF-8');
                $mapLinkHtml = '<a href="' . $mapUrlEscaped . '" target="_blank" rel="noopener" class="feed-item-location-link" aria-label="View location on Google Maps">&#128205;</a>';
                if ($snippet !== '') {
                    $contentHtml = htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') . ' ' . $mapLinkHtml;
                    $snippet = '';
                } else {
                    $contentHtml = 'A geographical location was added ' . $mapLinkHtml;
                }
            }

            $interaction = null;
            $itemId = $extractItemId($itemGroup);
            if ($itemId > 0 && !in_array($groupTitleTrimmed, $nonInteractiveItemGroups, true)) {
                $interaction = [
                    'type'             => 'item',
                    'target_id'        => $itemId,
                    'item_identifier'  => $firstItem['item_identifier'] ?? null,
                    'reaction_summary' => $itemReactionSummaries[$itemId] ?? [],
                    'comments'         => $itemCommentsLookup[$itemId] ?? [],
                ];
            }

            $primaryIndividualId = $firstItem['individualId'] ?? $firstItem['item_links_individual_id'] ?? 0;
            $primaryDescendancy = $getDescendancyTrail($primaryIndividualId);
            $primaryIndirect = [];
            if (empty($primaryDescendancy)) {
                $primaryIndirect = $getIndirectConnection($primaryIndividualId);
            }
            $primaryIndirect = $decorateIndirectConnection($primaryIndirect);

        $feedEntries[] = [
            'type'      => $feedEntryType,
            'title'     => $displayGroupTitle !== '' ? $displayGroupTitle . ' update for ' . $personName : 'New update for ' . $personName,
                'content'   => $snippet,
                'content_html' => $contentHtml,
                'meta'      => [
                    'actor_name'   => trim(($firstItem['first_name'] ?? '') . ' ' . ($firstItem['last_name'] ?? '')),
                    'actor_id'     => $firstItem['user_id'] ?? null,
                    'subject_name' => $personName,
                    'privacy'      => $itemGroup['privacy'] ?? 'private',
                    'subject_id'   => (int) $primaryIndividualId,
                ],
                'url'       => $primaryIndividualId > 0 ? "?to=family/individual&individual_id={$primaryIndividualId}&tab=eventstab" : '',
                'timestamp' => $timestamp,
                'raw_time'  => $timestampString,
                'relationship_to_user' => $getRelationshipToUser($primaryIndividualId),
                'descendancy' => $primaryDescendancy,
                'indirect_connection' => $primaryIndirect,
                'interactions' => $interaction,
                'media'     => $mediaItems,
                'files'     => $fileLinks,
                'details'   => $detailRows,
            ];
            unset($interaction, $mediaItems, $fileLinks, $detailRows, $gpsDetails, $spouseNames, $contentHtml);
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

            $personName = $resolveName(
                $file,
                ['tree_first_name', 'tree_first_names', 'first_names', 'first_name'],
                ['tree_last_name', 'last_name']
            );
            $fileIndividualId = $file['individualId'] ?? $file['item_links_individual_id'] ?? 0;
            $fileDescendancy = $getDescendancyTrail($fileIndividualId);
            $fileIndirect = [];
            if (empty($fileDescendancy)) {
                $fileIndirect = $getIndirectConnection($fileIndividualId);
            }
            $fileIndirect = $decorateIndirectConnection($fileIndirect);

            $feedEntries[] = [
                'type'      => 'file',
                'title'     => $personName !== '' ? $personName . ' media added' : 'New media uploaded',
                'content'   => $createSnippet($file['file_description'] ?? '', 18),
                'content_html' => '',
                'meta'      => [
                    'actor_name'   => trim(($file['user_first_name'] ?? '') . ' ' . ($file['user_last_name'] ?? '')),
                    'actor_id'     => $file['user_id'] ?? null,
                    'subject_name' => $personName,
                    'file_type'    => $file['file_type'] ?? '',
                    'subject_id'   => (int) $fileIndividualId,
                ],
                'url'       => $fileIndividualId > 0 ? "?to=family/individual&individual_id={$fileIndividualId}&tab=mediatab&file_id={$file['id']}" : '',
                'timestamp' => $timestamp,
                'raw_time'  => $timestampString,
                'relationship_to_user' => $getRelationshipToUser($fileIndividualId),
                'descendancy' => $fileDescendancy,
                'indirect_connection' => $fileIndirect,
                'interactions' => null,
                'media'     => $file['file_path'] ?? '',
                'files'     => [],
                'details'   => [],
            ];
        }

        usort($feedEntries, static function ($a, $b) {
            return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
        });

        $feedEntries = array_values($feedEntries);
        foreach ($feedEntries as &$entry) {
            $entry['html'] = $this->renderFeedEntry(
                $entry,
                $feedTypeMeta,
                $currentUserId,
                $isCurrentUserAdmin,
                $reactionEmojiMap,
                $getRelationshipToUser
            );
        }
        unset($entry);

        return $feedEntries;
    }

    private function buildSummary(array $changes, array $counts = array()): array
    {
        $definitions = array(
            'Discussions'   => array('key' => 'discussions',   'singular' => 'discussion',   'plural' => 'discussions'),
            'Individuals'   => array('key' => 'individuals',   'singular' => 'individual',   'plural' => 'individuals'),
            'Relationships' => array('key' => 'relationships', 'singular' => 'relationship', 'plural' => 'relationships'),
            'Events'        => array('key' => 'items',         'singular' => 'event',        'plural' => 'events'),
            'Files'         => array('key' => 'files',         'singular' => 'file',         'plural' => 'files'),
        );

        $summary = array();
        foreach ($definitions as $label => $meta) {
            $key = $meta['key'];
            $count = isset($counts[$key])
                ? (int) $counts[$key]
                : (isset($changes[$key]) && is_array($changes[$key]) ? count($changes[$key]) : 0);

            if ($count > 0) {
                $word = ($count === 1) ? $meta['singular'] : $meta['plural'];
                $summary[$label] = $count . ' new ' . $word;
            } else {
                $summary[$label] = '';
            }
        }

        return $summary;
    }

    private function buildEventDescriptors(array $changes): array
    {
        $events = [];
        $itemDescriptorSeen = [];
        $itemDescriptorSeen = [];

        foreach ($changes['visitors'] ?? [] as $visitor) {
            $timestampString = $visitor['last_view'] ?? null;
            if (!$timestampString) {
                continue;
            }
            $timestamp = strtotime($timestampString);
            if (!$timestamp) {
                continue;
            }
            $events[] = [
                'bucket'    => 'visitors',
                'type'      => 'visitor',
                'timestamp' => $timestamp,
                'record'    => $visitor,
            ];
        }

        foreach ($changes['discussions'] ?? [] as $discussion) {
            $timestampString = $discussion['updated_at'] ?? null;
            if (!$timestampString) {
                continue;
            }
            $timestamp = strtotime($timestampString);
            if (!$timestamp) {
                continue;
            }
            $events[] = [
                'bucket'    => 'discussions',
                'type'      => ($discussion['change_type'] ?? '') === 'comment' ? 'comment' : 'discussion',
                'timestamp' => $timestamp,
                'record'    => $discussion,
            ];
        }

        foreach ($changes['individuals'] ?? [] as $individual) {
            $timestampString = $individual['updated'] ?? $individual['created'] ?? null;
            if (!$timestampString) {
                continue;
            }
            $timestamp = strtotime($timestampString);
            if (!$timestamp) {
                continue;
            }
            $events[] = [
                'bucket'    => 'individuals',
                'type'      => 'individual',
                'timestamp' => $timestamp,
                'record'    => $individual,
            ];
        }

        foreach ($changes['items'] ?? [] as $itemGroup) {
            $firstItem = $itemGroup['items'][0] ?? null;
            if (!is_array($firstItem)) {
                continue;
            }
            $timestampString = $firstItem['updated'] ?? $firstItem['created'] ?? null;
            if (!$timestampString) {
                continue;
            }
            $timestamp = strtotime($timestampString);
            if (!$timestamp) {
                continue;
            }
            $descriptorSubject = (int) ($firstItem['item_links_individual_id'] ?? $firstItem['individualId'] ?? 0);
            $descriptorUnique = (string) ($firstItem['unique_id'] ?? ($firstItem['item_identifier'] ?? ''));
            $descriptorKey = $descriptorUnique !== '' ? $descriptorUnique . ':' . $descriptorSubject : null;
            if ($descriptorKey !== null) {
                if (isset($itemDescriptorSeen[$descriptorKey])) {
                    continue;
                }
                $itemDescriptorSeen[$descriptorKey] = true;
            }
            $events[] = [
                'bucket'    => 'items',
                'type'      => 'item',
                'timestamp' => $timestamp,
                'record'    => $itemGroup,
            ];
        }

        foreach ($changes['files'] ?? [] as $file) {
            $timestampString = $file['upload_date'] ?? $file['updated'] ?? null;
            if (!$timestampString) {
                continue;
            }
            $timestamp = strtotime($timestampString);
            if (!$timestamp) {
                continue;
            }
            $events[] = [
                'bucket'    => 'files',
                'type'      => 'file',
                'timestamp' => $timestamp,
                'record'    => $file,
            ];
        }

        usort($events, static function (array $a, array $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return $events;
    }

    private function selectChangesForDescriptors(array $changes, array $descriptors): array
    {
        $selected = [
            'visitors'      => [],
            'discussions'   => [],
            'relationships' => [],
            'individuals'   => [],
            'items'         => [],
            'files'         => [],
        ];

        foreach ($descriptors as $descriptor) {
            $bucket = $descriptor['bucket'];
            if (!array_key_exists($bucket, $selected)) {
                $selected[$bucket] = [];
            }
            $selected[$bucket][] = $descriptor['record'];
        }

        if (isset($changes['last_view'])) {
            $selected['last_view'] = $changes['last_view'];
        }

        return $selected;
    }

    private function renderFeedEntry(array $entry, array $feedTypeMeta, int $currentUserId, bool $isCurrentUserAdmin, array $reactionEmojiMap, callable $getRelationshipToUser): string
    {
        $type = $entry['type'];
        $meta = $feedTypeMeta[$type] ?? ['label' => ucfirst($type), 'icon' => 'fas fa-circle'];
        $timestampLabel = isset($entry['timestamp']) ? date('l, d F Y g:ia', $entry['timestamp']) : '';
        $actorInitial = '';
        if (empty($entry['meta']['actor_id']) && !empty($entry['meta']['actor_name'])) {
            $actorInitial = strtoupper(substr($entry['meta']['actor_name'], 0, 1));
        }
        $recentCommentIds = [];
        if (!empty($entry['meta']['recent_comment_ids']) && is_array($entry['meta']['recent_comment_ids'])) {
            foreach ($entry['meta']['recent_comment_ids'] as $recentId) {
                $recentId = (int) $recentId;
                if ($recentId > 0) {
                    $recentCommentIds[] = $recentId;
                }
            }
            $recentCommentIds = array_values(array_unique($recentCommentIds));
        }

        $descendancyLineSegments = [];
        if (!empty($entry['descendancy']) && is_array($entry['descendancy'])) {
            foreach ($entry['descendancy'] as $descendant) {
                $label = trim((string) ($descendant[0] ?? ''));
                $id = isset($descendant[1]) ? (int) $descendant[1] : 0;
                if ($label === '') {
                    continue;
                }
                if ($id > 0) {
                    $descendancyLineSegments[] = '<a href="?to=family/individual&individual_id=' . $id . '" class="hover:text-burnt-orange">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
                } else {
                    $descendancyLineSegments[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
                }
            }
        }

        $indirectConnection = (!empty($entry['indirect_connection']) && is_array($entry['indirect_connection']))
            ? $entry['indirect_connection']
            : null;

        if (empty($descendancyLineSegments) && $indirectConnection && !empty($indirectConnection['descendancy_path'])) {
            $indirectSegments = [];
            foreach ((array) $indirectConnection['descendancy_path'] as $node) {
                $nodeName = trim((string) ($node['name'] ?? ''));
                if ($nodeName === '') {
                    continue;
                }
                $nodeId = isset($node['id']) ? (int) $node['id'] : 0;
                if ($nodeId > 0) {
                    $indirectSegments[] = '<a href="?to=family/individual&individual_id=' . $nodeId . '" class="hover:text-burnt-orange">' . htmlspecialchars($nodeName, ENT_QUOTES, 'UTF-8') . '</a>';
                } else {
                    $indirectSegments[] = htmlspecialchars($nodeName, ENT_QUOTES, 'UTF-8');
                }
            }
            if (!empty($indirectSegments)) {
                $descendancyLineSegments = $indirectSegments;
            }
        }

        $relationshipLine = '';
        if (!empty($entry['relationship_to_user'])) {
            $relationshipLine = trim((string) $entry['relationship_to_user']);
        } elseif (!empty($entry['descendancy'])) {
            $lastDescendant = end($entry['descendancy']);
            if (is_array($lastDescendant) && !empty($lastDescendant[1])) {
                $relationshipLine = $getRelationshipToUser((int) $lastDescendant[1]);
            }
        }

        $badgeList = [];
        if (!empty($entry['meta']['badges']) && is_array($entry['meta']['badges'])) {
            foreach ($entry['meta']['badges'] as $badge) {
                if (!is_array($badge)) {
                    continue;
                }
                $label = trim((string) ($badge['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $variant = trim((string) ($badge['variant'] ?? ''));
                $badgeList[] = [
                    'label'   => $label,
                    'variant' => $variant,
                ];
            }
        }
        if ($indirectConnection) {
            $rawSteps = (array) ($indirectConnection['steps'] ?? []);
            $processedSteps = [];
            $stepTotal = count($rawSteps);
            for ($index = 0; $index < $stepTotal; $index++) {
                $currentStep = $rawSteps[$index];
                $label = trim((string) ($currentStep['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                if ($label === 'parent of') {
                    $generationCount = 1;
                    $targetName = trim((string) ($currentStep['to_name'] ?? ''));
                    while (($index + 1) < $stepTotal) {
                        $nextLabel = trim((string) ($rawSteps[$index + 1]['label'] ?? ''));
                        if ($nextLabel !== 'parent of') {
                            break;
                        }
                        $index++;
                        $generationCount++;
                        $targetName = trim((string) ($rawSteps[$index]['to_name'] ?? ''));
                    }
                    if ($targetName !== '') {
                        $processedSteps[] = [
                            'label' => $this->formatAncestorLabel($generationCount),
                            'to_name' => $targetName,
                        ];
                    }
                    continue;
                }
                if ($label === 'child of') {
                    $generationCount = 1;
                    $targetName = trim((string) ($currentStep['to_name'] ?? ''));
                    while (($index + 1) < $stepTotal) {
                        $nextLabel = trim((string) ($rawSteps[$index + 1]['label'] ?? ''));
                        if ($nextLabel !== 'child of') {
                            break;
                        }
                        $index++;
                        $generationCount++;
                        $targetName = trim((string) ($rawSteps[$index]['to_name'] ?? ''));
                    }
                    if ($targetName !== '') {
                        $processedSteps[] = [
                            'label' => $this->formatDescendantLabel($generationCount),
                            'to_name' => $targetName,
                        ];
                    }
                    continue;
                }
                $processedSteps[] = [
                    'label' => $label,
                    'to_name' => trim((string) ($currentStep['to_name'] ?? '')),
                ];
            }

            $stepPhrases = [];
            foreach ($processedSteps as $processed) {
                $label = trim((string) ($processed['label'] ?? ''));
                $targetName = trim((string) ($processed['to_name'] ?? ''));
                if ($label === '' || $targetName === '') {
                    continue;
                }
                $phraseLabel = $label;
                $lowerLabel = strtolower($label);
                if (strpos($lowerLabel, 'spouse of') === 0 || strpos($lowerLabel, 'partner of') === 0) {
                    $phraseLabel = 'the ' . $label;
                }
                $stepPhrases[] = rtrim($phraseLabel) . ' ' . $targetName;
            }
            $connectionText = '';
            if (!empty($stepPhrases)) {
                foreach ($stepPhrases as $index => $phrase) {
                    if ($index === 0) {
                        $connectionText = $phrase;
                    } else {
                        $connectionText .= ', who is ' . $phrase;
                    }
                }
            }
            $relativeName = trim((string) ($indirectConnection['via_individual_name'] ?? ''));
            $relativeRelationship = trim((string) ($indirectConnection['user_relationship'] ?? ''));
            if ($relativeRelationship !== '') {
                if ($connectionText !== '') {
                    $connectionText .= ', who is your ' . $relativeRelationship;
                } elseif ($relativeName !== '') {
                    $connectionText = $relativeName . ' is your ' . $relativeRelationship;
                } else {
                    $connectionText = 'Your ' . $relativeRelationship;
                }
            }
            if ($connectionText === '' && !empty($indirectConnection['explanation'])) {
                $connectionText = trim((string) $indirectConnection['explanation']);
            }
            if ($connectionText !== '') {
                $relationshipLine = $connectionText;
            }
        }

        $web = $this->web;

        if ($type === 'visitor') {
            $titleText = trim((string) ($entry['title'] ?? ''));
            if ($titleText === '') {
                $titleText = 'Recent visitor';
            }
            $timeDisplay = '';
            if (!empty($entry['raw_time'])) {
                $timeDisplay = $web->timeSince($entry['raw_time']);
            } elseif (!empty($entry['timestamp'])) {
                $timeDisplay = $web->timeSince(date('Y-m-d H:i:s', (int) $entry['timestamp']));
            }

            ob_start();
            ?>
            <article class="feed-item bg-white shadow-sm border border-gray-200 rounded-lg p-4 flex items-center gap-4" data-feed-type="visitor">
                <div class="feed-item-icon text-xl text-ocean-blue flex-shrink-0">
                    <i class="<?= htmlspecialchars($meta['icon']) ?>"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm sm:text-base font-semibold text-brown mb-0"><?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($timeDisplay !== ''): ?>
                        <span class="feed-item-timestamp text-xs text-gray-500" title="<?= htmlspecialchars($timestampLabel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($timeDisplay, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
            </article>
            <?php
            return trim(ob_get_clean());
        }

        ob_start();
        static $subjectImageCache = [];
        $subjectId = isset($entry['meta']['subject_id']) ? (int) $entry['meta']['subject_id'] : 0;
        $subjectName = trim((string) ($entry['meta']['subject_name'] ?? ''));
        $subjectImagePath = '';
        $subjectAlt = $subjectName !== '' ? $subjectName : 'Profile picture';
        $hasSubjectImage = false;
        if ($subjectId > 0) {
            if (!array_key_exists($subjectId, $subjectImageCache)) {
                $subjectImageCache[$subjectId] = Utils::getKeyImage($subjectId);
            }
            $subjectImagePath = (string) $subjectImageCache[$subjectId];
            if ($subjectImagePath !== '') {
                $hasSubjectImage = true;
            }
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
                    <?php
                        $metaSubjectName = trim((string) ($entry['meta']['subject_name'] ?? ''));
                        if ($metaSubjectName !== '') {
                            ?>
                            <span class="hidden sm:inline">&bull; <?= htmlspecialchars($metaSubjectName, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php
                        }
                    ?>
                    <?php if (!empty($badgeList)): ?>
                        <?php foreach ($badgeList as $badge): ?>
                            <?php
                                $badgeLabel = htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8');
                                $badgeVariant = trim((string) ($badge['variant'] ?? ''));
                                $badgeVariantClass = '';
                                if ($badgeVariant !== '') {
                                    $sanitizedVariant = preg_replace('/[^a-z0-9_-]/i', '', $badgeVariant);
                                    if ($sanitizedVariant !== '') {
                                        $badgeVariantClass = ' feed-item-badge--' . $sanitizedVariant;
                                    }
                                }
                                $badgeClass = 'feed-item-badge' . $badgeVariantClass;
                            ?>
                            <span class="<?= $badgeClass ?>"><?= $badgeLabel ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($entry['raw_time'])): ?>
                        <span class="feed-item-timestamp ml-auto" title="<?= htmlspecialchars($timestampLabel) ?>">
                            <?= $web->timeSince($entry['raw_time']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($hasSubjectImage || !empty($entry['title']) || $relationshipLine !== '' || !empty($descendancyLineSegments)): ?>
                    <div class="feed-item-hero flex gap-3 items-start mb-3">
                        <?php if ($hasSubjectImage): ?>
                            <div class="feed-item-subject-image flex-shrink-0">
                                <img src="<?= htmlspecialchars($subjectImagePath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($subjectAlt, ENT_QUOTES, 'UTF-8') ?>" class="subject-key-image">
                            </div>
                        <?php endif; ?>
                        <div class="feed-item-heading-main flex-1">
                            <?php if (!empty($entry['title'])): ?>
                                <h4 class="feed-item-title text-lg font-semibold text-brown mb-1">
                                    <?php if (!empty($entry['url'])): ?>
                                        <a class="hover:text-burnt-orange" href="<?= $entry['url'] ?>"><?= htmlspecialchars($entry['title']) ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($entry['title']) ?>
                                    <?php endif; ?>
                                </h4>
                            <?php endif; ?>
                            <?php if ($relationshipLine !== ''): ?>
                                <p class="feed-item-relationship text-xs text-gray-500 mb-0">
                                    <span class="font-semibold text-brown mr-1">Relationship:</span>
                                    <?= htmlspecialchars(rtrim($relationshipLine, '.') . '.') ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($descendancyLineSegments)): ?>
                                <p class="feed-item-descendancy text-xs text-gray-500 mb-1">
                                    <span class="font-semibold text-brown mr-1">Line:</span>
                                    <?= implode(' <span class="mx-1 text-gray-400">&gt;</span> ', $descendancyLineSegments) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php
                    $contentHtml = $entry['content_html'] ?? '';
                    $fullContentHtml = $entry['content_full_html'] ?? '';
                    $contentExpandId = isset($entry['content_expand_id']) ? trim((string) $entry['content_expand_id']) : '';
                    $contentIsExpandable = !empty($entry['content_expandable']) && $contentExpandId !== '';
                ?>
                <?php if ($contentHtml !== ''): ?>
                    <div class="feed-item-summary text-sm text-gray-600 mb-3">
                        <?php if ($contentIsExpandable): ?>
                            <?php $expandIdEscaped = htmlspecialchars($contentExpandId, ENT_QUOTES, 'UTF-8'); ?>
                            <div id="<?= $expandIdEscaped ?>"><?= $contentHtml ?></div>
                            <div id="full<?= $expandIdEscaped ?>" class="feed-item-summary-full hidden">
                                <?= $fullContentHtml !== '' ? $fullContentHtml : $contentHtml ?>
                                <span class="feed-summary-collapse" role="button" tabindex="0"
                                      onclick="shrinkStory('<?= $expandIdEscaped ?>')"
                                      onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();shrinkStory('<?= $expandIdEscaped ?>');}">
                                    less &hellip;
                                </span>
                            </div>
                        <?php else: ?>
                            <?= $contentHtml ?>
                        <?php endif; ?>
                    </div>
                <?php elseif (!empty($entry['content'])): ?>
                    <p class="feed-item-summary text-sm text-gray-600 mb-3"><?= nl2br(htmlspecialchars($entry['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                <?php endif; ?>
                <?php if ($type === 'item' && !empty($entry['media']) && is_array($entry['media'])): ?>
                    <div class="feed-item-media-grid mb-3">
                        <?php foreach ($entry['media'] as $media): ?>
                            <?php
                                $mediaSrc = htmlspecialchars($media['src'], ENT_QUOTES, 'UTF-8');
                                $mediaAlt = htmlspecialchars($media['alt'] ?? '', ENT_QUOTES, 'UTF-8');
                                $mediaFull = htmlspecialchars($media['full'] ?? $media['src'], ENT_QUOTES, 'UTF-8');
                            ?>
                            <a href="<?= $mediaFull ?>" target="_blank" rel="noopener">
                                <img src="<?= $mediaSrc ?>" alt="<?= $mediaAlt ?>" class="feed-item-thumb feed-item-media-image">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                    <?php if ($type === 'item' && !empty($entry['details'])): ?>
                        <ul class="feed-item-details text-sm text-gray-700 mb-3">
                        <?php foreach ($entry['details'] as $detail): ?>
                            <?php
                                $detailIsRecent = !empty($detail['is_recent']);
                                $detailClasses = 'feed-item-detail-row';
                                if ($detailIsRecent) {
                                    $detailClasses .= ' feed-item-detail-row--recent';
                                }
                            ?>
                            <li class="<?= $detailClasses ?>">
                                <span class="feed-item-detail-label"><?= htmlspecialchars($detail['label']) ?>:</span>
                                <?php if (!empty($detail['link'])): ?>
                                    <?php
                                        $detailLink = (string) $detail['link'];
                                        $isExternalDetailLink = preg_match('/^https?:/i', $detailLink) === 1;
                                    ?>
                                    <a href="<?= htmlspecialchars($detailLink) ?>" class="feed-item-detail-link hover:text-burnt-orange" <?= $isExternalDetailLink ? 'target="_blank" rel="noopener"' : '' ?>>
                                        <?php if (!empty($detail['is_html'])): ?>
                                            <?= $detail['value'] ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($detail['value']) ?>
                                        <?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="feed-item-detail-text">
                                        <?php if (!empty($detail['is_html'])): ?>
                                            <?= $detail['value'] ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($detail['value'], ENT_QUOTES, 'UTF-8') ?>
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
                <?php if ($type === 'file' && !empty($entry['media']) && is_string($entry['media'])): ?>
                    <div class="feed-item-media mb-3">
                        <?php $fileMediaSrc = htmlspecialchars($entry['media'], ENT_QUOTES, 'UTF-8'); ?>
                        <a href="<?= $fileMediaSrc ?>" target="_blank" rel="noopener">
                            <img src="<?= $fileMediaSrc ?>" alt="<?= htmlspecialchars($entry['title'] ?? 'Media', ENT_QUOTES, 'UTF-8') ?>" class="feed-item-thumb h-24 w-24 object-cover rounded-md border">
                        </a>
                    </div>
                <?php endif; ?>
                <div class="feed-item-footer flex flex-wrap items-center gap-3 text-xs text-gray-500">
                    <?php if (!empty($entry['meta']['actor_name'])): ?>
                        <span>by <?= htmlspecialchars($entry['meta']['actor_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($type === 'item' && !empty($entry['meta']['privacy']) && $entry['meta']['privacy'] === 'private'): ?>
                        <span class="inline-flex items-center gap-1 text-warm-red"><i class="fas fa-lock"></i> Private</span>
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
                        $reactionSummaryHtml = $this->renderReactionSummaryHtml($interaction['reaction_summary'] ?? [], $reactionEmojiMap);
                        $reactionEmoticons = Web::getReactionEmoticons();
                        $reactionOrder = ['like', 'love', 'haha', 'wow', 'sad', 'angry', 'care', 'remove'];
                        $comments = is_array($interaction['comments'] ?? null) ? $interaction['comments'] : [];
                ?>
                    <div class="feed-item-interactions mt-4" data-feed-interaction="<?= htmlspecialchars($interactionType, ENT_QUOTES, 'UTF-8') ?>" <?= $targetAttributeName ?>="<?= $targetId ?>">
                        <div class="<?= $reactionContainerClass ?> feed-reactions flex items-center gap-3" <?= $targetAttributeName ?>="<?= $targetId ?>"<?= $itemIdentifierAttribute ?>>
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
                            <?php if (!empty($reactionEmoticons)): ?>
                                <div class="reaction-buttons" title="Reactions">
                                    <?php foreach ($reactionOrder as $reactionKey): ?>
                                        <?php if (!isset($reactionEmoticons[$reactionKey])) { continue; } ?>
                                        <?php
                                            $emojiSymbol = (string) $reactionEmoticons[$reactionKey];
                                            $reactionLabel = $reactionKey === 'remove' ? 'Remove' : ucfirst($reactionKey);
                                        ?>
                                        <button class="reaction-btn" data-reaction="<?= htmlspecialchars($reactionKey, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($reactionLabel, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($emojiSymbol, ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="reaction-summary text-sm text-gray-600" data-reaction-summary>
                                <?= $reactionSummaryHtml !== '' ? $reactionSummaryHtml : '<span class="text-gray-400">No reactions yet</span>' ?>
                            </div>
                        </div>
                        <div class="feed-comments mt-3" data-comment-container>
                            <button type="button" class="feed-comment-toggle text-ocean-blue hover:text-burnt-orange text-xs font-semibold uppercase tracking-wide" data-comment-toggle>
                                View comments
                            </button>
                            <div class="feed-comment-list space-y-3<?= empty($comments) ? ' hidden' : '' ?>" data-comment-list>
                                <?php foreach ($comments as $comment): ?>
                                    <?php
                                        $commentUserId = (int) ($comment['user_id'] ?? 0);
                                        $commentId = (int) ($comment['id'] ?? 0);
                                        $commentName = trim(($comment['first_name'] ?? '') . ' ' . ($comment['last_name'] ?? ''));
                                        $commentCreatedAt = $comment['created_at'] ?? '';
                                        $canDeleteComment = ($commentUserId === $currentUserId) || $isCurrentUserAdmin;
                                        $isRecentComment = in_array($commentId, $recentCommentIds, true);
                                        $commentClasses = 'feed-comment flex items-start gap-3';
                                        if ($isRecentComment) {
                                            $commentClasses .= ' feed-comment--recent';
                                        }
                                    ?>
                                    <div class="<?= $commentClasses ?>" data-comment-id="<?= $commentId ?>" data-user-id="<?= $commentUserId ?>"<?= $isRecentComment ? ' data-recent-comment="true"' : '' ?>>
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
    }

    private function formatAncestorLabel(int $generations): string
    {
        if ($generations <= 1) {
            return 'parent of';
        }
        if ($generations === 2) {
            return 'grandparent of';
        }
        $greatCount = $generations - 2;
        return str_repeat('great-', $greatCount) . 'grandparent of';
    }

    private function formatDescendantLabel(int $generations): string
    {
        if ($generations <= 1) {
            return 'child of';
        }
        if ($generations === 2) {
            return 'grandchild of';
        }
        $greatCount = $generations - 2;
        return str_repeat('great-', $greatCount) . 'grandchild of';
    }

    private function renderReactionSummaryHtml(array $summary, array $emoji): string
    {
        if (empty($summary)) {
            return '';
        }
        $parts = [];
        foreach ($summary as $reaction => $count) {
            $reaction = (string) $reaction;
            $count = (int) $count;
            if ($count <= 0) {
                continue;
            }
            $symbol = $emoji[$reaction] ?? '';
            if ($symbol === '') {
                continue;
            }
            $parts[] = '<span class="reaction-chip" data-reaction-type="' . htmlspecialchars($reaction, ENT_QUOTES, 'UTF-8') . '">' .
                $symbol .
                ' <span class="reaction-count">' . $count . '</span></span>';
        }
        return implode(' ', $parts);
    }

    private function getReactionEmojiMap(): array
    {
        $emoji = Web::getReactionEmoticons();
        if (isset($emoji['remove'])) {
            unset($emoji['remove']);
        }
        return $emoji;
    }
}
