<?php
session_name('nodavuvale_app_session');
session_start();

require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../system/nodavuvale_database.php';
require_once __DIR__ . '/../system/nodavuvale_auth.php';
require_once __DIR__ . '/../system/nodavuvale_web.php';
require_once __DIR__ . '/../system/nodavuvale_utils.php';
require_once __DIR__ . '/../vendor/simplepdf/SimplePDF.php';

$db = Database::getInstance();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo 'You must be logged in to generate reports.';
    exit;
}

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'descendants';
if (!in_array($type, ['descendants', 'ancestors'], true)) {
    http_response_code(400);
    echo 'Invalid report type.';
    exit;
}

$individualId = isset($_GET['individual_id']) ? (int) $_GET['individual_id'] : 0;
if ($individualId <= 0) {
    http_response_code(400);
    echo 'An individual must be selected.';
    exit;
}

$generationsRaw = isset($_GET['generations']) ? trim((string) $_GET['generations']) : 'All';
$maxGenerations = null;
if (strcasecmp($generationsRaw, 'All') !== 0) {
    if (!ctype_digit($generationsRaw) || (int) $generationsRaw < 1) {
        http_response_code(400);
        echo 'Generations must be "All" or a positive number.';
        exit;
    }
    $maxGenerations = (int) $generationsRaw;
}

$individual = Utils::getIndividual($individualId);
if (!$individual) {
    http_response_code(404);
    echo 'Individual not found.';
    exit;
}

$siteSettings = $db->getSiteSettings();
$siteName = $siteSettings['site_name'] ?? 'NodaVuvale';

$bookLabel = $type === 'descendants' ? 'Descendants' : 'Ancestry';
$generationData = $type === 'descendants'
    ? Utils::getDescendantsByGeneration($individualId, $maxGenerations)
    : Utils::getAncestorsByGeneration($individualId, $maxGenerations);

$rootBundleCache = [];
$rootBundle = fetchIndividualBundle($individualId, $rootBundleCache);

$pdf = new SimplePDF();
$pdf->SetMargins(20, 20, 20);
$pdf->SetBottomMargin(25);
$pdf->SetAutoPageBreak(true);
$pdf->EnablePageNumbers();

$lineMetadata = [];
if ($type === 'descendants' && !empty($generationData[1])) {
    $lineMetadata = buildDescendantLineMetadata($generationData[1]);
}

$rootPerson = $rootBundle['person'] ?? [];
$rootName = formatPersonName($rootPerson);

$indexEntries = [];
$simpleIndex = [
    'subject' => null,
    'lines_overview' => null,
    'lines' => [],
    'generations' => [],
    'appendix_page' => null,
];

createCoverPage($pdf, $rootBundle, $bookLabel, $siteName, $type);
$simpleIndexPageNumber = createSimpleIndexPage($pdf, $bookLabel, $type);
$rootPageNumber = renderIndividualPage($pdf, $rootBundle, 'Overview');
if ($rootPageNumber !== null) {
    $indexEntries[(int) $individualId] = [
        'name' => $rootName !== '' ? $rootName : 'Subject',
        'page' => $rootPageNumber,
        'color' => null,
        'line' => '',
        'relationship' => 'Subject of the book',
    ];
    $simpleIndex['subject'] = [
        'name' => $rootName !== '' ? $rootName : 'Subject',
        'page' => $rootPageNumber,
    ];
}

$parentCache = [];

if ($type === 'descendants') {
    $lines = buildDescendantLines($generationData, $lineMetadata);
    $linesOverviewPage = null;
    if (!empty($generationData[1])) {
        $linesOverviewPage = addDescendantLinesOverviewPage($pdf, $generationData[1], $lineMetadata, $rootName);
        if ($linesOverviewPage !== null) {
            $simpleIndex['lines_overview'] = [
                'label' => 'Descendant Lines',
                'page' => $linesOverviewPage,
            ];
        }
    }
    foreach ($lines as $line) {
        $lineLabel = ($line['name'] ?? '[Unknown]') . "'s Line";
        $accentColor = $line['color'] ?? null;
        $lineSimpleIndex = [
            'name' => $line['name'] ?? 'Line of Descendancy',
            'color' => $accentColor,
            'page' => null,
            'generations' => [],
        ];
        foreach ($line['generations'] as $generation => $members) {
            if (empty($members)) {
                continue;
            }
            $summaryPage = addLineGenerationSummaryPage($pdf, $generation, $members, $line, $parentCache, $rootName);
            if ($summaryPage !== null) {
                if ($lineSimpleIndex['page'] === null) {
                    $lineSimpleIndex['page'] = $summaryPage;
                }
                $lineSimpleIndex['generations'][] = [
                    'label' => 'Generation ' . $generation,
                    'page' => $summaryPage,
                ];
            }
            foreach ($members as $person) {
                $bundle = fetchIndividualBundle((int) $person['id'], $rootBundleCache);
                $relationship = formatRelationshipWithRoot($person['relationship'] ?? '', $rootName, 'descendants');
                if ($relationship === '') {
                    $relationship = 'Descendant of ' . $rootName;
                }
                $pageNumber = renderIndividualPage($pdf, $bundle, $relationship, $accentColor, $lineLabel);
                if ($pageNumber !== null && !empty($bundle['person']['id'])) {
                    $personId = (int) $bundle['person']['id'];
                    $fullName = formatPersonName($bundle['person']);
                    $indexEntries[$personId] = [
                        'name' => $fullName,
                        'page' => $pageNumber,
                        'color' => $accentColor,
                        'line' => $line['name'] ?? '',
                        'relationship' => $relationship,
                    ];
                }
            }
        }
        $simpleIndex['lines'][] = $lineSimpleIndex;
    }
} else {
    if (!empty($generationData)) {
        ksort($generationData);
        foreach ($generationData as $generation => $people) {
            if (empty($people)) {
                continue;
            }
            usort($people, [Utils::class, 'compareIndividualsByBirthThenName']);
            $summaryPage = addGenerationSummaryPage($pdf, $generation, $people, $type, $parentCache, [
                'rootName' => $rootName,
            ]);
            if ($summaryPage !== null) {
                $simpleIndex['generations'][] = [
                    'label' => 'Generation ' . $generation,
                    'page' => $summaryPage,
                ];
            }
            foreach ($people as $person) {
                $bundle = fetchIndividualBundle((int) $person['id'], $rootBundleCache);
                $relationship = formatRelationshipWithRoot($person['relationship'] ?? '', $rootName, 'ancestors');
                if ($relationship === '') {
                    $relationship = 'Ancestor of ' . $rootName;
                }
                $pageNumber = renderIndividualPage($pdf, $bundle, $relationship);
                if ($pageNumber !== null && !empty($bundle['person']['id'])) {
                    $personId = (int) $bundle['person']['id'];
                    $fullName = formatPersonName($bundle['person']);
                    $indexEntries[$personId] = [
                        'name' => $fullName,
                        'page' => $pageNumber,
                        'color' => null,
                        'line' => '',
                        'relationship' => $relationship,
                    ];
                }
            }
        }
    }
}

