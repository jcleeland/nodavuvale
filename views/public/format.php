<?php
function ancestorEscape($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function ancestorName(array $person): string
{
    return str_replace('_', ' ', trim($person['first_names'] . ' ' . $person['last_name']));
}
function ancestorDate(array $person, string $kind): string
{
    $year = (int) ($person[$kind . '_year'] ?? 0);
    if ($year < 1) { return 'Unknown'; }
    $month = (int) ($person[$kind . '_month'] ?? 0);
    $day = (int) ($person[$kind . '_date'] ?? 0);
    $value = (string) $year;
    if ($month > 0 && checkdate($month, $day > 0 ? $day : 1, $year)) {
        $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day > 0 ? $day : 1));
        $value = $date->format($day > 0 ? 'j F Y' : 'F Y');
    }
    $prefix = trim((string) ($person[$kind . '_prefix'] ?? ''));
    return ($prefix !== '' && $prefix !== 'exactly' ? ucfirst($prefix) . ' ' : '') . $value;
}
function ancestorUrl(int $id, bool $preview): string
{
    return 'index.php?to=public/individual&individual_id=' . $id . ($preview ? '&preview=1' : '');
}
