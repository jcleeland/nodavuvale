<?php
function renderAncestorDirectory(array $people, array $get = [], bool $preview = false): string
{
    $_GET = $get;
    $publicPreview = $preview;
    $publicAccess = new class($people) {
        public function __construct(private array $people) {}
        public function settings(): array { return ['threshold_years' => 75]; }
        public function directory($search, $page, $preview): array {
            return ['people' => $this->people, 'total' => count($this->people), 'pages' => $this->people ? 2 : 0];
        }
    };
    ob_start();
    require dirname(__DIR__) . '/views/public/ancestors.php';
    return ob_get_clean();
}
function directoryViewAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
$base = ['aka_names'=>'', 'birth_prefix'=>'', 'birth_year'=>1820, 'birth_month'=>null, 'birth_date'=>null,
    'death_prefix'=>'', 'death_year'=>1890, 'death_month'=>null, 'death_date'=>null];
$people = [
    ['id'=>1,'first_names'=>'Anna','last_name'=>'Boehm'] + $base,
    ['id'=>2,'first_names'=>'Johann','last_name'=>'boehm','birth_year'=>1830] + $base,
    ['id'=>3,'first_names'=>'Mary','last_name'=>'Macdonald'] + $base,
    ['id'=>4,'first_names'=>'Unknown','last_name'=>''] + $base,
];
$html = renderAncestorDirectory($people, ['q'=>'family'], true);
$document = new DOMDocument();
@$document->loadHTML($html);
$xpath = new DOMXPath($document);
directoryViewAssert($xpath->query('//section[contains(@class,"hero")]/div[contains(@class,"hero-content")]/h1')->length === 1, 'Site hero contains page heading');
directoryViewAssert(str_contains(preg_replace('/\s+/', ' ', strip_tags($html)), '75 years ago'), 'Introduction uses configured threshold');
directoryViewAssert($xpath->query('//section/h2[text()="Boehm"]')->length === 1 && $xpath->query('//section/h2[text()="boehm"]')->length === 0, 'Case variants share a surname group');
directoryViewAssert($xpath->query('//section[h2="Boehm"]/ul/li')->length === 2, 'Surname group contains both records');
directoryViewAssert($xpath->query('//section/h2[text()="Surname not recorded"]')->length === 1, 'Missing surname has a clear label');
directoryViewAssert($xpath->query('//a[contains(@href,"individual_id=") and contains(@href,"preview=1")]')->length === 4, 'Profile links preserve preview');
directoryViewAssert($xpath->query('//nav/a[contains(@href,"q=family") and contains(@href,"preview=1")]')->length === 1, 'Pagination preserves search and preview');
$escaped = renderAncestorDirectory([['id'=>5,'first_names'=>'<script>alert(1)</script>','last_name'=>'<unsafe>'] + $base]);
directoryViewAssert(!str_contains($escaped, '<script>') && str_contains($escaped, '&lt;unsafe&gt;'), 'Names and surname headings escaped');
$empty = renderAncestorDirectory([], ['q'=>'missing']);
directoryViewAssert(str_contains($empty, 'No public ancestors match your search.'), 'Search empty state is helpful');
if ($path = getenv('NV_ANCESTOR_RENDER_OUTPUT')) {
    file_put_contents($path, '<!DOCTYPE html><html lang="en"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ancestor directory preview</title><link rel="stylesheet" href="/styles/tailwind.min.css"><link rel="stylesheet" href="/styles/styles.css"><body class="bg-cream text-brown font-sans non-individual-page">' . renderAncestorDirectory($people) . '</body></html>');
}
echo "Ancestor directory view tests passed.\n";
