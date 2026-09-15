<?php

final class DiscussionFilters
{
    public const CATEGORIES = ['family' => 'Family events', 'historical' => 'Historical events',
        'news' => 'News', 'other' => 'Other'];
    public readonly string $search;
    public readonly array $categories;

    public function __construct(array $query)
    {
        $this->search = is_string($query['q'] ?? null) ? mb_substr(trim($query['q']), 0, 200, 'UTF-8') : '';
        // The marker distinguishes an unchecked set from the initial, unfiltered page.
        $submitted = isset($query['filter']) || array_key_exists('categories', $query);
        $requested = $submitted ? ($query['categories'] ?? []) : array_keys(self::CATEGORIES);
        $this->categories = is_array($requested) ? array_values(array_filter(array_keys(self::CATEGORIES),
            static fn($category) => in_array($category, $requested, true))) : [];
    }

    public function apply(array $discussions): array
    {
        $needle = self::normaliseWhitespace($this->search);
        return array_values(array_filter($discussions, function (array $discussion) use ($needle): bool {
            $flags = ['family' => !empty($discussion['is_event']), 'historical' => !empty($discussion['is_historical_event']),
                'news' => !empty($discussion['is_news'])];
            $flags['other'] = !in_array(true, $flags, true);
            $matchesCategory = false;
            foreach ($this->categories as $category) { $matchesCategory = $matchesCategory || $flags[$category]; }
            if (!$matchesCategory) { return false; }
            if ($needle === '') { return true; }
            $html = (string) ($discussion['content'] ?? '');
            // Search readable text, not HTML attributes or formatting instructions.
            $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', $html) ?? '';
            $html = preg_replace('~</?(?:p|div|br|li|h[1-6]|tr|td|th|blockquote)\b[^>]*>~i', ' ', $html) ?? '';
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = self::normaliseWhitespace((string) ($discussion['title'] ?? '') . ' ' . $text);
            return mb_stripos($text, $needle, 0, 'UTF-8') !== false;
        }));
    }

    private static function normaliseWhitespace(string $value): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value) ?? $value);
    }
}
