<?php

final class DiscussionCreation
{
    public function __construct(private PDO $pdo) {}

    public function create(array $draft, int $authorId, bool $requireTitle = false): int
    {
        if ($authorId < 1) { throw new InvalidArgumentException('Log in before posting a discussion.'); }
        $title = trim($draft['title']);
        $content = trim($draft['content']);
        $text = preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($content === '' || ($text === '' && !preg_match('/<img\b/i', $content))) {
            throw new InvalidArgumentException('Please enter some discussion content.');
        }
        if ($requireTitle && $title === '') { throw new InvalidArgumentException('Please enter a title.'); }
        if (mb_strlen($title, 'UTF-8') > 255) { throw new InvalidArgumentException('The title must be 255 characters or fewer.'); }
        $start = self::eventDate($draft['event_date'] ?? '');
        $finish = self::eventDate($draft['event_date_finish'] ?? '');
        $location = trim($draft['event_location'] ?? '');
        if ($start !== null && $finish !== null && $finish < $start) {
            throw new InvalidArgumentException('The event finish must not be before its start.');
        }
        // Use the throwing PDO API, not Database::insert(), which can return an ID after a failed query.
        // General discussions explicitly use individual_id=0 rather than relying on a server's default.
        $statement = $this->pdo->prepare('INSERT INTO discussions
            (user_id, title, content, is_sticky, is_event, is_historical_event, is_news,
             event_date, event_date_finish, event_location, individual_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())');
        if (!$statement->execute([$authorId, $title, $content, !empty($draft['is_sticky']) ? 1 : 0,
            !empty($draft['is_event']) ? 1 : 0, !empty($draft['is_historical_event']) ? 1 : 0,
            !empty($draft['is_news']) ? 1 : 0, $start, $finish, $location === '' ? null : $location])) {
            throw new RuntimeException('Discussion insert failed.');
        }
        $id = (int) $this->pdo->lastInsertId();
        if ($id < 1) { throw new RuntimeException('Discussion insert returned no ID.'); }
        return $id;
    }

    private static function eventDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') { return null; }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date && $date->format($format) === $value) { return $date->format('Y-m-d H:i:s'); }
        }
        throw new InvalidArgumentException('Enter a valid event date, for example 2026-09-14 14:30:00.');
    }
}
