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

    public function __construct(Database $db, Auth $auth, Web $web)
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->web = $web;
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
        $effectiveSince = ($since !== null && $since !== '')
            ? $since
            : date('Y-m-d H:i:s', strtotime('-14 days'));
        $changes = Utils::getNewStuff($userId, $effectiveSince);

        $summary = $this->buildSummary($changes);

        $entries = $this->buildFeedEntries($userId, $changes);

        return [
            'summary'         => $summary,
            'entries'         => $entries,
            'last_view'       => $changes['last_view'] ?? null,
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

        $effectiveSince = ($since !== null && $since !== '')
            ? $since
            : date('Y-m-d H:i:s', strtotime('-14 days'));

        $perTypeLimit = max($offset + $limit + self::FETCH_BUFFER, self::MIN_FETCH_SIZE);
        $rawChanges = Utils::getNewStuff(
            $userId,
            $effectiveSince,
            ['limit_per_type' => $perTypeLimit]
        );

        $summary = $this->buildSummary($rawChanges);
        $descriptors = $this->buildEventDescriptors($rawChanges);
        $total = count($descriptors);
        $entries = [];

        if ($total > 0) {
            $pageDescriptors = array_slice($descriptors, $offset, $limit);
            if (!empty($pageDescriptors)) {
                $selectedChanges = $this->selectChangesForDescriptors($rawChanges, $pageDescriptors);
                $entries = $this->buildFeedEntries($userId, $selectedChanges);
            }
        }

        return [
            'summary'         => $summary,
            'entries'         => $entries,
            'total'           => $total,
            'has_more'        => ($offset + $limit) < $total,
            'next_offset'     => min($total, $offset + $limit),
            'last_view'       => $rawChanges['last_view'] ?? $effectiveSince,
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
            'discussion' => ['label' => 'Discussion update', 'icon' => 'fas fa-comments'],
            'comment'    => ['label' => 'New comment',       'icon' => 'fas fa-comment-dots'],
            'individual' => ['label' => 'New family member', 'icon' => 'fas fa-user-plus'],
            'item'       => ['label' => 'Story update',      'icon' => 'fas fa-book-open'],
            'file'       => ['label' => 'New file',          'icon' => 'fas fa-photo-video'],
            'visitor'    => ['label' => 'Latest visit',      'icon' => 'fas fa-door-open'],
        ];
        $reactionEmojiMap = $this->getReactionEmojiMap();

        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
        $isCurrentUserAdmin = ($this->auth->getUserRole() === 'admin');

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

        $normalizeValue = static function ($value) {
            $value = (string) $value;
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
        foreach ($changes['items'] as $itemGroup) {
            if (!empty($itemGroup['item_id'])) {
                $itemIds[] = (int) $itemGroup['item_id'];
            }
        }
        if (!empty($itemIds)) {
            $itemIds = array_values(array_unique($itemIds));
            $itemReactionSummaries = Utils::getItemReactionSummaryByItemIds($itemIds);
            $itemCommentsLookup = Utils::getItemCommentsByItemIds($itemIds);
        }

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
                'content_html' => '',
                'meta'      => [
                    'actor_name'   => trim(($visitor['first_name'] ?? '') . ' ' . ($visitor['last_name'] ?? '')),
                    'actor_id'     => $visitor['user_id'] ?? null,
                    'context'      => 'Latest logins',
                    'subject_name' => '',
                ],
                'url'       => isset($visitor['user_id']) ? "?to=family/users&user_id={$visitor['user_id']}" : '',
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
            $discussionContentRaw = isset($discussion['content']) ? stripslashes($discussion['content']) : '';
            $discussionSnippetHtml = '';
            if ($discussionContentRaw !== '') {
                $discussionSnippetHtml = nvFeedSanitizeHtml(
                    $this->web->truncateText(
                        nl2br($discussionContentRaw),
                        80,
                        'Read more',
                        'feed_discussion_' . $discussion['discussionId'],
                        'expand'
                    )
                );
            }
            $discussionDescendancy = [];
            $discussionIndirectConnection = [];
            if ($isTreeDiscussion && !empty($discussion['individual_id'])) {
                $discussionDescendancy = $getDescendancyTrail($discussion['individual_id']);
                if (empty($discussionDescendancy)) {
                    $discussionIndirectConnection = $getIndirectConnection($discussion['individual_id']);
                }
            }

            $discussionId = (int) ($discussion['discussionId'] ?? 0);
            $reactionSummary = $discussionReactionSummaries[$discussionId] ?? [];
            $comments = $discussionCommentsLookup[$discussionId] ?? [];
            $discussionSubjectName = $resolveName(
                $discussion,
                ['tree_first_name', 'tree_first_names', 'first_names', 'first_name'],
                ['tree_last_name', 'last_name']
            );

            $feedEntries[] = [
                'type'      => $discussion['change_type'] === 'comment' ? 'comment' : 'discussion',
                'title'     => stripslashes($discussion['title']),
                'content'   => $createSnippet($discussionContentRaw, 28),
                'content_html' => $discussionSnippetHtml,
                'meta'      => [
                    'actor_name' => trim(($discussion['user_first_name'] ?? '') . ' ' . ($discussion['user_last_name'] ?? '')),
                    'actor_id'   => $discussion['user_id'] ?? null,
                    'context'    => $isTreeDiscussion ? 'Family Tree Chat' : 'Community Chat',
                    'subject_name' => $isTreeDiscussion ? $discussionSubjectName : '',
                ],
                'url'       => $isTreeDiscussion
                    ? "?to=family/individual&individual_id={$discussion['individual_id']}&discussion_id={$discussion['discussionId']}"
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

            $groupTitle = trim((string) ($itemGroup['item_group_name'] ?? ''));
            $groupTitleTrimmed = $groupTitle !== '' ? $groupTitle : 'New update';
            $personName = $resolveName(
                $firstItem,
                ['tree_first_name', 'tree_first_names', 'first_names', 'first_name'],
                ['tree_last_name', 'last_name']
            );
            $snippet = '';
            $mediaKeys = [];
            $fileKeys = [];

            $addDetailRow = static function ($label, $value, $link = '', $isHtml = false) use (&$detailRows) {
                $detailRows[] = [
                    'label'   => $label,
                    'value'   => $value,
                    'link'    => $link,
                    'is_html' => $isHtml,
                ];
            };

            foreach ($itemGroup['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
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
                    $addDetailRow($detailLabel, $linkIconHtml, $detailValue, true);
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
                        $addDetailRow('GPS', $mapIconHtml, $mapsUrl, true);
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
                    $addDetailRow($detailLabel, $individualName, $linkTarget, false);
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

                $addDetailRow($detailLabel, $allowsBreaks ? $normalizeValue($displayValue) : (string) $displayValue, '', $allowsBreaks);
            }

            if ($snippet === '' && !empty($firstItem['file_path'])) {
                $snippet = 'New file attached.';
            }
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
                    $normalizedTitle = strtolower($groupTitleTrimmed);
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
            $itemId = (int) ($itemGroup['item_id'] ?? 0);
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

            $feedEntries[] = [
                'type'      => 'item',
                'title'     => $groupTitleTrimmed !== '' ? $groupTitleTrimmed . ' update for ' . $personName : 'New update for ' . $personName,
                'content'   => $snippet,
                'content_html' => $contentHtml,
                'meta'      => [
                    'actor_name'   => trim(($firstItem['first_name'] ?? '') . ' ' . ($firstItem['last_name'] ?? '')),
                    'actor_id'     => $firstItem['user_id'] ?? null,
                    'subject_name' => $personName,
                    'privacy'      => $itemGroup['privacy'] ?? 'private',
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

    private function buildSummary(array $changes): array
    {
        return [
            'Discussions'   => !empty($changes['discussions']) ? count($changes['discussions']) . ' new discussions' : '',
            'Individuals'   => !empty($changes['individuals']) ? count($changes['individuals']) . ' new individuals' : '',
            'Relationships' => !empty($changes['relationships']) ? count($changes['relationships']) . ' new relationships' : '',
            'Items'         => !empty($changes['items']) ? count($changes['items']) . ' new items' : '',
            'Files'         => !empty($changes['files']) ? count($changes['files']) . ' new files' : '',
        ];
    }

    private function buildEventDescriptors(array $changes): array
    {
        $events = [];

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

        $relationshipLine = '';
        if (!empty($entry['relationship_to_user'])) {
            $relationshipLine = trim((string) $entry['relationship_to_user']);
        } elseif (!empty($entry['descendancy'])) {
            $lastDescendant = end($entry['descendancy']);
            if (is_array($lastDescendant) && !empty($lastDescendant[1])) {
                $relationshipLine = $getRelationshipToUser((int) $lastDescendant[1]);
            }
        }
        if (!empty($entry['indirect_connection']) && is_array($entry['indirect_connection'])) {
            $indirectConnection = $entry['indirect_connection'];
            $indirectText = trim((string) ($indirectConnection['summary'] ?? ''));
            if ($indirectText !== '') {
                $relationshipLine = $indirectText;
            }
        }

        $web = $this->web;

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
                    <?php
                        $subjectName = trim((string) ($entry['meta']['subject_name'] ?? ''));
                        if ($type === 'individual' && $subjectName !== '') {
                            ?>
                            <span class="hidden sm:inline">&bull; <?= htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php
                        } elseif ($type !== 'individual' && $subjectName !== '') {
                            ?>
                            <span class="hidden sm:inline">&bull; <?= htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php
                        }
                    ?>
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
                <?php if ($relationshipLine !== ''): ?>
                    <p class="feed-item-relationship text-xs text-gray-500 mb-2">
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
                <?php if (!empty($entry['content_html'])): ?>
                    <div class="feed-item-summary text-sm text-gray-600 mb-3"><?= $entry['content_html'] ?></div>
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
                            <li class="feed-item-detail-row">
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
                        $comments = is_array($interaction['comments'] ?? null) ? $interaction['comments'] : [];
                ?>
                    <div class="feed-item-interactions mt-4" data-feed-interaction="<?= htmlspecialchars($interactionType, ENT_QUOTES, 'UTF-8') ?>" <?= $targetAttributeName ?>="<?= $targetId ?>">
                        <div class="<?= $reactionContainerClass ?> feed-reactions flex items-center gap-3" <?= $targetAttributeName ?>="<?= $targetId ?>"<?= $itemIdentifierAttribute ?>>
                            <button type="button" class="like-image inline-flex items-center justify-center pl-2 h-8 w-8 rounded-full bg-gray-100 text-lg" title="React">
                                <svg alt="Like" class="like-image flex-item" viewBox="0 0 32 32" xml:space="preserve" width="18px" height="18px" fill="#000000">
                                    <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                    <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                    <g id="SVGRepo_iconCarrier">
                                        <path fill="#3b82f6" d="M16 3.2l2.2 6.8h7.2l-5.8 4.2 2.2 6.8-5.8-4.2-5.8 4.2 2.2-6.8-5.8-4.2h7.2z"></path>
                                    </g>
                                </svg>
                            </button>
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


