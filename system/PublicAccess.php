<?php

final class PublicAccess
{
    public const MIGRATION = '20260914_001_public_ancestors';
    private array $settings = ['enabled' => 0, 'threshold_years' => 50, 'timezone' => 'Australia/Sydney'];
    private bool $ready = false;
    private DateTimeImmutable $today;
    private const FIELDS = 'id, first_names, aka_names, last_name, birth_prefix, birth_year, birth_month, birth_date,
        death_prefix, death_year, death_month, death_date, exclude_from_public';

    public function __construct(private PDO $pdo, ?DateTimeImmutable $today = null)
    {
        try {
            require_once __DIR__ . '/admin/MigrationRunner.php';
            $states = (new MigrationRunner($pdo, __DIR__ . '/migrations'))->status();
            if (($states[self::MIGRATION]['status'] ?? '') === 'applied') {
                $settings = $pdo->query('SELECT * FROM public_access_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
                if ($settings && self::validSettings($settings)) {
                    // Verify the column even if a database was restored without its matching schema.
                    $pdo->query('SELECT exclude_from_public FROM individuals LIMIT 0');
                    $this->settings = $settings;
                    $this->ready = true;
                }
            }
        } catch (Throwable $error) {
            error_log('Public access unavailable: ' . $error->getMessage());
        }
        $this->today = ($today ?? new DateTimeImmutable('now', new DateTimeZone($this->settings['timezone'])))->setTime(0, 0);
    }

    public static function validSettings(array $settings): bool
    {
        return in_array((string) ($settings['enabled'] ?? ''), ['0', '1'], true)
            && filter_var($settings['threshold_years'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]) !== false
            && in_array($settings['timezone'] ?? '', DateTimeZone::listIdentifiers(), true);
    }

    public function ready(): bool { return $this->ready; }
    public function enabled(): bool { return $this->ready && (int) $this->settings['enabled'] === 1; }
    public function settings(): array { return $this->settings; }

    /** Unknown parts use the end of their period. Invalid input never becomes a guessed date. */
    public static function latestDeath(array $person): ?DateTimeImmutable
    {
        $prefix = strtolower(trim((string) ($person['death_prefix'] ?? '')));
        if (!in_array($prefix, ['', 'exactly', 'before'], true)) {
            return null;
        }
        $parts = [];
        foreach (['death_year', 'death_month', 'death_date'] as $key) {
            $value = $person[$key] ?? null;
            if ($value === null || $value === '' || (string) $value === '0') {
                $parts[] = null;
            } elseif (!ctype_digit((string) $value)) {
                return null;
            } else {
                $parts[] = (int) $value;
            }
        }
        [$year, $month, $day] = $parts;
        if (!$year || $year > 9999 || ($day !== null && $month === null)) {
            return null;
        }
        $month ??= 12;
        if (!checkdate($month, $day ?? 1, $year)) {
            return null;
        }
        $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day ?? 1), new DateTimeZone('UTC'));
        return $day === null ? $date->modify('last day of this month') : $date;
    }

    public static function eligible(array $person, DateTimeImmutable $today, int $years = 50): bool
    {
        if (!array_key_exists('exclude_from_public', $person) || (string) $person['exclude_from_public'] !== '0') {
            return false;
        }
        $from = self::publicFrom($person, $years);
        return $from !== null && $from <= $today->format('Y-m-d');
    }

    public static function publicFrom(array $person, int $years = 50): ?string
    {
        $latest = self::latestDeath($person);
        if ($latest === null || $years < 1 || $years > 500) { return null; }
        // Clamp a February 29 anniversary to the final day of February when necessary.
        $year = (int) $latest->format('Y') + $years;
        if ($year > 9999) { return null; }
        $month = (int) $latest->format('m');
        $day = (int) $latest->format('d');
        while (!checkdate($month, $day, $year)) { --$day; }
        $anniversary = sprintf('%04d-%02d-%02d', $year, $month, $day);
        if ($anniversary === '9999-12-31') { return null; }
        return (new DateTimeImmutable($anniversary, new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
    }

    public function qualifies(array $person): bool
    {
        return $this->ready && self::eligible($person, $this->today, (int) $this->settings['threshold_years']);
    }

    public function statusLabel(array $person): string
    {
        if (!$this->ready) { return 'Private: database migration required'; }
        if ((int) ($person['exclude_from_public'] ?? 1) !== 0) { return 'Private: excluded by an administrator'; }
        if (self::latestDeath($person) === null) { return 'Private: death date unknown or uncertain'; }
        if (!$this->qualifies($person)) {
            $from = self::publicFrom($person, (int) $this->settings['threshold_years']);
            return $from ? 'Private until ' . $from : 'Private: death does not yet meet the age threshold';
        }
        return $this->enabled() ? 'Public automatically' : 'Eligible: public access is switched off';
    }

    public function person(int $id, bool $preview = false): ?array
    {
        if (!$this->ready || (!$preview && !$this->enabled())) { return null; }
        $query = $this->pdo->prepare('SELECT ' . self::FIELDS . ' FROM individuals WHERE id = ?');
        $query->execute([$id]);
        $person = $query->fetch(PDO::FETCH_ASSOC);
        return $person && $this->qualifies($person) ? $person : null;
    }

    /** Stream candidates, filter before counting/pagination, retain only the requested page. */
    public function directory(string $search, int $page, bool $preview = false): array
    {
        $result = ['people' => [], 'total' => 0, 'page' => max(1, $page), 'pages' => 0];
        if (!$this->ready || (!$preview && !$this->enabled())) { return $result; }
        $query = $this->pdo->prepare('SELECT ' . self::FIELDS . ' FROM individuals
            WHERE exclude_from_public = 0 AND death_year > 0 AND death_year <= ?
            ORDER BY last_name, first_names, id');
        $query->execute([(int) $this->today->format('Y') - (int) $this->settings['threshold_years']]);
        $offset = ($result['page'] - 1) * 30;
        while ($person = $query->fetch(PDO::FETCH_ASSOC)) {
            if (!$this->qualifies($person)) { continue; }
            $name = $person['first_names'] . ' ' . $person['last_name'] . ' ' . $person['aka_names'];
            if ($search !== '' && mb_stripos(str_replace('_', ' ', $name), $search) === false) { continue; }
            $index = $result['total']++;
            if ($index >= $offset && $index < $offset + 30) { $result['people'][] = $person; }
        }
        $result['pages'] = (int) ceil($result['total'] / 30);
        return $result;
    }

    public function relatives(int $id, bool $preview = false): array
    {
        $groups = ['Parents' => [], 'Spouses' => [], 'Children' => [], 'Siblings' => []];
        if (!$this->person($id, $preview)) { return $groups; }
        $query = $this->pdo->prepare("SELECT individual_id_1, individual_id_2, relationship_type
            FROM relationships WHERE individual_id_1 = ? OR individual_id_2 = ?");
        $query->execute([$id, $id]);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $left = (int) $row['individual_id_1'];
            $other = $left === $id ? (int) $row['individual_id_2'] : $left;
            $group = $row['relationship_type'] === 'spouse' ? 'Spouses' : ($left === $id ? 'Children' : 'Parents');
            if ($other !== $id && ($person = $this->person($other, $preview))) { $groups[$group][$other] = $person; }
        }
        // Do not traverse a private parent to reveal a sibling connection.
        foreach (array_keys($groups['Parents']) as $parentId) {
            $query = $this->pdo->prepare("SELECT individual_id_2 FROM relationships WHERE individual_id_1 = ? AND relationship_type = 'child'");
            $query->execute([$parentId]);
            foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $siblingId) {
                if ((int) $siblingId !== $id && ($person = $this->person((int) $siblingId, $preview))) {
                    $groups['Siblings'][(int) $siblingId] = $person;
                }
            }
        }
        return $groups;
    }
}