$appendixPage = createAppendingPage($pdf, $indexEntries, $bookLabel, $type);
if ($appendixPage !== null) {
    $simpleIndex['appendix_page'] = $appendixPage;
}

$finalPageNumber = $pdf->GetPageNumber();
populateSimpleIndexPage($pdf, $simpleIndexPageNumber, $simpleIndex, $bookLabel, $type);
if ($finalPageNumber > 0) {
    $pdf->UsePage($finalPageNumber);
}

$fileName = buildFileName($individual, $bookLabel);
$pdf->Output('I', $fileName);
exit;

function fetchIndividualBundle(int $individualId, array &$cache): array
{
    if (isset($cache[$individualId])) {
        return $cache[$individualId];
    }

    $person = Utils::getIndividual($individualId);
    if (!$person) {
        $cache[$individualId] = [
            'person' => null,
            'facts' => [],
            'stories' => [],
            'photos' => [],
            'key_image' => null,
        ];
        return $cache[$individualId];
    }

    $bundle = [
        'person' => $person,
        'facts' => Utils::getItems($individualId),
        'stories' => Utils::getIndividualDiscussions($individualId),
        'photos' => Utils::getFiles($individualId, 'image'),
        'key_image' => Utils::getKeyImage($individualId),
    ];

    $cache[$individualId] = $bundle;
    return $bundle;
}

function createCoverPage(SimplePDF $pdf, array $bundle, string $bookLabel, string $siteName, string $type): void
{
    $person = $bundle['person'];
    $fullName = formatPersonName($person);
    $pdf->AddPage();
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', 'B', 24);
    $pdf->Cell(0, 20, $fullName . "'s " . $bookLabel . ' Book', 1, 'C');
    $pdf->Ln(30);

    if (!empty($bundle['key_image'])) {
        [$imageWidth, $imageHeight] = computeImageBox($bundle['key_image'], 110.0);
        $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
        $imageX = $pdf->GetLeftMargin() + max(0, ($usableWidth - $imageWidth) / 2);
        $currentY = $pdf->GetY();
        $pdf->Image($bundle['key_image'], $imageX, $currentY, $imageWidth, $imageHeight);
        pdfSetY($pdf, $currentY + $imageHeight + 20);
    } else {
        $pdf->Ln(20);
    }

    $descriptor = $type === 'descendants' ? 'descendants' : 'ancestors';
    $subtitle = sprintf(
        'The %s of %s. Printed %s by %s (courtesy of NodaVuvule CMS).',
        $descriptor,
        $fullName,
        date('j F Y'),
        $siteName
    );
    $pdf->SetFont('Helvetica', '', 13);
    $pdf->MultiCell(0, 8, $subtitle, 'C');
}

function createSimpleIndexPage(SimplePDF $pdf, string $bookLabel, string $type): int
{
    $pdf->AddPage();
    $pageNumber = $pdf->GetPageNumber();
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', 'B', 22);
    $title = trim($bookLabel) !== '' ? ($bookLabel . ' Simple Index') : 'Simple Index';
    $pdf->Cell(0, 14, $title, 1, 'C');
    $pdf->Ln(16);

    return $pageNumber;
}

