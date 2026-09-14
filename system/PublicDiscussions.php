<?php
require_once __DIR__ . '/admin/MigrationRunner.php';
require_once __DIR__ . '/PublicArticleHtml.php';

final class PublicDiscussions
{
    public const MIGRATION = '20260914_003_public_discussions';
    private bool $ready = false;

    public function __construct(private PDO $pdo)
    {
        try {
            $states = (new MigrationRunner($pdo, __DIR__ . '/migrations'))->status();
            if (($states[self::MIGRATION]['status'] ?? '') === 'applied') {
                $pdo->query('SELECT is_public, public_published_at, public_published_by FROM discussions LIMIT 0');
                $this->ready = true;
            }
        } catch (Throwable $error) { error_log('Public discussions unavailable: ' . $error->getMessage()); }
    }

    public function ready(): bool { return $this->ready; }

    public function listing(): array
    {
        if (!$this->ready) { return []; }
        return $this->pdo->query('SELECT id, title FROM discussions WHERE is_public=1
            ORDER BY public_published_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function article(int $id): ?array
    {
        if (!$this->ready) { return null; }
        $query = $this->pdo->prepare('SELECT id, title, content FROM discussions WHERE id=? AND is_public=1');
        $query->execute([$id]);
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function publish(int $id, bool $published, int $administratorId): void
    {
        if (!$this->ready) { throw new RuntimeException('Run the pending database migration first.'); }
        $exists = $this->pdo->prepare('SELECT id FROM discussions WHERE id=?');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) { throw new InvalidArgumentException('Discussion not found.'); }
        $update = $this->pdo->prepare('UPDATE discussions SET public_published_at=
            CASE WHEN is_public=0 AND ?=1 THEN UTC_TIMESTAMP() ELSE public_published_at END,
            public_published_by=?, is_public=? WHERE id=?');
        $update->execute([(int) $published, $administratorId, (int) $published, $id]);
    }

    public static function title(array $article): string
    {
        return trim((string) $article['title']) ?: 'Family story';
    }

    public static function imageUrl(int $id, string $path): string
    {
        return 'public_discussion_image.php?discussion_id=' . $id . '&path=' . rawurlencode($path);
    }

    /** Inline images and image attachments only; no comment uploads or other private files. */
    public function images(array $article, string $host): array
    {
        $images = (new PublicArticleHtml((int) $article['id'], $host))->render($article['content'])['images'];
        $query = $this->pdo->prepare('SELECT file_path FROM discussion_files WHERE discussion_id=?');
        $query->execute([(int) $article['id']]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $path) {
            $local = PublicArticleHtml::uploadPath($path, $host);
            if ($local !== null) { $images[] = $local; }
        }
        return array_values(array_unique($images));
    }

    public static function imageFile(string $path, string $rootDirectory): ?array
    {
        $root = realpath($rootDirectory . '/uploads');
        $file = realpath($rootDirectory . '/' . $path);
        if (!$root || !$file || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) { return null; }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
            'image/bmp', 'image/x-ms-bmp', 'image/svg+xml'], true)) { return null; }
        return ['file' => $file, 'mime' => $mime];
    }
}
