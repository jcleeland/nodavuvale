<?php
/**
 * Timeline tab content for individual profile.
 *
 * Renders a vertical, scrollable timeline with life events, family milestones,
 * and optional facts/events drawn from the individual's record.
 *
 * Expected global context (provided by individual.php):
 * - $individual (array)
 * - $items (array)
 * - $parents (array)
 * - $children (array)
 */
$nvTimelineDebug = isset($_GET['timeline_debug']) && $_GET['timeline_debug'] !== '' && $_GET['timeline_debug'] !== '0';
if (!isset($individual) || !is_array($individual)) {
    echo '<div class="p-4 text-sm text-red-600">Timeline data is currently unavailable.</div>';
    return;
}
$person = $individual;
if (!function_exists('nvTimelineFormatName')) {
    /**
     * Create a friendly display name for an individual array.
     */
    function nvTimelineFormatName(array $person): string
    {
        $first = isset($person['first_names']) ? str_replace('_', ' ', trim((string) $person['first_names'])) : '';
        $last = isset($person['last_name']) ? str_replace('_', ' ', trim((string) $person['last_name'])) : '';
        $name = trim($first . ' ' . $last);
        return $name !== '' ? $name : 'Unknown';
    }
    /**
     * Build a date representation (with inferred precision) from Y/M/D integers.
     *
     * @return array{date: DateTimeImmutable, label: string, precision: string}|null
     */
    function nvTimelineCreateDateFromParts(?int $year, ?int $month, ?int $day): ?array
    {
        global $nvTimelineDebug;
        if (empty($year)) {
            if ($nvTimelineDebug) {
                error_log('[timeline-debug] date_from_parts skipped: missing year; parts=' . json_encode([$year, $month, $day], JSON_PARTIAL_OUTPUT_ON_ERROR));
            }
            return null;
        }
        $hasMonth = !empty($month) && $month >= 1 && $month <= 12;
        $hasDay = $hasMonth && !empty($day) && $day >= 1;
        $month = $hasMonth ? (int) $month : 7;
        $maxDay = cal_days_in_month(CAL_GREGORIAN, $month, (int) $year);
        $day = $hasDay ? min((int) $day, $maxDay) : (int) ceil($maxDay / 2);
        $dateString = sprintf('%04d-%02d-%02d', (int) $year, $month, $day);
        try {
            $date = new DateTimeImmutable($dateString);
        } catch (Exception $exception) {
            if ($nvTimelineDebug) {
                error_log('[timeline-debug] date_from_parts exception: ' . $exception->getMessage() . '; parts=' . json_encode([$year, $month, $day], JSON_PARTIAL_OUTPUT_ON_ERROR));
            }
            return null;
        }
        if ($hasDay) {
            $result = [
                'date' => $date,
                'label' => $date->format('j M Y'),
                'precision' => 'day',
            ];
            if ($nvTimelineDebug) {
                error_log('[timeline-debug] date_from_parts resolved (day): ' . json_encode([$year, $month, $day, $result['label']], JSON_PARTIAL_OUTPUT_ON_ERROR));
            }
            return $result;
        }
        if ($hasMonth) {
            $result = [
                'date' => $date,
                'label' => $date->format('M Y'),
                'precision' => 'month',
            ];
            if ($nvTimelineDebug) {
                error_log('[timeline-debug] date_from_parts resolved (month): ' . json_encode([$year, $month, $day, $result['label']], JSON_PARTIAL_OUTPUT_ON_ERROR));
            }
            return $result;
        }
        $result = [
            'date' => $date,
            'label' => $date->format('Y'),
            'precision' => 'year',
        ];
        if ($nvTimelineDebug) {
            error_log('[timeline-debug] date_from_parts resolved (year): ' . json_encode([$year, $month, $day, $result['label']], JSON_PARTIAL_OUTPUT_ON_ERROR));
        }
        return $result;
    }
    /**
     * Allow a controlled subset of HTML for rich text fields.
     */
    function nvTimelineSanitizeRichText(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><a><blockquote><span>';
        $clean = strip_tags($html, $allowed);
        if ($clean === null || $clean === '') {
            return '';
        }
        $clean = preg_replace_callback('/<a\b[^>]*>/i', static function ($matches) {
            $tag = $matches[0];
            $hasTarget = stripos($tag, 'target=') !== false;
            $hasRel = stripos($tag, 'rel=') !== false;
            $replacement = rtrim($tag, '>');
            if (!$hasTarget) {
                $replacement .= ' target="_blank"';
            }
            if (!$hasRel) {
                $replacement .= ' rel="noopener"';
            }
            $replacement .= '>';
            return $replacement;
        }, $clean);
        return $clean;
    }
    /**
     * Build a date representation from a free-form string (YYYY, YYYY-MM, YYYY-MM-DD).
     *
     * @param string|null $raw
     * @return array{date: DateTimeImmutable, label: string, precision: string}|null
     */
    function nvTimelineCreateDateFromString(?string $raw): ?array
    {
        global $nvTimelineDebug;
        if ($raw === null) {
            return null;
        }
        $value = trim((string) $raw);
        if ($value === '') {
            if ($nvTimelineDebug) {
                error_log('[timeline-debug] date_from_string skipped: empty value');
            }
            return null;
        }
        if (preg_match('/^\d{4}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-07-01');
            if ($date) {
                $result = [
                    'date' => $date,
                    'label' => $date->format('Y'),
                    'precision' => 'year',
                ];
                if ($nvTimelineDebug) {
                    error_log('[timeline-debug] date_from_string resolved (year): ' . $value . ' => ' . $result['label']);
                }
                return $result;
            }
        }
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-15');
            if ($date) {
                $result = [
                    'date' => $date,
                    'label' => $date->format('M Y'),
                    'precision' => 'month',
                ];
                if ($nvTimelineDebug) {
                    error_log('[timeline-debug] date_from_string resolved (month): ' . $value . ' => ' . $result['label']);
                }
                return $result;
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($date) {
                $result = [
                    'date' => $date,
                    'label' => $date->format('j M Y'),
                    'precision' => 'day',
                ];
                if ($nvTimelineDebug) {
                    error_log('[timeline-debug] date_from_string resolved (day): ' . $value . ' => ' . $result['label']);
                }
                return $result;
            }
        }
        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception $exception) {
            if ($nvTimelineDebug) {
                error_log('[timeline-debug] date_from_string exception: ' . $exception->getMessage() . ' for value ' . $value);
            }
            return null;
        }
        $result = [
            'date' => $date,
            'label' => $date->format('j M Y'),
            'precision' => 'day',
        ];
        if ($nvTimelineDebug) {
            error_log('[timeline-debug] date_from_string fallback resolved: ' . $value . ' => ' . $result['label']);
        }
        return $result;
    }
    function nvTimelineBuildPersonLink(?array $person, string $fallback = 'Unknown'): string
    {
        $id = 0;
        $personData = is_array($person) ? $person : [];
        if (isset($personData['id'])) {
            $id = (int) $personData['id'];
        } elseif (isset($personData['individual_name_id'])) {
            $id = (int) $personData['individual_name_id'];
        }
        $label = nvTimelineFormatName($personData);
        if ($label === '' || $label === '-') {
            $label = $fallback;
        }
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        if ($id > 0) {
            return '<a class="nv-timeline-link" href="?to=family/individual&amp;individual_id=' . $id . '">' . $safeLabel . '</a>';
        }
        return $safeLabel;
    }
    function nvTimelineLinkLabel(string $label, int $id = 0): string
    {
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        if ($id > 0) {
            return '<a class="nv-timeline-link" href="?to=family/individual&amp;individual_id=' . $id . '">' . $safeLabel . '</a>';
        }
        return $safeLabel;
    }
    function nvTimelineRenderDetailList(array $group): string
    {
        $items = $group['items'] ?? [];
        if (empty($items)) {
            return '';
        }
        $html = '<div class="nv-timeline-info-card">';
        $title = htmlspecialchars($group['item_group_name'] ?? 'Event', ENT_QUOTES, 'UTF-8');
        $html .= '<h4 class="nv-timeline-info-title">' . $title . '</h4><dl class="nv-timeline-info-list">';
        foreach ($items as $item) {
            $label = htmlspecialchars($item['detail_type'] ?? 'Detail', ENT_QUOTES, 'UTF-8');
            $valueHtml = '';
            if (!empty($item['individual_name'])) {
                $valueText = trim((string) $item['individual_name']);
                $personId = isset($item['individual_name_id']) ? (int) $item['individual_name_id'] : (isset($item['individual_id']) ? (int) $item['individual_id'] : 0);
                $valueHtml = nvTimelineLinkLabel($valueText, $personId);
            } elseif (!empty($item['detail_value'])) {
                $valueText = (string) $item['detail_value'];
                $type = $item['detail_type'] ?? '';
                $richTextTypes = ['Story', 'Description', 'Notes', 'Narrative', 'Summary'];
                if (in_array($type, $richTextTypes, true)) {
                    $valueHtml = nvTimelineSanitizeRichText($valueText);
                } elseif (!empty($item['detail_is_url']) || filter_var($valueText, FILTER_VALIDATE_URL)) {
                    $safeUrl = htmlspecialchars($valueText, ENT_QUOTES, 'UTF-8');
                    $valueHtml = '<a class="nv-timeline-link" href="' . $safeUrl . '" target="_blank" rel="noopener">' . $safeUrl . '</a>';
                } else {
                    $valueHtml = htmlspecialchars($valueText, ENT_QUOTES, 'UTF-8');
                }
            } elseif (!empty($item['file_path'])) {
                $filePath = (string) $item['file_path'];
                $fileLabel = htmlspecialchars(basename($filePath), ENT_QUOTES, 'UTF-8');
                $safePath = htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8');
                $valueHtml = '<a class="nv-timeline-link" href="' . $safePath . '" target="_blank" rel="noopener">' . $fileLabel . '</a>';
            } else {
                $valueHtml = '<span class="nv-timeline-muted">Not specified</span>';
            }
            $html .= '<dt>' . $label . '</dt><dd>' . $valueHtml . '</dd>';
        }
        $html .= '</dl></div>';
        return $html;
    }
    /**
     * Compare two timeline dates.
     *
     * @return int -1 when $a is earlier, 1 when $a is later, 0 when identical.
     */
    function nvTimelineCompareDates(?DateTimeImmutable $a, ?DateTimeImmutable $b): int
    {
        if ($a === null && $b === null) {
            return 0;
        }
        if ($a === null) {
            return 1;
        }
        if ($b === null) {
            return -1;
        }
        $primary = strcmp($a->format('Y-m-d H:i:s'), $b->format('Y-m-d H:i:s'));
        if ($primary !== 0) {
            return $primary;
        }
        return strcmp($a->format('u'), $b->format('u'));
    }
    /**
     * Extract commonly used details from an item group.
     *
     * @param array $group
     * @return array<string,array<int,array<string,mixed>>>
     */
    function nvTimelineIndexGroupItems(array $group): array
    {
        $indexed = [];
        foreach ($group['items'] ?? [] as $item) {
            $type = $item['detail_type'] ?? '';
            if ($type === '') {
                continue;
            }
            $indexed[$type][] = $item;
        }
        return $indexed;
    }
    /**
     * Break a location string into hierarchical parts for comparison.
     *
     * @return array{raw_parts: array<int, string>, normalized_parts: array<int, string>}
     */
    function nvTimelineNormalizeLocation(string $location): array
    {
        $fragments = array_map('trim', explode(',', $location));
        $fragments = array_values(array_filter($fragments, static function (string $fragment): bool {
            return $fragment !== '';
        }));
        $normalized = array_map(static function (string $fragment): string {
            $fragment = preg_replace('/\s+/u', ' ', $fragment);
            $fragment = trim((string) $fragment, " \t\n\r\0\x0B,.");
            return mb_strtolower($fragment ?? '');
        }, $fragments);
        return [
            'raw_parts' => $fragments,
            'normalized_parts' => $normalized,
        ];
    }
    /**
     * Extract the first image src from HTML.
     */
    function nvTimelineExtractFirstImageSrc(string $html): ?string
    {
        if (trim($html) === '') {
            return null;
        }
        if (!preg_match('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $html, $matches)) {
            return null;
        }
        $src = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) $src) !== '' ? $src : null;
    }
    /**
     * Resolve a cached avatar path for an individual.
     */
    function nvTimelineResolveIndividualAvatar(int $individualId): string
    {
        static $avatarCache = [];
        if ($individualId <= 0) {
            return 'images/default_avatar.webp';
        }
        if (array_key_exists($individualId, $avatarCache)) {
            return $avatarCache[$individualId];
        }
        $path = Utils::getKeyImage($individualId);
        if (!$path) {
            $path = 'images/default_avatar.webp';
        }
        $avatarCache[$individualId] = $path;
        return $path;
    }
    /**
     * Determine whether a discussion location (needle) matches an individual's location (haystack).
     *
     * @param array<int,string> $haystack Normalized location parts for the individual (specific -> general).
     * @param array<int,string> $needle   Normalized location parts for the discussion.
     */
    function nvTimelineLocationMatches(array $haystack, array $needle): bool
    {
        $haystack = array_values(array_filter($haystack, static fn($part) => $part !== ''));
        $needle = array_values(array_filter($needle, static fn($part) => $part !== ''));
        $hayLen = count($haystack);
        $needleLen = count($needle);
        if ($hayLen === 0 || $needleLen === 0 || $needleLen > $hayLen) {
            return false;
        }
        for ($offset = 1; $offset <= $needleLen; $offset++) {
            if ($needle[$needleLen - $offset] !== $haystack[$hayLen - $offset]) {
                return false;
            }
        }
        return true;
    }
    /**
     * Build a chronological history of locations based on timeline events.
     *
     * @param array<int,array<string,mixed>> $events
     * @return array<int,array<string,mixed>>
     */
    function nvTimelineBuildLocationHistory(array $events, ?DateTimeImmutable $deathDate): array
    {
        $anchors = [];
        foreach ($events as $event) {
            if (
                empty($event['location_text'])
                || !($event['date'] ?? null) instanceof DateTimeImmutable
            ) {
                continue;
            }
            $normalized = nvTimelineNormalizeLocation((string) $event['location_text']);
            if (empty($normalized['raw_parts'])) {
                continue;
            }
            $anchors[] = [
                'date' => $event['date'],
                'location_text' => $event['location_text'],
                'parts' => $normalized['raw_parts'],
                'normalized' => $normalized['normalized_parts'],
            ];
        }
        if (empty($anchors)) {
            return [];
        }
        usort($anchors, static function (array $a, array $b): int {
            return nvTimelineCompareDates(
                $a['date'] instanceof DateTimeImmutable ? $a['date'] : null,
                $b['date'] instanceof DateTimeImmutable ? $b['date'] : null
            );
        });
        $history = [];
        $count = count($anchors);
        for ($index = 0; $index < $count; $index++) {
            $startDate = $anchors[$index]['date'];
            if (!$startDate instanceof DateTimeImmutable) {
                continue;
            }
            $endDate = null;
            if ($index < $count - 1) {
                $nextDate = $anchors[$index + 1]['date'] ?? null;
                if ($nextDate instanceof DateTimeImmutable) {
                    $endDate = $nextDate;
                }
            } elseif ($deathDate instanceof DateTimeImmutable) {
                $endDate = $deathDate;
            }
            if ($endDate instanceof DateTimeImmutable && nvTimelineCompareDates($endDate, $startDate) <= 0) {
                $endDate = null;
            }
            $history[] = [
                'start' => $startDate,
                'end' => $endDate,
                'location_text' => $anchors[$index]['location_text'],
                'parts' => $anchors[$index]['parts'],
                'normalized' => $anchors[$index]['normalized'],
            ];
        }
        return $history;
    }
    /**
     * Extract a given name for display purposes.
     */
    function nvTimelineExtractGivenName(array $individual, string $fallbackName): string
    {
        $raw = '';
        if (!empty($individual['first_names'])) {
            $raw = (string) $individual['first_names'];
        } elseif ($fallbackName !== '') {
            $raw = $fallbackName;
        }
        $raw = str_replace(['&nbsp;', '_'], ' ', trim((string) $raw));
        $raw = preg_replace('/\s+/u', ' ', strip_tags($raw));
        if ($raw === null) {
            $raw = '';
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return 'Family';
        }
        $parts = preg_split('/\s+/u', $raw);
        return $parts[0] ?? $raw;
    }
    /**
     * Fetch discussion events whose locations intersect the supplied history.
     *
     * @param array<int,array<string,mixed>> $locationHistory
     * @return array<int,array<string,mixed>>
     */
    function nvTimelineCollectLocationTokens(array $locationHistory): array
    {
        $tokens = [];
        foreach ($locationHistory as $segment) {
            foreach ($segment['normalized'] ?? [] as $part) {
                $part = trim((string) $part);
                if ($part === '' || is_numeric($part)) {
                    continue;
                }
                $tokens[$part] = true;
            }
        }
        return array_slice(array_keys($tokens), 0, 12);
    }
    /**
     * Fetch discussion events whose locations intersect the supplied history.
     *
     * @param array<int,array<string,mixed>> $locationHistory
     * @return array<int,array<string,mixed>>
     */
    function nvTimelineFetchDiscussionLocationEvents(array $locationHistory): array
    {
        $tokens = nvTimelineCollectLocationTokens($locationHistory);
        $hasTokens = !empty($tokens);
        try {
            $db = Database::getInstance();
        } catch (Exception $exception) {
            error_log('[timeline] Unable to fetch discussion events: ' . $exception->getMessage());
            return [];
        }
        $params = [];
        if ($hasTokens) {
            $likeParts = [];
            foreach ($tokens as $token) {
                $likeParts[] = "LOWER(discussions.event_location) LIKE ?";
                $params[] = '%' . $token . '%';
            }
            $locationCondition = 'AND ((discussions.event_location IS NULL OR discussions.event_location = \'\') OR (discussions.event_location IS NOT NULL AND discussions.event_location != \'\' AND (' . implode(' OR ', $likeParts) . ')))';
        } else {
            $locationCondition = 'AND (discussions.event_location IS NULL OR discussions.event_location = \'\')';
        }
        $sql = "
            SELECT discussions.*, users.first_name, users.last_name
            FROM discussions
            JOIN users ON discussions.user_id = users.id
            WHERE discussions.is_historical_event = 1
                AND discussions.event_date IS NOT NULL
                $locationCondition
            ORDER BY discussions.event_date IS NULL, discussions.event_date ASC, discussions.updated_at ASC
            LIMIT 250
        ";
        return $db->fetchAll($sql, $params) ?? [];
    }
    /**
     * Create timeline events for matching discussion location entries.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,array<string,mixed>> $locationHistory
     * @return array<int,array<string,mixed>>
     */
    function nvTimelineBuildLocationDiscussionEvents(
        array $rows,
        array $locationHistory,
        string $personName,
        string $personGivenName,
        string $personAvatar,
        ?DateTimeImmutable $lifespanStart,
        ?DateTimeImmutable $lifespanEnd,
        ?array &$debugLog = null
    ): array
    {
    $hasHistory = !empty($locationHistory);
    if (empty($rows)) {
        if (is_array($debugLog)) {
            $debugLog[] = [
                'reason' => 'no_rows',
                'row_count' => 0,
                'history_count' => $hasHistory ? count($locationHistory) : 0,
            ];
        }
        return [];
    }
    if (!$hasHistory && is_array($debugLog)) {
        $debugLog[] = [
            'reason' => 'no_history_available',
            'row_count' => count($rows),
            'history_count' => 0,
        ];
    }
        $events = [];
        foreach ($rows as $row) {
            $rawLocation = trim((string) ($row['event_location'] ?? ''));
            $isGlobalEvent = ($rawLocation === '');
            $locationText = $rawLocation;
            $normalized = [
                'raw_parts' => [],
                'normalized_parts' => [],
            ];
            if ($isGlobalEvent) {
                if (is_array($debugLog)) {
                    $debugLog[] = [
                        'reason' => 'global_event_candidate',
                        'discussion_id' => $row['id'] ?? null,
                    ];
                }
            } else {
                $normalized = nvTimelineNormalizeLocation($locationText);
                if (empty($normalized['normalized_parts'])) {
                    if (is_array($debugLog)) {
                        $debugLog[] = [
                            'reason' => 'location_normalize_empty',
                            'discussion_id' => $row['id'] ?? null,
                            'raw_location' => $locationText,
                        ];
                    }
                    continue;
                }
            }
            $date = null;
            $rawDate = $row['event_date'] ?? null;
            if (!empty($rawDate)) {
                try {
                    $date = new DateTimeImmutable((string) $rawDate);
                } catch (Exception $exception) {
                    $date = null;
                    if (is_array($debugLog)) {
                        $debugLog[] = [
                            'reason' => 'invalid_date',
                            'discussion_id' => $row['id'] ?? null,
                            'raw_date' => $rawDate,
                            'error' => $exception->getMessage(),
                        ];
                    }
                }
            }
            $endDate = null;
            $rawFinish = $row['event_date_finish'] ?? null;
            if (!empty($rawFinish)) {
                try {
                    $endDate = new DateTimeImmutable((string) $rawFinish);
                } catch (Exception $exception) {
                    if (is_array($debugLog)) {
                        $debugLog[] = [
                            'reason' => 'invalid_finish_date',
                            'discussion_id' => $row['id'] ?? null,
                            'raw_finish_date' => $rawFinish,
                            'error' => $exception->getMessage(),
                        ];
                    }
                }
            }
            $match = null;
            if ($date instanceof DateTimeImmutable) {
                if ($lifespanStart instanceof DateTimeImmutable && nvTimelineCompareDates($date, $lifespanStart) < 0) {
                    if (is_array($debugLog)) {
                        $debugLog[] = [
                            'reason' => 'before_birth',
                            'discussion_id' => $row['id'] ?? null,
                            'event_date' => $date->format('Y-m-d H:i:s'),
                            'lifespan_start' => $lifespanStart->format('Y-m-d H:i:s'),
                        ];
                    }
                    continue;
                }
                if ($lifespanEnd instanceof DateTimeImmutable && nvTimelineCompareDates($date, $lifespanEnd) > 0) {
                    if (is_array($debugLog)) {
                        $debugLog[] = [
                            'reason' => 'after_death',
                            'discussion_id' => $row['id'] ?? null,
                            'event_date' => $date->format('Y-m-d H:i:s'),
                            'lifespan_end' => $lifespanEnd->format('Y-m-d H:i:s'),
                        ];
                    }
                    continue;
                }
                if ($isGlobalEvent) {
                    $match = [
                        'start' => null,
                        'end' => null,
                        'location_text' => 'Worldwide',
                        'normalized' => [],
                    ];
                    if (is_array($debugLog)) {
                        $debugLog[] = [
                            'reason' => 'global_match',
                            'discussion_id' => $row['id'] ?? null,
                            'event_date' => $date->format('Y-m-d H:i:s'),
                            'event_date_finish' => $endDate instanceof DateTimeImmutable ? $endDate->format('Y-m-d H:i:s') : null,
                        ];
                    }
                } else {
                    if (!$hasHistory) {
                        if (is_array($debugLog)) {
                            $debugLog[] = [
                                'reason' => 'no_history_for_location_event',
                                'discussion_id' => $row['id'] ?? null,
                                'raw_location' => $locationText,
                            ];
                        }
                        continue;
                    }
                    foreach ($locationHistory as $segment) {
                        $segmentStart = $segment['start'] ?? null;
                        $segmentEnd = $segment['end'] ?? null;
                        if (!$segmentStart instanceof DateTimeImmutable) {
                            continue;
                        }
                        if (nvTimelineCompareDates($date, $segmentStart) < 0) {
                            continue;
                        }
                        if ($segmentEnd instanceof DateTimeImmutable && nvTimelineCompareDates($date, $segmentEnd) >= 0) {
                            continue;
                        }
                        if (nvTimelineLocationMatches($segment['normalized'] ?? [], $normalized['normalized_parts'])) {
                            $match = $segment;
                            if (is_array($debugLog)) {
                                $debugLog[] = [
                                    'reason' => 'matched',
                                    'discussion_id' => $row['id'] ?? null,
                                    'raw_location' => $locationText,
                                    'normalized_location' => $normalized['normalized_parts'],
                                    'event_date' => $date->format('Y-m-d H:i:s'),
                                    'event_date_finish' => $endDate instanceof DateTimeImmutable ? $endDate->format('Y-m-d H:i:s') : null,
                                    'matched_segment' => [
                                        'start' => $segment['start'] instanceof DateTimeImmutable ? $segment['start']->format('Y-m-d H:i:s') : null,
                                        'end' => $segment['end'] instanceof DateTimeImmutable ? $segment['end']->format('Y-m-d H:i:s') : null,
                                        'location_text' => $segment['location_text'] ?? null,
                                        'normalized' => $segment['normalized'] ?? null,
                                    ],
                                ];
                            }
                            break;
                        }
                    }
                }
            }
            if ($match === null || !$date instanceof DateTimeImmutable) {
                if (is_array($debugLog)) {
                    $debugLog[] = [
                        'reason' => 'no_match',
                        'discussion_id' => $row['id'] ?? null,
                        'raw_location' => $locationText,
                        'normalized_location' => $normalized['normalized_parts'],
                        'event_date' => $date instanceof DateTimeImmutable ? $date->format('Y-m-d H:i:s') : null,
                        'event_date_finish' => $endDate instanceof DateTimeImmutable ? $endDate->format('Y-m-d H:i:s') : null,
                    ];
                }
                continue;
            }
            $discussionId = (int) ($row['id'] ?? 0);
            $title = trim((string) ($row['title'] ?? 'Location event'));
            $title = $title === '' ? 'Location event' : $title;
            $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $discussionUrl = 'index.php?to=communications/discussions&amp;discussion_id=' . $discussionId;
            $content = trim((string) ($row['content'] ?? ''));
            $rawImage = nvTimelineExtractFirstImageSrc((string) ($row['content'] ?? ''));
            $locationSummary = $match['location_text'] ?? $locationText;
            if ($locationSummary === '' || $isGlobalEvent) {
                $locationSummary = 'Worldwide';
            }
            $locationSummaryHtml = htmlspecialchars($locationSummary, ENT_QUOTES, 'UTF-8');
            $descriptionPlacePlain = $isGlobalEvent ? 'the world' : $locationSummary;
            $descriptionPlaceHtml = $isGlobalEvent ? htmlspecialchars($descriptionPlacePlain, ENT_QUOTES, 'UTF-8') : $locationSummaryHtml;
            $descriptionText = 'This historical event affected ' . $descriptionPlacePlain . ' and would have impacted on ' . $personName;
            $descriptionHtml = 'This historical event affected <strong>' . $descriptionPlaceHtml . '</strong> and would have impacted on ' . htmlspecialchars($personName, ENT_QUOTES, 'UTF-8');
            if ($endDate instanceof DateTimeImmutable) {
                $endLabel = $endDate->format('j M Y');
                $descriptionText .= '. It concluded on ' . $endLabel . '.';
                $descriptionHtml .= '. It concluded on <strong>' . htmlspecialchars($endLabel, ENT_QUOTES, 'UTF-8') . '</strong>.';
            } else {
                $descriptionText .= '.';
                $descriptionHtml .= '.';
            }
            $fullContentHtml = nvTimelineSanitizeRichText($content);
            $plainContent = trim(strip_tags($fullContentHtml));
            $discussionContent = null;
            if ($plainContent !== '') {
                $limit = 360;
                $hasMore = mb_strlen($plainContent) > $limit;
                if ($hasMore) {
                    $excerptText = mb_substr($plainContent, 0, $limit);
                    $excerptText = preg_replace('/\s+\S*$/u', '', $excerptText);
                    $excerptHtml = '<p>' . htmlspecialchars($excerptText, ENT_QUOTES, 'UTF-8') . '&hellip;</p>';
                } else {
                    $excerptHtml = $fullContentHtml;
                }
                $discussionContent = [
                    'excerpt_html' => $excerptHtml,
                    'full_html' => $fullContentHtml,
                    'has_more' => $hasMore,
                ];
            }
            $detailsHtml = '<div class="nv-timeline-info-card">';
            $detailsHtml .= '<h4 class="nv-timeline-info-title">' . $safeTitle . '</h4>';
            $detailsHtml .= '<dl class="nv-timeline-info-list">';
            $detailsHtml .= '<dt>Location</dt><dd>' . $locationSummaryHtml . '</dd>';
            $detailsHtml .= '<dt>Date</dt><dd>' . htmlspecialchars($date->format('j M Y'), ENT_QUOTES, 'UTF-8') . '</dd>';
            if ($endDate instanceof DateTimeImmutable) {
                $detailsHtml .= '<dt>Finished</dt><dd>' . htmlspecialchars($endDate->format('j M Y'), ENT_QUOTES, 'UTF-8') . '</dd>';
            }
            $detailsHtml .= '</dl>';
            if ($plainContent !== '') {
                $detailsHtml .= '<div class="mt-3 nv-timeline-info-text">' . $fullContentHtml . '</div>';
            }
            $detailsHtml .= '<p class="mt-3"><a class="nv-timeline-link" href="' . $discussionUrl . '" target="_blank" rel="noopener">View discussion</a></p>';
            $detailsHtml .= '</div>';
            $displayDate = $date->format('j M Y');
            if ($endDate instanceof DateTimeImmutable) {
                $displayDate .= ' - ' . $endDate->format('j M Y');
            }
            $locationSubjectAvatar = '';
            $locationSubjectIcon = null;
            if ($rawImage) {
                $locationSubjectAvatar = $rawImage;
            }
            if ($locationSubjectAvatar === '') {
                $locationSubjectIcon = 'fa-solid fa-landmark';
            }
            $locationSubjectAlt = null;
            if ($locationSubjectAvatar !== '') {
                $locationSubjectAlt = 'Historical event image' . ($locationSummary !== '' ? ' for ' . $locationSummary : '');
            } elseif ($locationSubjectIcon !== null) {
                $locationSubjectAlt = $isGlobalEvent ? 'Worldwide historical event' : 'Historical event icon for ' . $locationSummary;
            }
            $events[] = [
                'id' => 'nv-location-discussion-' . $discussionId,
                'category' => 'location',
                'scope' => 'discussion_location',
                'icon' => 'fa-solid fa-location-dot',
                'title' => $title,
                'title_html' => '<a class="nv-timeline-link" href="' . $discussionUrl . '">' . $safeTitle . '</a>',
                'description' => $descriptionText,
                'description_html' => $descriptionHtml,
                'full_description_html' => $descriptionHtml,
                'date' => $date,
                'display_date' => $displayDate,
                'location_text' => $locationSummary,
                'subject_avatar' => $locationSubjectAvatar !== '' ? $locationSubjectAvatar : null,
                'subject_avatar_icon' => $locationSubjectIcon,
                'subject_avatar_alt' => $locationSubjectAlt,
                'discussion_content' => $discussionContent,
                'details_html' => $detailsHtml,
            ];
        }
        return $events;
    }
    /**
     * Locate the first detail value for the given type list.
     *
     * @param array<string,array<int,array<string,mixed>>> $indexed
     * @param array<int,string> $types
     */
    function nvTimelineExtractDetail(array $indexed, array $types): ?array
    {
        foreach ($types as $type) {
            if (!empty($indexed[$type][0])) {
                return $indexed[$type][0];
            }
        }
        return null;
    }
}
$personName = nvTimelineFormatName($individual);
$personGivenName = nvTimelineExtractGivenName($individual, $personName);
$personAvatar = !empty($individual['keyimagepath']) ? (string) $individual['keyimagepath'] : 'images/default_avatar.webp';
$individualId = (int) ($individual['id'] ?? 0);
$birthInfo = nvTimelineCreateDateFromParts(
    $individual['birth_year'] ?? null,
    $individual['birth_month'] ?? null,
    $individual['birth_date'] ?? null
);
$deathInfo = nvTimelineCreateDateFromParts(
    $individual['death_year'] ?? null,
    $individual['death_month'] ?? null,
    $individual['death_date'] ?? null
);
$assumedBirth = false;
$assumedDeath = false;
if (!$birthInfo && $deathInfo) {
    $deathDateForEstimate = $deathInfo['date'] ?? null;
    if ($deathDateForEstimate instanceof DateTimeImmutable) {
        // Only fetch descendant depth when we need to estimate a birth year.
        $descendantGenerations = 0;
        $descendantData = Utils::getDescendantsByGeneration($individualId, 4);
        if (!empty($descendantData) && is_array($descendantData)) {
            $descendantGenerations = (int) max(array_keys($descendantData));
        }
        $descendantGenerations = min(3, max(0, $descendantGenerations));
        $descendantAgeRanges = [
            0 => [28, 36],
            1 => [33, 42],
            2 => [50, 70],
            3 => [80, 100],
        ];
        $offsetRange = $descendantAgeRanges[$descendantGenerations];
        $randomYears = random_int($offsetRange[0], $offsetRange[1]);
        $fallbackDate = $deathDateForEstimate->sub(new DateInterval('P' . $randomYears . 'Y'));
        $birthInfo = [
            'date' => $fallbackDate,
            'label' => 'Estimated birth year',
            'precision' => 'year',
        ];
        $assumedBirth = true;
    }
}
if (!$deathInfo && $birthInfo) {
    $fallbackDate = $birthInfo['date']->add(new DateInterval('P82Y'));
    $deathInfo = [
        'date' => $fallbackDate,
        'label' => $fallbackDate->format('Y'),
        'precision' => 'year',
    ];
    $assumedDeath = true;
}
if (!$birthInfo && !$deathInfo) {
    $now = new DateTimeImmutable('now');
    $birthInfo = [
        'date' => $now->sub(new DateInterval('P50Y')),
        'label' => 'Estimated birth year',
        'precision' => 'year',
    ];
    $deathInfo = [
        'date' => $now->add(new DateInterval('P50Y')),
        'label' => $now->add(new DateInterval('P50Y'))->format('Y'),
        'precision' => 'year',
    ];
    $assumedBirth = true;
    $assumedDeath = true;
}
$now = isset($now) ? $now : new DateTimeImmutable('now');
if ($deathInfo && $birthInfo) {
    $deathDate = $deathInfo['date'] ?? null;
    $birthDate = $birthInfo['date'] ?? null;
    if ($deathDate instanceof DateTimeImmutable && $birthDate instanceof DateTimeImmutable) {
        if (nvTimelineCompareDates($deathDate, $birthDate) <= 0) {
            $adjusted = $birthDate->add(new DateInterval('P1Y'));
            $deathInfo['date'] = $adjusted;
            $deathInfo['label'] = $adjusted->format('Y');
            $deathInfo['precision'] = 'year';
        }
    }
}
if ($assumedDeath && $deathInfo) {
    $deathDate = $deathInfo['date'] ?? null;
    if ($deathDate instanceof DateTimeImmutable && $now instanceof DateTimeImmutable) {
        if (nvTimelineCompareDates($deathDate, $now) > 0) {
            $deathInfo = null;
            $assumedDeath = false;
        }
    }
}
$timelineEvents = [];
$deathEventIncluded = false;
$birthDetails = null;
$deathDetails = null;
$itemGroupsByName = [];
foreach ($items ?? [] as $group) {
    $groupName = $group['item_group_name'] ?? '';
    if ($groupName === '') {
        continue;
    }
    $itemGroupsByName[$groupName][] = $group;
    if ($groupName === 'Birth') {
        $birthDetails = $group;
    }
    if ($groupName === 'Death') {
        $deathDetails = $group;
    }
}
if ($birthInfo) {
    $birthIndexed = $birthDetails ? nvTimelineIndexGroupItems($birthDetails) : [];
    $birthLocation = nvTimelineExtractDetail($birthIndexed, ['Location']);
    $birthLocationText = $birthLocation['detail_value'] ?? null;
    $birthDescription = $birthLocationText ? 'Born in ' . $birthLocationText : 'Birth recorded for ' . $personName;
    $birthDescriptionHtml = $birthLocationText
        ? 'Born in ' . htmlspecialchars($birthLocationText, ENT_QUOTES, 'UTF-8')
        : 'Birth recorded for ' . nvTimelineBuildPersonLink($person, $personName);
    $timelineEvents[] = [
        'id' => 'nv-birth-' . $individualId,
        'category' => 'personal',
        'scope' => 'self',
        'icon' => 'fa-solid fa-cake-candles',
        'title' => 'Birth of ' . $personName,
        'title_html' => 'Birth of ' . nvTimelineBuildPersonLink($person, $personName),
        'description' => $birthDescription,
        'description_html' => $birthDescriptionHtml,
        'date' => $birthInfo['date'],
        'display_date' => $birthInfo['label'],
        'assumed' => $assumedBirth,
        'location_text' => $birthLocationText,
        'subject_avatar' => $personAvatar,
        'subject_avatar_alt' => $personName,
        'full_description_html' => $birthDescriptionHtml,
    ];
}
if ($deathInfo) {
    $deathIndexed = $deathDetails ? nvTimelineIndexGroupItems($deathDetails) : [];
    $deathLocation = nvTimelineExtractDetail($deathIndexed, ['Location']);
    $deathLocationText = $deathLocation['detail_value'] ?? null;
    $deathTitle = $assumedDeath
        ? 'Estimated passing of ' . $personName
        : 'Passing of ' . $personName;
    $deathTitleHtml = $assumedDeath
        ? 'Estimated passing of ' . nvTimelineBuildPersonLink($person, $personName)
        : 'Passing of ' . nvTimelineBuildPersonLink($person, $personName);
    $deathDescriptionParts = [];
    $deathDescriptionPartsHtml = [];
    if ($deathLocationText) {
        $deathDescriptionParts[] = 'Left us at ' . $deathLocationText;
        $deathDescriptionPartsHtml[] = 'Left us at ' . htmlspecialchars($deathLocationText, ENT_QUOTES, 'UTF-8');
    }
    if ($assumedDeath) {
        $deathDescriptionParts[] = 'Date estimated using an 82-year lifespan';
        $deathDescriptionPartsHtml[] = 'Date estimated using an 82-year lifespan';
    }
    $deathDescription = $deathDescriptionParts
        ? implode(' - ', $deathDescriptionParts)
        : ($assumedDeath
            ? 'Estimated date for the end of ' . $personName . '\'s lifespan'
            : 'Death recorded for ' . $personName);
    $deathDescriptionHtml = $deathDescriptionPartsHtml
        ? implode(' - ', $deathDescriptionPartsHtml)
        : ($assumedDeath
            ? 'Estimated date for the end of ' . nvTimelineBuildPersonLink($person, $personName) . '\'s lifespan'
            : 'Death recorded for ' . nvTimelineBuildPersonLink($person, $personName));
    if ($assumedDeath) {
        $deathDateCheck = $deathInfo['date'] ?? null;
        $nowCheck = $now instanceof DateTimeImmutable ? $now : new DateTimeImmutable('now');
        if ($deathDateCheck instanceof DateTimeImmutable && nvTimelineCompareDates($deathDateCheck, $nowCheck) > 0) {
            $deathInfo = null;
            $assumedDeath = false;
        }
    }
    if ($deathInfo) {
        $timelineEvents[] = [
            'id' => 'nv-death-' . $individualId,
            'category' => 'personal',
            'scope' => 'self',
            'icon' => 'fa-solid fa-dove',
            'title' => $deathTitle,
            'title_html' => $deathTitleHtml,
            'description' => $deathDescription,
            'description_html' => $deathDescriptionHtml,
            'date' => $deathInfo['date'],
            'display_date' => $deathInfo['label'],
            'assumed' => $assumedDeath,
            'location_text' => $deathLocationText,
            'subject_avatar' => $personAvatar,
            'subject_avatar_alt' => $personName,
            'full_description_html' => $deathDescriptionHtml,
        ];
        $deathEventIncluded = true;
    }
}
foreach ($itemGroupsByName['Marriage'] ?? [] as $marriageGroup) {
    $indexed = nvTimelineIndexGroupItems($marriageGroup);
    $spouseDetail = nvTimelineExtractDetail($indexed, ['Spouse']);
    $locationDetail = nvTimelineExtractDetail($indexed, ['Location']);
    $dateDetail = nvTimelineExtractDetail($indexed, ['Date', 'Started']);
    $eventDate = null;
    if ($dateDetail && !empty($dateDetail['detail_value'])) {
        $eventDate = nvTimelineCreateDateFromString((string) $dateDetail['detail_value']);
    } elseif (!empty($marriageGroup['sortDate'])) {
        $eventDate = nvTimelineCreateDateFromString((string) $marriageGroup['sortDate']);
    }
    if (!$eventDate) {
        continue;
    }
    $spouseName = $spouseDetail['individual_name'] ?? $spouseDetail['detail_value'] ?? 'Spouse';
    $spouseId = isset($spouseDetail['individual_name_id']) ? (int) $spouseDetail['individual_name_id'] : (isset($spouseDetail['individual_id']) ? (int) $spouseDetail['individual_id'] : 0);
    $spouseLink = nvTimelineLinkLabel($spouseName, $spouseId);
    $location = $locationDetail['detail_value'] ?? null;
    $locationHtml = $location ? htmlspecialchars($location, ENT_QUOTES, 'UTF-8') : null;
    $marriageAvatar = $personAvatar;
    if ($spouseId > 0) {
        $marriageAvatarCandidate = nvTimelineResolveIndividualAvatar($spouseId);
        if ($marriageAvatarCandidate !== '') {
            $marriageAvatar = $marriageAvatarCandidate;
        }
    }
    $timelineEvents[] = [
        'id' => 'nv-marriage-' . ($marriageGroup['items'][0]['item_identifier'] ?? uniqid('', true)),
        'category' => 'family',
        'scope' => 'marriage',
        'icon' => 'fa-solid fa-ring',
        'title' => 'Married ' . $spouseName,
        'title_html' => 'Married ' . $spouseLink,
        'description' => $location ? 'Celebrated in ' . $location : 'Marriage recorded with ' . $spouseName,
        'description_html' => $locationHtml ? 'Celebrated in ' . $locationHtml : 'Marriage recorded with ' . $spouseLink,
        'date' => $eventDate['date'],
        'display_date' => $eventDate['label'],
        'assumed' => false,
        'location_text' => $location,
        'subject_avatar' => $marriageAvatar,
        'subject_avatar_alt' => $spouseName,
        'full_description_html' => $locationHtml ? 'Celebrated in ' . $locationHtml : 'Marriage recorded with ' . $spouseLink,
    ];
}
// Debug: timeline instrumentation (remove once ordering issue is solved)
if ($nvTimelineDebug && !empty($children)) {
    error_log('[timeline-debug] raw children payload: ' . print_r($children, true));
}
foreach ($children ?? [] as $child) {
    $childDate = nvTimelineCreateDateFromParts(
        $child['birth_year'] ?? null,
        $child['birth_month'] ?? null,
        $child['birth_date'] ?? null
    );
    if (!$childDate) {
        continue;
    }
    $childName = nvTimelineFormatName($child);
    $childLink = nvTimelineBuildPersonLink($child, $childName);
    $otherParent = null;
    $otherParentHtml = null;
    if (!empty($child['other_parents'][0])) {
        $otherParent = nvTimelineFormatName($child['other_parents'][0]);
        $otherParentHtml = nvTimelineBuildPersonLink($child['other_parents'][0], $otherParent);
    }
    $childDescription = $otherParent ? 'Parented with ' . $otherParent : 'Child of ' . $personName;
    $childDescriptionHtml = $otherParentHtml
        ? 'Parented with ' . $otherParentHtml
        : 'Child of ' . nvTimelineBuildPersonLink($person, $personName);
    $childAvatar = '';
    if (!empty($child['keyimagepath'])) {
        $childAvatar = (string) $child['keyimagepath'];
    }
    if ($childAvatar === '' && !empty($child['id'])) {
        $childAvatar = nvTimelineResolveIndividualAvatar((int) $child['id']);
    }
    if ($childAvatar === '') {
        $childAvatar = $personAvatar;
    }
    $timelineEvents[] = [
        'id' => 'nv-child-' . ($child['id'] ?? uniqid('', true)),
        'category' => 'family',
        'scope' => 'child',
        'icon' => 'fa-solid fa-baby',
        'title' => $childName . ' was born',
        'title_html' => $childLink . ' was born',
        'description' => $childDescription,
        'description_html' => $childDescriptionHtml,
        'date' => $childDate['date'],
        'display_date' => $childDate['label'],
        'assumed' => false,
        'subject_avatar' => $childAvatar,
        'subject_avatar_alt' => $childName,
        'full_description_html' => $childDescriptionHtml,
    ];
}
foreach ($parents ?? [] as $parent) {
    $parentDeath = nvTimelineCreateDateFromParts(
        $parent['death_year'] ?? null,
        $parent['death_month'] ?? null,
        $parent['death_date'] ?? null
    );
    if (!$parentDeath) {
        continue;
    }
    $parentName = nvTimelineFormatName($parent);
    $parentAvatar = '';
    if (!empty($parent['keyimagepath'])) {
        $parentAvatar = (string) $parent['keyimagepath'];
    }
    if ($parentAvatar === '' && !empty($parent['id'])) {
        $parentAvatar = nvTimelineResolveIndividualAvatar((int) $parent['id']);
    }
    if ($parentAvatar === '') {
        $parentAvatar = $personAvatar;
    }
    $timelineEvents[] = [
        'id' => 'nv-parent-' . ($parent['id'] ?? uniqid('', true)),
        'category' => 'family',
        'scope' => 'parent',
        'icon' => 'fa-solid fa-people-roof',
        'title' => $parentName . ' passed away',
        'title_html' => nvTimelineBuildPersonLink($parent, $parentName) . ' passed away',
        'description' => 'Parent of ' . $personName,
        'description_html' => 'Parent of ' . nvTimelineBuildPersonLink($person, $personName),
        'date' => $parentDeath['date'],
        'display_date' => $parentDeath['label'],
        'assumed' => false,
        'subject_avatar' => $parentAvatar,
        'subject_avatar_alt' => $parentName,
        'full_description_html' => 'Parent of ' . nvTimelineBuildPersonLink($person, $personName),
    ];
}
$dateDetailTypes = ['Date', 'Started', 'Arrival', 'Departure', 'Ended'];
$ignoredFactGroups = ['Private', 'Birth', 'Death', 'Marriage', 'Key Image'];
foreach ($items ?? [] as $group) {
    $groupName = $group['item_group_name'] ?? '';
    if ($groupName === '' || in_array($groupName, $ignoredFactGroups, true)) {
        continue;
    }
    $isBurialGroup = strcasecmp($groupName, 'Burial') === 0;
    $indexed = nvTimelineIndexGroupItems($group);
    $dateDetail = nvTimelineExtractDetail($indexed, $dateDetailTypes);
    $eventDate = null;
    if ($dateDetail && !empty($dateDetail['detail_value'])) {
        $eventDate = nvTimelineCreateDateFromString((string) $dateDetail['detail_value']);
    } elseif (!empty($group['sortDate'])) {
        $eventDate = nvTimelineCreateDateFromString((string) $group['sortDate']);
    }
    $eventDateWasDerived = false;
    if (!$eventDate && $isBurialGroup && $deathInfo && ($deathInfo['date'] ?? null) instanceof DateTimeImmutable) {
        $baseDeathDate = $deathInfo['date'];
        try {
            $burialDate = $baseDeathDate->add(new DateInterval('P1D'));
        } catch (Exception $exception) {
            $burialDate = $baseDeathDate;
        }
        $eventDate = [
            'date' => $burialDate,
            'label' => $deathInfo['precision'] === 'day'
                ? $burialDate->format('j M Y')
                : ($deathInfo['precision'] === 'month'
                    ? $burialDate->format('M Y')
                    : $burialDate->format('Y')),
            'precision' => $deathInfo['precision'] ?? 'day',
        ];
        $eventDateWasDerived = true;
    }
    if (!$eventDate) {
        continue;
    }
    $locationDetail = nvTimelineExtractDetail($indexed, ['Location', 'Place', 'Residence', 'Address']);
    $locationText = $locationDetail && !empty($locationDetail['detail_value'])
        ? trim((string) $locationDetail['detail_value'])
        : null;
    $descriptionParts = [];
    $descriptionPartsHtml = [];
    $factAvatar = '';
    foreach ($indexed as $type => $details) {
        if (in_array($type, $dateDetailTypes, true)) {
            continue;
        }
        $detail = $details[0];
        if (!empty($detail['individual_name'])) {
            $label = trim((string) $detail['individual_name']);
            $descriptionParts[] = $label;
            $personId = isset($detail['individual_name_id']) ? (int) $detail['individual_name_id'] : (isset($detail['individual_id']) ? (int) $detail['individual_id'] : 0);
            $descriptionPartsHtml[] = nvTimelineLinkLabel($label, $personId);
            if ($factAvatar === '' && $personId > 0) {
                $factAvatar = nvTimelineResolveIndividualAvatar($personId);
            }
        } elseif (!empty($detail['detail_value']) && !filter_var($detail['detail_value'], FILTER_VALIDATE_URL)) {
            $value = (string) $detail['detail_value'];
            $descriptionParts[] = $value;
            $descriptionPartsHtml[] = nvTimelineSanitizeRichText($value);
        }
    }
    if ($eventDateWasDerived) {
        $descriptionParts[] = 'Date approximated after their passing';
        $descriptionPartsHtml[] = 'Date approximated after their passing';
    }
    $descriptionText = $descriptionParts ? implode(' - ', array_slice($descriptionParts, 0, 2)) : 'Recorded event for ' . $personName;
    $descriptionHtml = $descriptionPartsHtml ? implode(' - ', array_slice($descriptionPartsHtml, 0, 2)) : 'Recorded event for ' . nvTimelineBuildPersonLink($person, $personName);
    $mediaGallery = [];
    if (strcasecmp($groupName, 'Website') === 0) {
        $urlDetail = nvTimelineExtractDetail($indexed, ['URL', 'Link']);
        $targetUrl = $urlDetail['detail_value'] ?? null;
        if (!$targetUrl) {
            foreach ($indexed as $detailGroup) {
                foreach ($detailGroup as $detail) {
                    if (!empty($detail['detail_value']) && filter_var($detail['detail_value'], FILTER_VALIDATE_URL)) {
                        $targetUrl = $detail['detail_value'];
                        break 2;
                    }
                }
            }
        }
        if ($targetUrl) {
            $safeUrl = htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8');
            $descriptionHtml .= ' <a class="nv-timeline-link inline-flex items-center gap-1" href="' . $safeUrl . '" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Visit site</a>';
        }
    }
    foreach ($indexed as $detailGroup) {
        foreach ($detailGroup as $detail) {
            if (!empty($detail['file_path']) && !empty($detail['file_type']) && strtolower($detail['file_type']) === 'image') {
                $mediaGallery[] = [
                    'src' => $detail['file_path'],
                    'alt' => trim((string) ($detail['file_description'] ?? $groupName)),
                ];
                if ($factAvatar === '') {
                    $factAvatar = (string) $detail['file_path'];
                }
            }
            if (!empty($detail['detail_type']) && strcasecmp($detail['detail_type'], 'GPS') === 0 && !empty($detail['detail_value'])) {
                $coordinates = trim((string) $detail['detail_value']);
                if (strpos($coordinates, ',') !== false) {
                    [$lat, $lng] = array_map('trim', explode(',', $coordinates, 2));
                    if (is_numeric($lat) && is_numeric($lng)) {
                        $mapUrl = 'https://www.google.com/maps?q=' . urlencode($lat . ',' . $lng);
                        $descriptionHtml .= ' <a class="nv-timeline-link inline-flex items-center gap-1" href="' . htmlspecialchars($mapUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener"><i class="fa-solid fa-map-location-dot"></i> View map</a>';
                    }
                }
            }
        }
    }
    $detailsHtml = nvTimelineRenderDetailList($group);
    if ($factAvatar === '') {
        $factAvatar = $personAvatar;
    }
    $timelineEvents[] = [
        'id' => 'nv-fact-' . ($group['items'][0]['item_identifier'] ?? uniqid('', true)),
        'category' => 'facts',
        'scope' => 'fact',
        'icon' => 'fa-solid fa-scroll',
        'title' => $groupName,
        'title_html' => htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8'),
        'description' => $descriptionText,
        'description_html' => $descriptionHtml,
        'date' => $eventDate['date'],
        'display_date' => $eventDate['label'],
        'assumed' => $eventDateWasDerived,
        'details_html' => $detailsHtml,
        'location_text' => $locationText,
        'subject_avatar' => $factAvatar,
        'subject_avatar_alt' => $groupName,
        'full_description_html' => $descriptionHtml,
        'media_gallery' => $mediaGallery,
    ];
}
$locationEventsEnabled = !isset($_GET['include_location_events']) || $_GET['include_location_events'] !== '0';
$locationEventsVisibleByDefault = $locationEventsEnabled;
$lifespanStart = isset($birthInfo['date']) && $birthInfo['date'] instanceof DateTimeImmutable ? $birthInfo['date'] : null;
$lifespanEnd = isset($deathInfo['date']) && $deathInfo['date'] instanceof DateTimeImmutable ? $deathInfo['date'] : null;
$locationHistory = nvTimelineBuildLocationHistory(
    $timelineEvents,
    isset($deathInfo['date']) && $deathInfo['date'] instanceof DateTimeImmutable ? $deathInfo['date'] : null
);
$locationHistorySummary = array_map(static function (array $segment): array {
    return [
        'start' => ($segment['start'] ?? null) instanceof DateTimeImmutable ? $segment['start']->format('Y-m-d H:i:s') : null,
        'end' => ($segment['end'] ?? null) instanceof DateTimeImmutable ? $segment['end']->format('Y-m-d H:i:s') : null,
        'location_text' => $segment['location_text'] ?? null,
        'normalized' => $segment['normalized'] ?? [],
    ];
}, $locationHistory);
$locationTokens = nvTimelineCollectLocationTokens($locationHistory);
$locationDiscussionDebug = [];
if ($locationEventsEnabled && !empty($locationHistory)) {
    $discussionRows = nvTimelineFetchDiscussionLocationEvents($locationHistory);
    $locationDiscussionEvents = nvTimelineBuildLocationDiscussionEvents(
        $discussionRows,
        $locationHistory,
        $personName,
        $personGivenName,
        $personAvatar,
        $lifespanStart,
        $lifespanEnd,
        $locationDiscussionDebug
    );
    if (!empty($locationDiscussionEvents)) {
        $timelineEvents = array_merge($timelineEvents, $locationDiscussionEvents);
    }
}
$locationDebugOutput = [
    'enabled' => $locationEventsEnabled,
    'history_count' => count($locationHistory),
    'history' => $locationHistorySummary,
    'tokens' => $locationTokens,
    'lifespan_start' => $lifespanStart instanceof DateTimeImmutable ? $lifespanStart->format('Y-m-d H:i:s') : null,
    'lifespan_end' => $lifespanEnd instanceof DateTimeImmutable ? $lifespanEnd->format('Y-m-d H:i:s') : null,
    'discussion_debug' => $locationDiscussionDebug,
];
echo "\n<!-- timeline-location-debug " . json_encode($locationDebugOutput, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . " -->\n";
usort($timelineEvents, static function (array $a, array $b): int {
    $aDate = $a['date'] instanceof DateTimeImmutable ? $a['date'] : null;
    $bDate = $b['date'] instanceof DateTimeImmutable ? $b['date'] : null;
    $dateComparison = nvTimelineCompareDates($aDate, $bDate);
    if ($dateComparison !== 0) {
        return $dateComparison;
    }
    return strcmp($a['title'] ?? '', $b['title'] ?? '');
});
// Debug: timeline instrumentation (remove once ordering issue is solved)
if ($nvTimelineDebug) {
    echo '<div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-xs text-slate-700">';
    echo '<strong>Timeline debug order</strong><br>';
    foreach ($timelineEvents as $event) {
        $raw = $event['date'] instanceof DateTimeImmutable
            ? $event['date']->format('Y-m-d')
            : (is_object($event['date']) ? get_class($event['date']) : (is_scalar($event['date']) ? (string) $event['date'] : gettype($event['date'])));
        printf(
            '<div>%s | %s | %s | %s</div>',
            htmlspecialchars((string) ($event['id'] ?? '?'), ENT_QUOTES),
            htmlspecialchars((string) ($event['category'] ?? '?'), ENT_QUOTES),
            htmlspecialchars((string) ($event['display_date'] ?? '?'), ENT_QUOTES),
            htmlspecialchars($raw, ENT_QUOTES)
        );
    }
    echo '</div>';
}
$firstOriginalEvent = $timelineEvents[0] ?? null;
$lastOriginalEvent = !empty($timelineEvents) ? $timelineEvents[count($timelineEvents) - 1] : null;
$knownBirthYear = (!$assumedBirth && $birthInfo && isset($birthInfo['date'])) ? $birthInfo['date']->format('Y') : null;
$knownDeathYear = (!$assumedDeath && $deathInfo && isset($deathInfo['date'])) ? $deathInfo['date']->format('Y') : null;
$rangeStartLabel = '';
$rangeEndLabel = '';

if ($knownBirthYear) {
    $rangeStartLabel = $knownBirthYear;
} elseif ($birthInfo) {
    $rangeStartLabel = 'Birth';
}
if ($rangeStartLabel === '') {
    $rangeStartLabel = 'Birth';
}

if ($knownDeathYear) {
    $rangeEndLabel = $knownDeathYear;
} elseif ($deathInfo) {
    $deathDate = $deathInfo['date'] ?? null;
    if ($deathDate instanceof DateTimeImmutable && isset($birthInfo['date']) && $birthInfo['date'] instanceof DateTimeImmutable) {
        $expectedLifespan = $birthInfo['date']->add(new DateInterval('P105Y'));
        $rangeEndLabel = (nvTimelineCompareDates($expectedLifespan, new DateTimeImmutable('now')) >= 0) ? 'Present' : 'Estimated death';
    } else {
        $rangeEndLabel = 'Present';
    }
} else {
    $rangeEndLabel = 'Present';
}
if ($rangeEndLabel === '') {
    $rangeEndLabel = 'Estimated death';
}

$rangeLabelParts = array_filter([$rangeStartLabel, $rangeEndLabel], static function ($part) {
    return $part !== '' && stripos($part, 'estimated birth year') === false;
});
$rangeLabel = implode(' - ', $rangeLabelParts);
$renderEvents = [];
$factsVisibleByDefault = true;
if (!empty($timelineEvents)) {
    $currentY = 56.0;
    $minSpacing = 160.0;
    $maxSpacing = 340.0;
    $yearFactor = 16.0;
    $previousDate = null;
    $lastNonBreakSide = 'left';
    $lastNonBreakPosition = null;
    foreach ($timelineEvents as $index => $event) {
        $category = $event['category'] ?? '';
        if ($index === 0) {
            $event['position'] = $currentY;
            if ($category === 'facts') {
                $event['initial_visibility'] = $factsVisibleByDefault;
            } elseif ($category === 'location') {
                $event['initial_visibility'] = $locationEventsVisibleByDefault;
            } else {
                $event['initial_visibility'] = true;
            }
            $event['side'] = 'left';
            $renderEvents[] = $event;
            $previousDate = $event['date'];
            $lastNonBreakSide = $event['side'];
            $lastNonBreakPosition = $event['position'];
            continue;
        }
        $yearsDiffRaw = $previousDate
            ? max(0.0, $previousDate->diff($event['date'])->days / 365.25)
            : 0.0;
        $spacing = max($minSpacing, min($maxSpacing, $yearsDiffRaw * $yearFactor));
        $currentY += $spacing;
        $event['position'] = $currentY;
        if ($category === 'facts') {
            $event['initial_visibility'] = $factsVisibleByDefault;
        } elseif ($category === 'location') {
            $event['initial_visibility'] = $locationEventsVisibleByDefault;
        } else {
            $event['initial_visibility'] = true;
        }
        if (($event['scope'] ?? '') === 'break') {
            $event['side'] = 'center';
        } else {
            $shouldAlternate = $lastNonBreakPosition !== null
                && ($event['position'] - $lastNonBreakPosition) < 240.0;
            if ($shouldAlternate) {
                $event['side'] = $lastNonBreakSide === 'left' ? 'right' : 'left';
            } else {
                $event['side'] = 'left';
            }
            $lastNonBreakSide = $event['side'];
            $lastNonBreakPosition = $event['position'];
        }
        $renderEvents[] = $event;
        $previousDate = $event['date'];
    }
}
if (!empty($renderEvents)) {
    $lastRenderEvent = $renderEvents[count($renderEvents) - 1];
    $canvasHeight = (int) max(420, ceil($lastRenderEvent['position'] + 220));
} else {
    $canvasHeight = 480;
}
// Debug: inspect rendered events with calculated positions (remove when resolved)
if ($nvTimelineDebug && !empty($renderEvents)) {
    echo '<div class="mb-4 rounded-lg border border-sky-300 bg-sky-50 p-4 text-xs text-slate-700">';
    echo '<strong>Timeline render positions</strong><br>';
    foreach ($renderEvents as $index => $event) {
        $raw = $event['date'] instanceof DateTimeImmutable
            ? $event['date']->format('Y-m-d')
            : (is_object($event['date']) ? get_class($event['date']) : (is_scalar($event['date']) ? (string) $event['date'] : gettype($event['date'])));
        printf(
            '<div>#%d %s | pos=%0.2f | %s | %s</div>',
            $index,
            htmlspecialchars((string) ($event['id'] ?? '?'), ENT_QUOTES),
            (float) ($event['position'] ?? 0.0),
            htmlspecialchars((string) ($event['display_date'] ?? '?'), ENT_QUOTES),
            htmlspecialchars($raw, ENT_QUOTES)
        );
    }
    echo '</div>';
}
$timelineRootId = 'nv-timeline-root-' . $individualId;
if (!defined('NV_TIMELINE_STYLES_LOADED')) {
    define('NV_TIMELINE_STYLES_LOADED', true);
    ?>
    <style>
        .nv-timeline-scroll {
            backdrop-filter: blur(6px);
        }
        .nv-timeline-wrapper {
            position: relative;
            padding: 4rem 6rem;
        }
        .nv-timeline-axis {
            position: absolute;
            top: 4rem;
            bottom: 4rem;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            border-radius: 9999px;
            background: linear-gradient(180deg, rgba(16, 185, 129, 0.82), rgba(14, 165, 233, 0.82));
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.35);
        }
        .nv-timeline-event {
            position: absolute;
            width: 100%;
            transform: translateY(-50%);
            display: flex;
        }
        .nv-timeline-event[data-side="left"] {
            justify-content: flex-end;
            padding-right: calc(50% + 3.5rem);
        }
        .nv-timeline-event[data-side="right"] {
            justify-content: flex-start;
            padding-left: calc(50% + 3.5rem);
        }
        .nv-timeline-event[data-side="center"] {
            justify-content: center;
            padding: 0 20%;
        }
        @media (max-width: 1024px) {
            .nv-timeline-wrapper {
                padding: 3rem 2.5rem;
            }
            .nv-timeline-axis {
                left: 2.5rem;
                transform: none;
            }
            .nv-timeline-event {
                width: 100%;
                left: 0 !important;
                padding: 0 2.5rem !important;
                justify-content: flex-start;
            }
            .nv-timeline-event .nv-timeline-card {
                text-align: left !important;
                border-right: 0 !important;
                border-left: 4px solid var(--nv-timeline-accent) !important;
                width: 100%;
            }
            .nv-timeline-pin {
                left: 2.5rem;
                transform: translate(-50%, -50%);
            }
        }
        @media (max-width: 640px) {
            .nv-timeline-wrapper {
                padding: 2.5rem 1.5rem;
            }
            .nv-timeline-axis {
                left: 1.9rem;
            }
            .nv-timeline-event {
                padding: 0 1rem !important;
                justify-content: flex-start;
            }
            .nv-timeline-pin {
                left: 1.9rem;
            }
        }
        .nv-timeline-pin {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 2.6rem;
            height: 2.6rem;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 12px 22px rgba(15, 23, 42, 0.14);
            border: 3px solid rgba(148, 163, 184, 0.35);
            z-index: 1200;
        }
        .nv-timeline-event[data-category="personal"] .nv-timeline-pin {
            background: linear-gradient(145deg, #10b981, #047857);
            color: #f0fdf4;
            border-color: rgba(16, 185, 129, 0.65);
        }
        .nv-timeline-event[data-category="family"] .nv-timeline-pin {
            background: linear-gradient(145deg, #0ea5e9, #2563eb);
            color: #f8fafc;
            border-color: rgba(14, 165, 233, 0.65);
        }
        .nv-timeline-event[data-category="facts"] .nv-timeline-pin {
            background: linear-gradient(145deg, #f59e0b, #f97316);
            color: #fff7ed;
            border-color: rgba(249, 115, 22, 0.65);
        }
        .nv-timeline-event[data-category="location"] .nv-timeline-pin {
            background: linear-gradient(145deg, #6366f1, #7c3aed);
            color: #ede9fe;
            border-color: rgba(99, 102, 241, 0.65);
        }
        .nv-timeline-event {
            --nv-timeline-accent: rgba(226, 232, 240, 0.78);
        }
        .nv-timeline-event[data-category="personal"] {
            --nv-timeline-accent: rgba(5, 150, 105, 0.8);
        }
        .nv-timeline-event[data-category="family"] {
            --nv-timeline-accent: rgba(37, 99, 235, 0.75);
        }
        .nv-timeline-event[data-category="facts"] {
            --nv-timeline-accent: rgba(234, 88, 12, 0.75);
        }
        .nv-timeline-event[data-category="location"] {
            --nv-timeline-accent: rgba(99, 102, 241, 0.75);
        }
        .nv-timeline-event[data-category="location"] .nv-timeline-card {
            background: linear-gradient(135deg, rgba(238, 242, 255, 0.96), rgba(224, 231, 255, 0.94));
            max-height: min(24vh, 340px);
        }
        .nv-timeline-link {
            color: #2563eb;
            text-decoration: underline;
            text-decoration-thickness: 1px;
        }
        .nv-timeline-link:hover {
            color: #1d4ed8;
        }
        .nv-timeline-info-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.9rem;
            height: 1.9rem;
            border-radius: 9999px;
            border: none;
            background: rgba(15, 23, 42, 0.06);
            color: #1e293b;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .nv-timeline-info-btn:hover {
            background: rgba(37, 99, 235, 0.15);
            transform: translateY(-1px);
        }
        .nv-timeline-info-btn i {
            pointer-events: none;
        }
        .nv-timeline-info-card {
            padding: 1rem 1.2rem;
        }
        .nv-timeline-info-title {
            font-size: 1.05rem;
            margin-bottom: 0.75rem;
        }
        .nv-timeline-info-list {
            display: grid;
            grid-template-columns: max-content 1fr;
            column-gap: 0.75rem;
            row-gap: 0.5rem;
        }
        .nv-timeline-info-list dt {
            font-weight: 600;
            color: #1f2937;
        }
        .nv-timeline-info-list dd {
            margin: 0;
            color: #374151;
        }
        .nv-timeline-info-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000;
        }
        .nv-timeline-info-modal[aria-hidden="false"] {
            display: flex;
        }
        .nv-timeline-info-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
        }
        .nv-timeline-info-dialog {
            position: relative;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 25px 45px rgba(15, 23, 42, 0.25);
            padding: 1.5rem 1.75rem;
            max-width: 480px;
            width: calc(100% - 2rem);
            max-height: 80vh;
            overflow-y: auto;
            z-index: 1;
        }
        .nv-timeline-info-close {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            border: none;
            background: transparent;
            font-size: 1.25rem;
            color: #4b5563;
            cursor: pointer;
        }
        .nv-timeline-info-close:hover {
            color: #1f2937;
        }
        .nv-timeline-card {
            margin: 0;
            padding: 0.85rem 1.05rem;
            border-radius: 0.9rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.97), rgba(241, 245, 249, 0.94));
            box-shadow: 0 16px 28px rgba(15, 23, 42, 0.09);
            border-left: 4px solid var(--nv-timeline-accent);
            width: min(32rem, 100%);
            max-height: min(20vh, 280px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .nv-timeline-event[data-side="left"] .nv-timeline-card {
            border-left: 0;
            border-right: 4px solid var(--nv-timeline-accent);
            text-align: right;
        }
        .nv-timeline-event[data-side="left"] .nv-timeline-card > .flex {
            flex-direction: row-reverse;
        }
        .nv-timeline-event[data-side="left"] .nv-timeline-chip {
            margin-left: auto;
        }
        .nv-timeline-event[data-side="right"] .nv-timeline-card {
            text-align: left;
        }
        .nv-timeline-card h4 {
            font-size: 1.05rem;
            line-height: 1.35rem;
        }
        .nv-timeline-card p {
            font-size: 0.9rem;
        }
        .nv-timeline-card .nv-timeline-body {
            flex: 1 1 auto;
            overflow: hidden;
        }
        .nv-timeline-text {
            overflow: hidden;
        }
        .nv-timeline-text > p {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .nv-timeline-event[data-category="location"] .nv-timeline-text > p {
            font-size: 0.82rem;
        }
        .nv-timeline-event[data-scope="break"] .nv-timeline-pin {
            background: linear-gradient(145deg, #e2e8f0, #cbd5f5);
            color: #475569;
            border-color: rgba(148, 163, 184, 0.65);
        }
        .nv-timeline-event[data-scope="break"] {
            --nv-timeline-accent: rgba(148, 163, 184, 0.55);
        }
        .nv-timeline-event[data-scope="break"] .nv-timeline-card {
            text-align: center;
            background: linear-gradient(135deg, rgba(248, 250, 252, 0.95), rgba(226, 232, 240, 0.95));
            width: min(38rem, 100%);
        }
        .nv-timeline-event[data-scope="break"] .nv-timeline-card h4 {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.9rem;
            color: #475569;
        }
        .nv-timeline-break-range {
            display: inline-block;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            padding-bottom: 0.5rem;
        }
        .nv-timeline-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            font-weight: 600;
            background: rgba(15, 23, 42, 0.08);
            color: #1e293b;
        }
        .nv-timeline-event[data-category="personal"] .nv-timeline-chip {
            background: rgba(16, 185, 129, 0.12);
            color: #0f766e;
        }
        .nv-timeline-event[data-category="family"] .nv-timeline-chip {
            background: rgba(59, 130, 246, 0.12);
            color: #1d4ed8;
        }
        .nv-timeline-event[data-category="facts"] .nv-timeline-chip {
            background: rgba(249, 115, 22, 0.12);
            color: #b45309;
        }
        .nv-timeline-event[data-category="location"] .nv-timeline-chip {
            background: rgba(99, 102, 241, 0.14);
            color: #4338ca;
        }
        .nv-timeline-subject-avatar-wrapper {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.75rem;
            overflow: hidden;
            border: 2px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.12);
            flex-shrink: 0;
        }
        .nv-timeline-subject-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .nv-timeline-subject-icon {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(99, 102, 241, 0.12);
            color: #4338ca;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.12);
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .nv-timeline-discussion {
            border-radius: 0.75rem;
            background: rgba(99, 102, 241, 0.08);
            padding: 0.85rem 1rem;
            max-height: clamp(6rem, 18vh, 220px);
            overflow: hidden;
        }
        .nv-timeline-event[data-category="location"] .nv-timeline-discussion {
            max-height: clamp(7rem, 22vh, 240px);
        }
        .nv-timeline-discussion[data-expanded="true"] .nv-timeline-discussion-snippet {
            display: none;
        }
        .nv-timeline-discussion-full {
            display: none;
            margin-top: 0.75rem;
            max-height: clamp(8rem, 20vh, 280px);
            overflow-y: auto;
        }
        .nv-timeline-discussion[data-expanded="true"] .nv-timeline-discussion-full {
            display: block;
        }
        .nv-timeline-more-btn {
            margin-top: 0.75rem;
            background: transparent;
            border: none;
            color: #4338ca;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
        }
        .nv-timeline-more-btn:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .nv-timeline-wrapper {
                padding: 2rem 1.25rem;
            }
            .nv-timeline-axis {
                display: none;
            }
            .nv-timeline-wrapper > .relative {
                position: static;
                height: auto !important;
            }
            .nv-timeline-event {
                position: static !important;
                transform: none !important;
                padding: 0 !important;
            margin-bottom: 1.5rem;
            width: 100% !important;
        }
        .nv-timeline-event .nv-timeline-card {
            width: 100%;
            max-height: min(20vh, 320px);
        }
        .nv-timeline-event[data-category="location"] .nv-timeline-card {
            max-height: min(24vh, 340px);
            display: block;
        }
            .nv-timeline-pin {
                display: none;
            }
            .nv-timeline-text > p {
                -webkit-line-clamp: 2;
            }
            .nv-timeline-discussion {
                max-height: min(20vh, 220px);
            }
            .nv-timeline-event[data-category="location"] .nv-timeline-discussion {
                max-height: min(24vh, 240px);
            }
            .nv-timeline-event[data-category="location"] .nv-timeline-text > p {
                font-size: 0.78rem;
            }
            .nv-timeline-media {
                display: none;
            }
        }
        .nv-timeline-muted {
            color: #64748b;
            font-size: 0.875rem;
        }
        .nv-timeline-empty {
            border: 1px dashed rgba(148, 163, 184, 0.6);
            border-radius: 1rem;
            padding: 2.5rem;
            text-align: center;
            background: rgba(248, 250, 252, 0.8);
            color: #475569;
        }
    </style>
    <?php
}
?>
<div id="<?= htmlspecialchars($timelineRootId, ENT_QUOTES) ?>" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white/90 p-6 shadow-lg ring-1 ring-slate-100">
        <div>
            <h3 class="text-2xl font-semibold text-slate-800">Life Timeline</h3>
            <p class="text-sm text-slate-500">
                <?php if ($rangeLabel !== '' && strcasecmp($rangeLabel, 'Timeline') !== 0): ?>
                    <?= htmlspecialchars($rangeLabel, ENT_QUOTES) ?>
                    <span aria-hidden="true">&mdash;</span>
                <?php endif; ?>
                <?= htmlspecialchars($personName, ENT_QUOTES) ?>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-6 text-sm text-slate-600">
            <label class="flex items-center gap-2 font-medium">
                <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 nv-timeline-toggle" data-timeline-toggle="family" checked>
                Include other family members
            </label>
            <label class="flex items-center gap-2 font-medium">
                <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 nv-timeline-toggle" data-timeline-toggle="location" <?= $locationEventsVisibleByDefault ? 'checked' : '' ?>>
                Include location events
            </label>
            <label class="flex items-center gap-2 font-medium">
                <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 nv-timeline-toggle" data-timeline-toggle="facts" <?= $factsVisibleByDefault ? 'checked' : '' ?>>
                Include Facts &amp; Events
            </label>
        </div>
    </div>
    <div class="nv-timeline-scroll overflow-y-auto rounded-2xl bg-white/80 p-2 shadow-xl ring-1 ring-slate-100" style="max-height: 72vh;">
        <?php if (empty($timelineEvents)): ?>
            <div class="nv-timeline-empty">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                    <i class="fa-solid fa-feather"></i>
                </div>
                <h4 class="text-lg font-semibold text-slate-700">No timeline entries yet</h4>
                <p class="mt-2 text-sm text-slate-500">
                    As you add marriages, children, or documented events for <?= htmlspecialchars($personName, ENT_QUOTES) ?>,
                    their story will unfold here.
                </p>
            </div>
        <?php else: ?>
            <div class="nv-timeline-wrapper">
                <div class="nv-timeline-axis"></div>
                <div class="relative" style="height: <?= $canvasHeight ?>px;">
                    <?php foreach ($renderEvents as $eventIndex => $event): ?>
                        <?php
                            $category = $event['category'] ?? 'personal';
                            $scope = $event['scope'] ?? 'self';
                            $initialVisible = $event['initial_visibility'] ?? true;
                            $styleFragments = [
                                'top: ' . sprintf('%.2f', (float) ($event['position'] ?? 0)) . 'px',
                                'z-index: ' . (1000 - $eventIndex)
                            ];
                            if (!$initialVisible) {
                                $styleFragments[] = 'opacity:0';
                                $styleFragments[] = 'visibility:hidden';
                                $styleFragments[] = 'pointer-events:none';
                            }
                            $styleAttribute = implode(';', $styleFragments) . ';';
                            $eventIdentifier = (string) ($event['id'] ?? ('event-' . $eventIndex));
                            $infoId = !empty($event['details_html']) ? 'nv-timeline-info-' . md5($eventIdentifier) : null;
                            $fullDescriptionHtml = $event['full_description_html'] ?? ($event['description_html'] ?? null);
                            $fullDescriptionTextLength = $fullDescriptionHtml ? mb_strlen(strip_tags($fullDescriptionHtml)) : 0;
                            $fullDescriptionId = null;
                            if ($fullDescriptionHtml && $fullDescriptionTextLength > 160) {
                                $fullDescriptionId = 'nv-timeline-desc-' . md5($eventIdentifier);
                            }
                            $subjectAvatarAlt = $event['subject_avatar_alt'] ?? ($event['title'] ?? $personName);
                        ?>
                        <div
                            class="nv-timeline-event transition-opacity duration-300"
                            data-category="<?= htmlspecialchars($category, ENT_QUOTES) ?>"
                            data-scope="<?= htmlspecialchars($scope, ENT_QUOTES) ?>"
                            data-side="<?= htmlspecialchars($event['side'] ?? 'left', ENT_QUOTES) ?>"
                            style="<?= $styleAttribute ?>"
                        >
                            <div class="nv-timeline-pin">
                                <i class="<?= htmlspecialchars($event['icon'], ENT_QUOTES) ?> text-lg"></i>
                            </div>
                            <?php if ($scope === 'break'): ?>
                                <div class="nv-timeline-card">
                                    <?php if (!empty($event['display_date'])): ?>
                                        <span class="nv-timeline-break-range"><?= htmlspecialchars($event['display_date'], ENT_QUOTES) ?></span>
                                    <?php endif; ?>
                                    <h4 class="font-semibold"><?= $event['title_html'] ?? htmlspecialchars($event['title'] ?? '', ENT_QUOTES) ?></h4>
                                    <?php if (!empty($event['description']) || !empty($event['description_html'])): ?>
                                        <p class="mt-2 nv-timeline-muted"><?= $event['description_html'] ?? htmlspecialchars($event['description'] ?? '', ENT_QUOTES) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="nv-timeline-card">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <span class="nv-timeline-chip">
                                            <?php if ($scope === 'self'): ?>
                                                Life Event
                                            <?php elseif ($scope === 'marriage'): ?>
                                                Marriage
                                            <?php elseif ($scope === 'child'): ?>
                                                Child
                                            <?php elseif ($scope === 'parent'): ?>
                                                Parent
                                            <?php elseif ($scope === 'discussion_location'): ?>
                                                Historical Event
                                            <?php else: ?>
                                                Fact
                                            <?php endif; ?>
                                        </span>
                                        <div class="flex items-center gap-2 text-slate-600">
                                            <span class="font-semibold">
                                                <?= htmlspecialchars($event['display_date'], ENT_QUOTES) ?>
                                                <?php if (!empty($event['assumed'])): ?>
                                                    <span class="ml-2 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                                        Estimated
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if ($infoId): ?>
                                                <button type="button" class="nv-timeline-info-btn" data-info-target="<?= $infoId ?>" aria-label="View full details">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="nv-timeline-body mt-4 flex gap-4 items-start">
                                        <?php if (!empty($event['media_gallery']) && is_array($event['media_gallery'])): ?>
                                            <div class="nv-timeline-media">
                                                <?php foreach (array_slice($event['media_gallery'], 0, 3) as $media): ?>
                                                    <div class="nv-timeline-media-thumb">
                                                        <img src="<?= htmlspecialchars($media['src'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($media['alt'] ?? $event['title'] ?? 'Event image', ENT_QUOTES) ?>">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="nv-timeline-text flex-1 min-w-0">
                                            <div class="flex items-center gap-3 flex-wrap">
                                                <?php if (!empty($event['subject_avatar'])): ?>
                                                    <span class="nv-timeline-subject-avatar-wrapper">
                                                        <img src="<?= htmlspecialchars($event['subject_avatar'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($subjectAvatarAlt, ENT_QUOTES) ?>" class="nv-timeline-subject-avatar">
                                                    </span>
                                                <?php elseif (!empty($event['subject_avatar_icon'])): ?>
                                                    <span class="nv-timeline-subject-icon" role="img" aria-label="<?= htmlspecialchars($subjectAvatarAlt, ENT_QUOTES) ?>">
                                                        <i class="<?= htmlspecialchars($event['subject_avatar_icon'], ENT_QUOTES) ?>" aria-hidden="true"></i>
                                                    </span>
                                                <?php endif; ?>
                                                <h4 class="text-xl font-semibold text-slate-800 m-0 flex-1 min-w-0"><?= $event['title_html'] ?? htmlspecialchars($event['title'] ?? '', ENT_QUOTES) ?></h4>
                                            </div>
                                            <?php if (!empty($event['description']) || !empty($event['description_html'])): ?>
                                                <p class="mt-2 nv-timeline-muted"><?= $event['description_html'] ?? htmlspecialchars($event['description'] ?? '', ENT_QUOTES) ?></p>
                                            <?php endif; ?>
                                            <?php if ($fullDescriptionId): ?>
                                                <button type="button" class="nv-timeline-more-btn" data-info-target="<?= $fullDescriptionId ?>">Read full text</button>
                                            <?php endif; ?>
                                            <?php if (!empty($event['discussion_content']) && is_array($event['discussion_content'])): ?>
                                                <?php $discussionContent = $event['discussion_content']; ?>
                                                <?php if (!empty($discussionContent['excerpt_html'])): ?>
                                                    <div class="nv-timeline-discussion mt-3" data-expanded="false" data-has-more="<?= !empty($discussionContent['has_more']) ? 'true' : 'false' ?>">
                                                        <div class="nv-timeline-discussion-snippet"><?= $discussionContent['excerpt_html'] ?></div>
                                                        <?php if (!empty($discussionContent['has_more'])): ?>
                                                            <div class="nv-timeline-discussion-full"><?= $discussionContent['full_html'] ?></div>
                                                            <button type="button" class="nv-timeline-more-btn" data-toggle-discussion>More...</button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($fullDescriptionId): ?>
                            <div id="<?= $fullDescriptionId ?>" class="hidden nv-timeline-info-content"><?= $fullDescriptionHtml ?></div>
                        <?php endif; ?>
                        <?php if ($infoId): ?>
                            <div id="<?= $infoId ?>" class="hidden nv-timeline-info-content"><?= $event['details_html'] ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
    (function () {
        const rootId = <?= json_encode($timelineRootId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        function initTimeline() {
        const root = document.getElementById(rootId);
        if (!root) {
            return;
        }
        const scrollContainer = root.querySelector('.nv-timeline-scroll');
        const scrollToEarliestVisible = () => {
            if (!scrollContainer) {
                return;
            }
            const events = root.querySelectorAll('.nv-timeline-event');
            for (const event of events) {
                const style = event.style;
                if (style.visibility === 'hidden' || style.opacity === '0') {
                    continue;
                }
                const offset = Math.max(0, event.offsetTop - 80);
                scrollContainer.scrollTo({ top: offset, behavior: 'auto' });
                return;
            }
            scrollContainer.scrollTop = 0;
        };
        scrollToEarliestVisible();
        requestAnimationFrame(scrollToEarliestVisible);
        setTimeout(scrollToEarliestVisible, 200);
        const toggles = root.querySelectorAll('.nv-timeline-toggle');
        toggles.forEach((toggle) => {
            const applyState = () => {
                const category = toggle.getAttribute('data-timeline-toggle');
                if (!category) {
                    return;
                }
                const visible = toggle.checked;
                root.querySelectorAll('.nv-timeline-event[data-category="' + category + '"]').forEach((event) => {
                    event.style.opacity = visible ? '1' : '0';
                    event.style.pointerEvents = visible ? 'auto' : 'none';
                    event.style.visibility = visible ? 'visible' : 'hidden';
                    event.setAttribute('aria-hidden', visible ? 'false' : 'true');
                });
                requestAnimationFrame(scrollToEarliestVisible);
            };
                toggle.addEventListener('change', applyState);
                applyState();
            });
        const setupDiscussionToggles = () => {
            root.querySelectorAll('[data-toggle-discussion]').forEach((button) => {
                button.addEventListener('click', () => {
                    const container = button.closest('.nv-timeline-discussion');
                    if (!container) {
                        return;
                    }
                    const expanded = container.getAttribute('data-expanded') === 'true';
                    container.setAttribute('data-expanded', expanded ? 'false' : 'true');
                    button.textContent = expanded ? 'More...' : 'Show less';
                });
            });
        };
        setupDiscussionToggles();
        const infoModal = document.getElementById('nv-timeline-info-modal');
        const infoBody = infoModal ? infoModal.querySelector('.nv-timeline-info-body') : null;
        if (infoModal && infoBody) {
            const openInfo = (targetId) => {
                const source = targetId ? document.getElementById(targetId) : null;
                if (!source) {
                    return;
                }
                infoBody.innerHTML = source.innerHTML;
                infoModal.setAttribute('aria-hidden', 'false');
                infoModal.focus();
            };
            const closeInfo = () => {
                infoModal.setAttribute('aria-hidden', 'true');
                infoBody.innerHTML = '';
            };
            root.querySelectorAll('.nv-timeline-info-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    const targetId = button.getAttribute('data-info-target');
                    if (targetId) {
                        openInfo(targetId);
                    }
                });
            });
            infoModal.querySelectorAll('[data-info-close]').forEach((closer) => {
                closer.addEventListener('click', closeInfo);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && infoModal.getAttribute('aria-hidden') === 'false') {
                    closeInfo();
                }
            });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTimeline, { once: true });
    } else {
        initTimeline();
        }
    })();
</script>
<?php if (!defined('NV_TIMELINE_INFO_MODAL')): ?>
    <?php define('NV_TIMELINE_INFO_MODAL', true); ?>
    <div id="nv-timeline-info-modal" class="nv-timeline-info-modal" aria-hidden="true" tabindex="-1">
        <div class="nv-timeline-info-backdrop" data-info-close></div>
        <div class="nv-timeline-info-dialog">
            <button type="button" class="nv-timeline-info-close" data-info-close aria-label="Close details">&times;</button>
            <div class="nv-timeline-info-body"></div>
        </div>
    </div>
<?php endif; ?>