function populateSimpleIndexPage(SimplePDF $pdf, int $pageNumber, array $data, string $bookLabel, string $type): void
{
    if ($pageNumber < 1) {
        return;
    }

    $pdf->UsePage($pageNumber);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', '', 12);

    $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
    $pageWidth = 24.0;
    $labelWidth = max(0.0, $usableWidth - $pageWidth);

    $writeEntry = static function (SimplePDF $pdf, string $label, ?int $page, ?array $color = null, float $indent = 0.0) use ($labelWidth, $pageWidth): void {
        if ($label === '') {
            return;
        }
        if ($color !== null && count($color) === 3) {
            $pdf->SetTextColor((int) $color[0], (int) $color[1], (int) $color[2]);
        } else {
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->SetFont('Helvetica', 'B', 12);
        $effectiveWidth = $labelWidth;
        $currentY = $pdf->GetY();
        if ($indent > 0) {
            $effectiveWidth = max(0.0, $labelWidth - $indent);
            $pdf->SetXY($pdf->GetLeftMargin() + $indent, $currentY);
        } else {
            $pdf->SetXY($pdf->GetLeftMargin(), $currentY);
        }
        $pdf->Cell($effectiveWidth, 6, $label, 0, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Cell($pageWidth, 6, $page !== null ? (string) $page : '', 1, 'R');
    };

    if (!empty($data['subject'])) {
        $writeEntry($pdf, (string) ($data['subject']['name'] ?? 'Subject'), $data['subject']['page'] ?? null);
        $pdf->Ln(2);
    }

    if ($type === 'descendants') {
        if (!empty($data['lines_overview'])) {
            $writeEntry(
                $pdf,
                (string) ($data['lines_overview']['label'] ?? 'Descendant Lines'),
                $data['lines_overview']['page'] ?? null
            );
            $pdf->Ln(2);
        }

        foreach ($data['lines'] ?? [] as $line) {
            $writeEntry(
                $pdf,
                (string) ($line['name'] ?? 'Line of Descendancy'),
                $line['page'] ?? null,
                is_array($line['color'] ?? null) ? $line['color'] : null
            );
            foreach ($line['generations'] ?? [] as $generation) {
                $writeEntry(
                    $pdf,
                    (string) ($generation['label'] ?? ''),
                    $generation['page'] ?? null,
                    null,
                    6.0
                );
            }
            $pdf->Ln(2);
        }
    } else {
        foreach ($data['generations'] ?? [] as $generation) {
            $writeEntry(
                $pdf,
                (string) ($generation['label'] ?? ''),
                $generation['page'] ?? null
            );
        }
        if (!empty($data['generations'])) {
            $pdf->Ln(2);
        }
    }

    if (!empty($data['appendix_page'])) {
        $writeEntry($pdf, 'Index of Names', $data['appendix_page']);
    }
}

function createAppendingPage(SimplePDF $pdf, array $entries, string $bookLabel, string $type): ?int
{
    $pdf->AddPage();
    $pageNumber = $pdf->GetPageNumber();

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->Cell(0, 12, 'Appending', 1, 'C');
    $pdf->Ln(14);

    if (empty($entries)) {
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->MultiCell(0, 5.0, 'No additional individuals were included in this report.');
        return $pageNumber;
    }

    $sorted = array_values($entries);
    usort($sorted, static function (array $a, array $b): int {
        $nameA = strtolower(trim($a['name'] ?? ''));
        $nameB = strtolower(trim($b['name'] ?? ''));
        return $nameA <=> $nameB;
    });

    $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
    $numberWidth = 18.0;
    $nameWidth = max(0.0, $usableWidth - $numberWidth);
    $lineHeight = 4.5;

    foreach ($sorted as $entry) {
        $name = trim((string) ($entry['name'] ?? 'Unknown'));
        $pageLabel = isset($entry['page']) ? (string) $entry['page'] : '';
        $color = $entry['color'] ?? null;
        if (is_array($color) && count($color) === 3) {
            $pdf->SetTextColor((int) $color[0], (int) $color[1], (int) $color[2]);
        } else {
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell($nameWidth, $lineHeight, $name, 0, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell($numberWidth, $lineHeight, $pageLabel, 1, 'R');

        $lineName = trim((string) ($entry['line'] ?? ''));
        if ($lineName !== '' && $type === 'descendants') {
            if (is_array($color) && count($color) === 3) {
                $pdf->SetTextColor((int) $color[0], (int) $color[1], (int) $color[2]);
            }
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->MultiCell(0, $lineHeight, 'Line: ' . $lineName);
            $pdf->SetTextColor(0, 0, 0);
        }

        $relationship = trim((string) ($entry['relationship'] ?? ''));
        if ($relationship !== '') {
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->MultiCell(0, $lineHeight, $relationship);
        }

        $pdf->Ln(1.5);
    }

    return $pageNumber;
}

function addDescendantLinesOverviewPage(SimplePDF $pdf, array $generationOne, array $lineMetadata, string $rootName): ?int
{
    if (empty($generationOne)) {
        return null;
    }

    $people = $generationOne;
    usort($people, [Utils::class, 'compareIndividualsByBirthThenName']);

    $pdf->AddPage();
    $pageNumber = $pdf->GetPageNumber();

    $title = $rootName !== '' ? ($rootName . ' Descendant Lines') : 'Descendant Lines';
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', 'B', 18);
    $pdf->Cell(0, 12, $title, 1, 'C');
    $pdf->Ln(14);

    foreach ($people as $person) {
        $lineIdRaw = $person['line_id'] ?? ($person['id'] ?? null);
        $lineKey = $lineIdRaw !== null ? (string) $lineIdRaw : null;
        $lineInfo = $lineKey !== null && isset($lineMetadata[$lineKey]) ? $lineMetadata[$lineKey] : null;
        $color = is_array($lineInfo['color'] ?? null) ? $lineInfo['color'] : null;
        if ($color) {
            $pdf->SetTextColor((int) $color[0], (int) $color[1], (int) $color[2]);
        } else {
            $pdf->SetTextColor(0, 0, 0);
        }
        $name = formatPersonName($person);
        if ($name === '') {
            $name = 'Unknown';
        }
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 7, $name, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        $relationship = formatRelationshipWithRoot($person['relationship'] ?? '', $rootName, 'descendants');
        if ($relationship === '' && $rootName !== '') {
            $relationship = 'Descendant of ' . $rootName;
        }
        if ($relationship !== '') {
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->MultiCell(0, 5.0, $relationship);
        }
        $pdf->Ln(3);
    }

    return $pageNumber;
}

function renderIndividualPage(SimplePDF $pdf, array $bundle, string $contextLabel = '', ?array $accentColor = null, string $lineLabel = ''): ?int
{
    $person = $bundle['person'];
    if (!$person) {
        return null;
    }
    $fullName = formatPersonName($person);
    $pdf->AddPage();
    $pageNumber = $pdf->GetPageNumber();

    $left = $pdf->GetLeftMargin();
    $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
    $topMargin = $pdf->GetTopMargin();

    $accent = null;
    if (is_array($accentColor) && count($accentColor) === 3) {
        $accent = [
            (int) $accentColor[0],
            (int) $accentColor[1],
            (int) $accentColor[2],
        ];
    }

    if ($accent) {
        [$r, $g, $b] = $accent;
        $blockHeight = 12.0;
        $blockY = max(0.0, $topMargin - ($blockHeight / 2));
        $pdf->FilledRect($left, $blockY, $usableWidth, $blockHeight, $accent);
        pdfSetY($pdf, $blockY + $blockHeight + 2);
        if ($lineLabel !== '') {
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetTextColor($r, $g, $b);
            $pdf->MultiCell(0, 6, $lineLabel, 'L');
            $pdf->Ln(2);
        }
        $pdf->SetFont('Helvetica', 'B', 22);
        $pdf->SetTextColor($r, $g, $b);
        $pdf->MultiCell(0, 10, $fullName, 'L');
        $pdf->Ln(2);
        $pdf->SetTextColor(0, 0, 0);
    } else {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', 'B', 22);
        $pdf->Cell(0, 14, $fullName, 1, 'C');
        $pdf->Ln(16);
    }

    if ($contextLabel !== '') {
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->MultiCell(0, 6, $contextLabel);
        $pdf->Ln(6);
    }

    if (!empty($bundle['key_image'])) {
        [$imageWidth, $imageHeight] = computeImageBox($bundle['key_image'], 80.0);
        $imageX = $left + max(0, ($usableWidth - $imageWidth) / 2);
        $currentY = $pdf->GetY();
        $pdf->Image($bundle['key_image'], $imageX, $currentY, $imageWidth, $imageHeight);
        pdfSetY($pdf, $currentY + $imageHeight + 6);
    }

    renderLifeLine($pdf, $person);

    $facts = summariseFacts($bundle['facts']);
    if (!empty($facts)) {
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Facts & Events', 1, 'L');
        $pdf->Ln(12);
        $leftMargin = $pdf->GetLeftMargin();
        foreach ($facts as $fact) {
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->MultiCell(0, 6, $fact['title']);
            if ($fact['detail'] !== '') {
                $pdf->SetFont('Helvetica', '', 10);
                pdfSetX($pdf, $leftMargin + 5);
                $pdf->MultiCell(0, 5.5, $fact['detail']);
            }
            $pdf->Ln(2);            
        }
    }
    $pdf->Ln(6);

    $stories = summariseStories($bundle['stories']);
    if (!empty($stories)) {
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Stories', 1, 'L');
        $pdf->Ln(12);
        $innerPadding = 3.0;        
        foreach ($stories as $story) {
            $boxWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
            $usableTextWidth = $boxWidth - ($innerPadding * 2);
            $pageBottom = $pdf->GetPageHeight() - $pdf->GetBottomMargin();

            $pdf->SetFont('Courier', 'B', 11);
            $titleText = (string) ($story['title'] ?? '');
            $titleHeight = $titleText !== '' ? estimateMultiCellHeight($pdf, $usableTextWidth, 5.5, $titleText) : 0.0;
            $pdf->SetFont('Courier', '', 10);
            $summaryText = (string) ($story['summary'] ?? '');
            $summaryHeight = $summaryText !== '' ? estimateMultiCellHeight($pdf, $usableTextWidth, 5.0, $summaryText) : 0.0;
            $requiredHeight = $innerPadding + $titleHeight + ($summaryHeight > 0 ? 2.0 + $summaryHeight : 0.0) + $innerPadding;

            if ($pdf->GetY() + $requiredHeight > $pageBottom) {
                $pdf->AddPage();
                $pageBottom = $pdf->GetPageHeight() - $pdf->GetBottomMargin();
            }

            $startX = $pdf->GetLeftMargin();
            $startY = $pdf->GetY();
            $pdf->SetXY($startX + $innerPadding, $startY + $innerPadding);

            if ($titleText !== '') {
                $pdf->SetFont('Courier', 'B', 11);
                $pdf->MultiCell($usableTextWidth, 5.5, $titleText);
            }

            if ($summaryText !== '') {
                if ($titleText !== '') {
                    $pdf->Ln(1.5);
                }
                $pdf->SetXY($startX + $innerPadding, $pdf->GetY());
                $pdf->SetFont('Courier', '', 10);
                $pdf->MultiCell($usableTextWidth, 5.0, $summaryText);
            }

            $contentBottom = $pdf->GetY();
            $boxHeight = max($requiredHeight, ($contentBottom - $startY) + $innerPadding);
            pdfDrawBorder($pdf, $startX, $startY, $boxWidth, $boxHeight);
            pdfSetY($pdf, $startY + $boxHeight + 4);       
        }
    }

    $photos = filterPhotos($bundle['photos'] ?? [], $bundle['key_image'] ?? null);
    if (!empty($photos)) {
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Photos', 1, 'L');
        $pdf->Ln(12);
        renderPhotoGrid($pdf, $photos);
    }

    return $pageNumber;
}

function renderLifeLine(SimplePDF $pdf, array $person): void
{
    $genderRaw = trim((string) ($person['gender'] ?? ''));
    $gender = $genderRaw !== '' ? ucfirst($genderRaw) : '—';
    $birth = formatDateFromParts($person['birth_year'] ?? null, $person['birth_month'] ?? null, $person['birth_date'] ?? null);
    $death = formatDateFromParts($person['death_year'] ?? null, $person['death_month'] ?? null, $person['death_date'] ?? null);
    $birthLabel = $birth !== '' ? $birth : '—';
    $deathLabel = $death !== '' ? $death : '—';

    if ($gender === '—' && $birthLabel === '—' && $deathLabel === '—') {
        return;
    }

    $line = sprintf('%-12s %s - %s', $gender, $birthLabel, $deathLabel);
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->MultiCell(0, 6, $line);
    $pdf->Ln(4);
}

function pdfSetY(SimplePDF $pdf, float $y): void
{
    if (is_callable([$pdf, 'SetY'])) {
        $pdf->SetY($y);
        return;
    }

    $left = is_callable([$pdf, 'GetLeftMargin']) ? $pdf->GetLeftMargin() : 0.0;
    $pdf->SetXY($left, $y);
}

function pdfSetX(SimplePDF $pdf, float $x): void
{
    $y = is_callable([$pdf, 'GetY']) ? $pdf->GetY() : 0.0;
    $pdf->SetXY($x, $y);
}

function pdfDrawBorder(SimplePDF $pdf, float $x, float $y, float $width, float $height, float $thickness = 0.5): void
{
    if ($width <= 0 || $height <= 0) {
        return;
    }

    $thickness = max(0.1, $thickness);
    $right = $x + $width;
    $bottom = $y + $height;

    // Draw the four edges using thin filled rectangles to emulate a stroked border.
    $pdf->FilledRect($x, $y, $width, $thickness, [0, 0, 0]); // Top edge
    $pdf->FilledRect($x, $bottom - $thickness, $width, $thickness, [0, 0, 0]); // Bottom edge
    $pdf->FilledRect($x, $y, $thickness, $height, [0, 0, 0]); // Left edge
    $pdf->FilledRect($right - $thickness, $y, $thickness, $height, [0, 0, 0]); // Right edge
}

function addGenerationSummaryPage(SimplePDF $pdf, int $generation, array $people, string $type, array &$parentCache, array $options = []): ?int
{
    if (empty($people)) {
        return null;
    }

    $label = $options['title'] ?? ('Generation ' . $generation);
    $lineMeta = $options['lineMeta'] ?? [];
    $titleColor = $options['color'] ?? null;
    $rootName = $options['rootName'] ?? '';
    $pdf->AddPage();
    $pageNumber = $pdf->GetPageNumber();
    if ($titleColor) {
        [$r, $g, $b] = $titleColor;
        $pdf->SetTextColor($r, $g, $b);
    } else {
        $pdf->SetTextColor(0, 0, 0);
    }
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->Cell(0, 12, $label, 1, 'C');
    $pdf->Ln(16);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', '', 12);
    foreach ($people as $person) {
        $name = formatPersonName($person);
        if ($name === '') {
            $name = 'Unknown';
        }
        $relationship = trim($person['relationship'] ?? '');
        if ($relationship === '') {
            $relationship = $type === 'descendants' ? 'Descendant' : 'Ancestor';
        }
        $relationship = formatRelationshipWithRoot($relationship, $rootName, $type);
        $personColor = null;
        if ($type === 'descendants') {
            $lineId = $person['line_id'] ?? null;
            if ($lineId !== null && isset($lineMeta[$lineId]['color'])) {
                $personColor = $lineMeta[$lineId]['color'];
            } elseif ($titleColor) {
                $personColor = $titleColor;
            }
        }
        if ($personColor) {
            [$r, $g, $b] = $personColor;
            $pdf->SetTextColor($r, $g, $b);
        }
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->MultiCell(0, 7, $name . ' — ' . $relationship);
        $pdf->SetTextColor(0, 0, 0);
        $parentsLine = formatParentsLine((int) ($person['id'] ?? 0), $parentCache);
        if ($parentsLine !== '') {
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->MultiCell(0, 6, 'Parents: ' . $parentsLine, 'L', 6.0);
        }
        $pdf->Ln(2);
    }
    return $pageNumber;
}

function buildDescendantLineMetadata(array $generationOne): array
{
    $palette = descendantLinePalette();
    $metadata = [];
    $index = 0;
    $sorted = $generationOne;
    usort($sorted, [Utils::class, 'compareIndividualsByBirthThenName']);
    foreach ($sorted as $person) {
        $lineId = $person['line_id'] ?? ($person['id'] ?? null);
        if ($lineId === null) {
            continue;
        }
        $key = (string) $lineId;
        if (isset($metadata[$key])) {
            continue;
        }
        $color = $palette[$index % count($palette)];
        $index++;
        $metadata[$key] = [
            'name' => formatPersonName($person),
            'color' => $color,
        ];
    }
    return $metadata;
}

function buildDescendantLines(array $generationData, array &$lineMetadata): array
{
    if (empty($generationData)) {
        return [];
    }

    $palette = descendantLinePalette();
    $colorIndex = count($lineMetadata);
    $lines = [];

    $generationOne = $generationData[1] ?? [];
    if (!empty($generationOne)) {
        usort($generationOne, [Utils::class, 'compareIndividualsByBirthThenName']);
        foreach ($generationOne as $person) {
            $lineIdRaw = $person['line_id'] ?? ($person['id'] ?? null);
            if ($lineIdRaw === null) {
                continue;
            }
            $lineId = (string) $lineIdRaw;
            if (!isset($lineMetadata[$lineId])) {
                $lineMetadata[$lineId] = [
                    'name' => formatPersonName($person),
                    'color' => $palette[$colorIndex % count($palette)],
                ];
                $colorIndex++;
            }
            if (!isset($lines[$lineId])) {
                $lines[$lineId] = [
                    'id' => $lineId,
                    'name' => $lineMetadata[$lineId]['name'],
                    'color' => $lineMetadata[$lineId]['color'],
                    'generations' => [],
                ];
            }
        }
    }

    foreach ($generationData as $generation => $people) {
        if (empty($people)) {
            continue;
        }
        $sorted = $people;
        usort($sorted, [Utils::class, 'compareIndividualsByBirthThenName']);
        foreach ($sorted as $person) {
            $lineIdRaw = $person['line_id'] ?? null;
            $lineId = $lineIdRaw !== null ? (string) $lineIdRaw : ('__' . ($person['id'] ?? uniqid('line', true)));
            if (!isset($lines[$lineId])) {
               $name = formatPersonName($person);
                if (!isset($lineMetadata[$lineId])) {
                    $lineMetadata[$lineId] = [
                        'name' => $name !== '' ? $name : 'Line ' . (count($lines) + 1),
                        'color' => $palette[$colorIndex % count($palette)],
                    ];
                    $colorIndex++;
                }
                $lines[$lineId] = [
                    'id' => $lineId,
                    'name' => $lineMetadata[$lineId]['name'],
                    'color' => $lineMetadata[$lineId]['color'],
                    'generations' => [],
                ];
            }
            $lines[$lineId]['generations'][$generation][] = $person;
        }
    }

    foreach ($lines as &$line) {
        ksort($line['generations']);
        foreach ($line['generations'] as &$members) {
            usort($members, [Utils::class, 'compareIndividualsByBirthThenName']);
        }
        unset($members);
    }
    unset($line);

    return array_values($lines);
}

function descendantLinePalette(): array
{
    return [
        [84, 99, 255],
        [38, 166, 154],
        [255, 112, 67],
        [156, 39, 176],
        [255, 193, 7],
        [63, 81, 181],
        [0, 150, 136],
        [244, 67, 54],
        [124, 179, 66],
        [0, 188, 212],
    ];
}

function addLineGenerationSummaryPage(SimplePDF $pdf, int $generation, array $people, array $lineInfo, array &$parentCache, string $rootName): ?int
{
    if (empty($people)) {
        return null;
    }

    $color = $lineInfo['color'] ?? [0, 0, 0];
    if (!is_array($color) || count($color) !== 3) {
        $color = [0, 0, 0];
    }
    $lineName = trim((string) ($lineInfo['name'] ?? 'Line of Descendancy'));

    $pdf->AddPage();
    $pageNumber = $pdf->GetPageNumber();
    $left = $pdf->GetLeftMargin();
    $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
    $blockHeight = 12.0;
    $blockY = max(0.0, $pdf->GetTopMargin() - ($blockHeight / 2));
    $pdf->FilledRect($left, $blockY, $usableWidth, $blockHeight, $color);
    pdfSetY($pdf, $blockY + $blockHeight + 2);

    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetTextColor((int) $color[0], (int) $color[1], (int) $color[2]);
    $pdf->MultiCell(0, 6, $lineName . "'s Line", 'L');
    $pdf->Ln(2);
    $pdf->SetFont('Helvetica', 'B', 18);
    $pdf->MultiCell(0, 8, 'Generation ' . $generation, 'L');
    $pdf->Ln(6);
    $pdf->SetTextColor(0, 0, 0);

    foreach ($people as $person) {
        $name = formatPersonName($person);
        $relationship = formatRelationshipWithRoot($person['relationship'] ?? '', $rootName, 'descendants');
        if ($relationship === '' && $rootName !== '') {
            $relationship = 'Descendant of ' . $rootName;
        }
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetTextColor((int) $color[0], (int) $color[1], (int) $color[2]);
        $pdf->MultiCell(0, 6, $name !== '' ? $name : 'Unknown', 'L');
        $pdf->SetTextColor(0, 0, 0);
        if ($relationship !== '') {
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->MultiCell(0, 5.5, $relationship, 'L', 6.0);
        }
        $parentsLine = formatParentsLine((int) ($person['id'] ?? 0), $parentCache);
        if ($parentsLine !== '') {
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->MultiCell(0, 5.0, 'Parents: ' . $parentsLine, 'L', 6.0);
        }
        $pdf->Ln(3);
    }

    return $pageNumber;
}

function formatParentsLine(int $personId, array &$cache): string
{
    if ($personId <= 0) {
        return 'Unknown';
    }
    if (isset($cache[$personId])) {
        return $cache[$personId];
    }
    $parents = Utils::getParents($personId);
    if (empty($parents)) {
        $cache[$personId] = 'Unknown';
        return $cache[$personId];
    }
    usort($parents, [Utils::class, 'compareIndividualsByBirthThenName']);
    $names = [];
    foreach ($parents as $parent) {
        $names[] = formatPersonName($parent);
    }
    $names = array_values(array_filter($names, static function ($name) {
        return $name !== '';
    }));
    if (empty($names)) {
        $cache[$personId] = 'Unknown';
        return $cache[$personId];
    }
    $cache[$personId] = implode(' & ', $names);
    return $cache[$personId];
}

function formatRelationshipWithRoot(string $relationship, string $rootName, string $type): string
{
    $relationship = trim($relationship);
    if ($relationship === '') {
        return '';
    }
    $formatted = ucwords(strtolower($relationship));
    $rootName = trim($rootName);
    if ($rootName === '') {
        return $formatted;
    }
    if (!preg_match('/\bof\b/i', $formatted)) {
        $formatted .= ' of ' . $rootName;
    }
    return $formatted;
}

function formatPersonName(?array $person): string
{
    if (empty($person)) {
        return '';
    }

    $first = sanitiseNameComponent($person['first_names'] ?? '', true);
    $last = sanitiseNameComponent($person['last_name'] ?? '', false);

    $parts = [];
    foreach ([$first, $last] as $component) {
        $component = trim($component);
        if ($component === '') {
            continue;
        }
        if ($component === '-' && in_array('-', $parts, true)) {
            continue;
        }
        $parts[] = $component;
    }

    if (empty($parts)) {
        return $first === '-' || $last === '-' ? '-' : '';
    }

    return trim(implode(' ', $parts));
}

function sanitiseNameComponent($value, bool $isFirstNames = false): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    if ($isFirstNames) {
        $text = str_replace('_', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
    }

    if ($text === '?' || preg_match('/^\?+$/', $text)) {
        return '-';
    }
    if (preg_match('/^\?[^?]*\?$/', $text)) {
        return '-';
    }
    if (preg_match('/^\[\?].*\[\?]$/', $text)) {
        return '-';
    }

    return $text;
}

function formatDateFromParts($year, $month, $day): string
{
    if (!$year) {
        return '';
    }
    $year = (int) $year;
    $month = $month ? (int) $month : 0;
    $day = $day ? (int) $day : 0;
    if ($month > 0 && $day > 0) {
        return date('j F Y', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $day)));
    }
    if ($month > 0) {
        return date('F Y', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    }
    return (string) $year;
}

function summariseFacts(array $items): array
{
    $facts = [];
    foreach ($items as $group) {
        if (($group['item_group_name'] ?? '') === 'Private') {
            continue;
        }

        $label = trim((string) ($group['item_group_name'] ?? 'Fact'));
        $segments = [];
        $notes = [];
        if (!empty($group['items'])) {
            foreach ($group['items'] as $detail) {
                if (!empty($detail['file_id'])) {
                    continue;
                }
                $type = trim((string) ($detail['detail_type'] ?? 'Detail'));
                $detailNotes = extractDetailNotes($detail);

                if (strcasecmp($type, 'Story') === 0 || strcasecmp($type, 'Description') === 0 || strcasecmp($type, 'Note') === 0) {
                    $noteValue = extractItemValue($detail);
                    if ($noteValue !== '') {
                        $notes[] = $noteValue;
                    }
                    $notes = array_merge($notes, $detailNotes);
                    continue;
                }

                $value = extractItemValue($detail);
                if ($value !== '') {
                    $segments[] = ($type !== '' ? $type : 'Detail') . ': ' . $value;
                }
                $notes = array_merge($notes, $detailNotes);
            }
        }

        $segments = array_values(array_filter($segments, static function ($segment) {
            return $segment !== '';
        }));
        $notes = array_values(array_filter(array_unique(array_map(static function ($note) {
            return trim($note);
        }, $notes)), static function ($note) {
            return $note !== '';
        }));

        if (empty($segments) && empty($notes)) {
            continue;
        }

        $title = $label !== '' ? $label : 'Fact';

        if (!empty($segments)) {
            $title .= ' — ' . implode('; ', $segments);
        }

        $facts[] = [
            'title' => trim($title),
            'detail' => !empty($notes) ? implode(PHP_EOL . PHP_EOL, $notes) : '',
        ];
    }

    return $facts;
}

function extractItemValue(array $detail): string
{
    $value = trim((string) ($detail['detail_value'] ?? ''));
    if ($value === '' && !empty($detail['items_item_value'])) {
        $value = trim((string) $detail['items_item_value']);
    }
    if (($detail['detail_type'] ?? '') === 'Date') {
        $value = formatDateValue($value);
    }
    if (in_array($detail['detail_type'] ?? '', ['Spouse', 'Person'], true) && !empty($detail['individual_name'])) {
        $value = trim((string) $detail['individual_name']);
    }
    $value = summariseText($value, 180);
    return $value;
}

function extractDetailNotes(array $detail): array
{
    $fields = [
        $detail['detail_story'] ?? '',
        $detail['detail_description'] ?? '',
        $detail['description'] ?? '',
        $detail['story'] ?? '',
        $detail['items_item_story'] ?? '',
        $detail['items_item_description'] ?? '',
        $detail['detail_memo'] ?? '',
    ];

    $notes = [];
    foreach ($fields as $field) {
        $text = summariseText((string) $field, 240);
        if ($text !== '') {
            $notes[] = $text;
        }
    }

    return $notes;
}

function formatDateValue(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return date('j F Y', strtotime($value));
    }
    if (preg_match('/^\d{4}-\d{2}$/', $value)) {
        return date('F Y', strtotime($value . '-01'));
    }
    return $value;
}

function summariseStories(array $stories): array
{
    $output = [];
    foreach ($stories as $story) {
        $title = trim((string) ($story['title'] ?? 'Story'));
        $summary = summariseText($story['content'] ?? '', 400);
        if ($title === '' && $summary === '') {
            continue;
        }
        if ($title === '') {
            $title = 'Story';
        }        
        $output[] = [
            'title' => $title,
            'summary' => $summary,
        ];
    }
    return $output;
}

function summariseText(string $text, int $maxLength = 250): string
{
    $decoded = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalised = trim(preg_replace('/\s+/', ' ', $decoded));
    if ($normalised === '') {
        return '';
    }
    if (mb_strlen($normalised) > $maxLength) {
        return rtrim(mb_substr($normalised, 0, $maxLength - 1)) . '…';
    }
    return $normalised;
}


function filterPhotos(array $photos, ?string $keyImagePath = null): array
{
   if (empty($photos)) {
        return [];
    }

    $filtered = [];
    $seen = [];
    $keyNormalised = $keyImagePath !== null ? normaliseMediaPath($keyImagePath) : null;

    foreach ($photos as $photo) {
        $path = trim((string) ($photo['file_path'] ?? ''));
        if ($path === '') {
            continue;
        }
        $normalised = normaliseMediaPath($path);
        if ($keyNormalised !== null && $keyNormalised !== '' && $normalised === $keyNormalised) {
            continue;
        }
        if ($normalised !== '' && isset($seen[$normalised])) {
            continue;
        }
        if ($normalised !== '') {
            $seen[$normalised] = true;
        }
        $filtered[] = $photo;
    }

    return $filtered;
}

function renderPhotoGrid(SimplePDF $pdf, array $photos, ?string $keyImagePath = null): void
{
    $filtered = filterPhotos($photos, $keyImagePath);

    if (empty($filtered)) {
        return;
    }

    $perRow = 2;
    $targetWidth = 70.0;
    $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
    if ($usableWidth < ($targetWidth * $perRow)) {
        $perRow = 1;
        $targetWidth = min($targetWidth, $usableWidth);
    }
    $spacing = $perRow > 1 ? ($usableWidth - ($targetWidth * $perRow)) / ($perRow - 1) : 0.0;

    $x = $pdf->GetLeftMargin();
    $y = $pdf->GetY();
    $rowHeight = 0.0;
    $baseFont = 10;
    $lineHeight = 4.5;
    $pageBottom = $pdf->GetPageHeight() - $pdf->GetBottomMargin();

    foreach ($filtered as $index => $photo) {
        if ($index > 0 && $index % $perRow === 0) {
            $y += $rowHeight + 12;
            $x = $pdf->GetLeftMargin();
            $rowHeight = 0.0;
        }

        [$width, $height] = computeImageBox($photo['file_path'], $targetWidth);
        $caption = summariseText((string) ($photo['file_description'] ?? ''), 120);
        $pdf->SetFont('Helvetica', '', $baseFont);
        $captionHeight = $caption !== '' ? estimateMultiCellHeight($pdf, $width, $lineHeight, $caption) + 4.0 : 0.0;
        $neededHeight = $height + $captionHeight;

        if ($y + max($rowHeight, $neededHeight) > $pageBottom) {
            $pdf->AddPage();
            $x = $pdf->GetLeftMargin();
            $y = $pdf->GetY();
            $rowHeight = 0.0;
        }

        $pdf->Image($photo['file_path'], $x, $y, $width, $height);
        $rowHeight = max($rowHeight, $height + ($captionHeight > 0 ? $captionHeight : 0));

        $caption = summariseText((string) ($photo['file_description'] ?? ''), 120);
        if ($caption !== '') {
            $pdf->SetFont('Helvetica', '', $baseFont);
            $pdf->SetXY($x, $y + $height + 4);
            $pdf->MultiCell($width, $lineHeight, $caption);
            $captionBottom = $pdf->GetY();
            $rowHeight = max($rowHeight, $captionBottom - $y);
            pdfSetY($pdf, $y);
        }

        $x += $width + $spacing;
    }

    pdfSetY($pdf, $y);
}

function estimateMultiCellHeight(SimplePDF $pdf, float $width, float $lineHeight, string $text): float
{
    $text = trim($text);
    if ($text === '') {
        return 0.0;
    }

    $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
    if ($width <= 0.0 || $width > $usableWidth) {
        $width = $usableWidth;
    }

    // Mirror SimplePDF::wrapLine() heuristics to approximate wrapping.
    $charWidth = max(0.1, $lineHeight * (0.5 / 1.35));
    $maxChars = max(1, (int) floor($width / $charWidth));

    $lineCount = 0;
    $lines = preg_split("/(\r\n|\r|\n)/", $text);
    if ($lines === false || empty($lines)) {
        $lines = [$text];
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            $lineCount++;
            continue;
        }
        $wrapped = wordwrap($line, $maxChars, "\n", true);
        $chunks = $wrapped === '' ? [''] : explode("\n", $wrapped);
        $lineCount += count($chunks);
    }

    return max(1, $lineCount) * $lineHeight;
}

function computeImageBox(string $path, float $targetWidth): array
{
    $real = resolveMediaPath($path);
    if (!$real || !is_file($real)) {
        return [$targetWidth, $targetWidth];
    }
    [$width, $height] = @getimagesize($real);
    if (!$width || !$height) {
        return [$targetWidth, $targetWidth];
    }
    $targetHeight = $targetWidth * ($height / $width);
    return [$targetWidth, $targetHeight];
}

function normaliseMediaPath(string $path): string
{
    $resolved = resolveMediaPath($path);
    if ($resolved !== null) {
        return $resolved;
    }

    $sanitised = str_replace(['\\', '//'], '/', trim($path));
    return ltrim($sanitised, './');
}

function resolveMediaPath(string $path): ?string
{
    $path = trim($path);
    if ($path === '') {
        return null;
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return null;
    }
    $candidates = [
        __DIR__ . '/../' . ltrim($path, '/'),
        __DIR__ . '/../' . $path,
        __DIR__ . '/../..' . '/' . ltrim($path, '/'),
        $path,
    ];
    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        if ($real !== false && is_file($real)) {
            return $real;
        }
    }
    return null;
}

function buildFileName(array $person, string $bookLabel): string
{
    $name = formatPersonName($person);
    if ($name === '' || $name === '-') {
        $name = 'individual';
    }
    $safe = preg_replace('/[^A-Za-z0-9_\-]+/', '_', str_replace(' ', '_', $name));
    return trim((string) $safe, '_') . '_' . $bookLabel . '_Book.pdf';
}