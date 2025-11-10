<?php
require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../system/nodavuvale_database.php';
require_once __DIR__ . '/../system/nodavuvale_auth.php';
require_once __DIR__ . '/../system/nodavuvale_web.php';
require_once __DIR__ . '/../system/nodavuvale_utils.php';
require_once __DIR__ . '/../vendor/simplepdf/SimplePDF.php';

Web::startSession();

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
// Insert a blank page between the cover and the index
$pdf->AddPage();
$simpleIndexPageNumber = createSimpleIndexPage($pdf, $bookLabel, $type);
// Enable page numbers starting after the index page (first numbered page = 1)
$pdf->EnablePageNumbers();
$pdf->SetPageNumberingStart($simpleIndexPageNumber + 1, 1);
$rootChapter = renderIndividualPage($pdf, $rootBundle, 'Overview', null, '', [
    'line_colors' => [],
    'direct_parent_map' => [],
    'render_guard' => [],
]);
$rootPageNumber = $rootChapter['page'] ?? null;
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
if (!empty($rootChapter['spouses'])) {
    foreach ($rootChapter['spouses'] as $spouseChapter) {
        $spouseId = (int) ($spouseChapter['id'] ?? 0);
        $spousePage = $spouseChapter['page'] ?? null;
        if ($spouseId <= 0 || $spousePage === null) {
            continue;
        }
        if (isset($indexEntries[$spouseId])) {
            continue;
        }
        $spouseName = trim((string) ($spouseChapter['name'] ?? 'Spouse'));
        $indexEntries[$spouseId] = [
            'name' => $spouseName !== '' ? $spouseName : 'Spouse',
            'page' => $spousePage,
            'color' => null,
            'line' => '',
            'relationship' => 'Spouse of ' . ($rootName !== '' ? $rootName : 'Subject'),
        ];
    }
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
    // Build a quick map of direct parents for descendants to help ordering and chains.
    $directParentMap = buildDirectParentMap($generationData);
    $lineColorMap = buildDescendantColorMap($lineMetadata, $directParentMap);

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
            // Order members by parent, then date of birth
            $members = sortMembersByParentThenBirth($members, $directParentMap);

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
                // From 3rd generation onwards, include ancestor chain beneath the line heading
                $lineLabelForPerson = $lineLabel;
                if ((int) $generation >= 3) {
                    $chain = buildAncestorChainForDescendant((int) ($person['id'] ?? 0), (string) ($line['id'] ?? ''), $directParentMap, $rootBundleCache);
                    if ($chain !== '') {
                        $lineLabelForPerson .= "\n" . $chain;
                    }
                }
                $chapter = renderIndividualPage(
                    $pdf,
                    $bundle,
                    $relationship,
                    $accentColor,
                    $lineLabelForPerson,
                    [
                        'line_colors' => $lineColorMap,
                        'direct_parent_map' => $directParentMap,
                        'render_guard' => [],
                    ]
                );
                $pageNumber = $chapter['page'] ?? null;
                $fullName = formatPersonName($bundle['person']);
                if ($pageNumber !== null && !empty($bundle['person']['id'])) {
                    $personId = (int) $bundle['person']['id'];
                    $indexEntries[$personId] = [
                        'name' => $fullName,
                        'page' => $pageNumber,
                        'color' => $accentColor,
                        'line' => $line['name'] ?? '',
                        'relationship' => $relationship,
                    ];
                }
                foreach ($chapter['spouses'] ?? [] as $spouseChapter) {
                    $spouseId = (int) ($spouseChapter['id'] ?? 0);
                    $spousePage = $spouseChapter['page'] ?? null;
                    if ($spouseId <= 0 || $spousePage === null) {
                        continue;
                    }
                    if (isset($indexEntries[$spouseId])) {
                        continue;
                    }
                    $spouseName = trim((string) ($spouseChapter['name'] ?? 'Spouse'));
                    $indexEntries[$spouseId] = [
                        'name' => $spouseName !== '' ? $spouseName : 'Spouse',
                        'page' => $spousePage,
                        'color' => $accentColor,
                        'line' => $line['name'] ?? '',
                        'relationship' => 'Spouse of ' . ($fullName !== '' ? $fullName : 'descendant'),
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
                $chapter = renderIndividualPage(
                    $pdf,
                    $bundle,
                    $relationship,
                    null,
                    '',
                    [
                        'line_colors' => [],
                        'direct_parent_map' => [],
                        'render_guard' => [],
                    ]
                );
                $pageNumber = $chapter['page'] ?? null;
                $fullName = formatPersonName($bundle['person']);
                if ($pageNumber !== null && !empty($bundle['person']['id'])) {
                    $personId = (int) $bundle['person']['id'];
                    $indexEntries[$personId] = [
                        'name' => $fullName,
                        'page' => $pageNumber,
                        'color' => null,
                        'line' => '',
                        'relationship' => $relationship,
                    ];
                }
                foreach ($chapter['spouses'] ?? [] as $spouseChapter) {
                    $spouseId = (int) ($spouseChapter['id'] ?? 0);
                    $spousePage = $spouseChapter['page'] ?? null;
                    if ($spouseId <= 0 || $spousePage === null) {
                        continue;
                    }
                    if (isset($indexEntries[$spouseId])) {
                        continue;
                    }
                    $spouseName = trim((string) ($spouseChapter['name'] ?? 'Spouse'));
                    $indexEntries[$spouseId] = [
                        'name' => $spouseName !== '' ? $spouseName : 'Spouse',
                        'page' => $spousePage,
                        'color' => null,
                        'line' => '',
                        'relationship' => 'Spouse of ' . ($fullName !== '' ? $fullName : 'ancestor'),
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
// Adjust displayed page numbers to start after the index
$pageOffset = $simpleIndexPageNumber; // first numbered page is offset + 1
populateSimpleIndexPage($pdf, $simpleIndexPageNumber, $simpleIndex, $bookLabel, $type, $pageOffset);
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
        'all_files' => Utils::getFiles($individualId),
        'key_image' => Utils::getKeyImage($individualId),
    ];

    $cache[$individualId] = $bundle;
    return $bundle;
}

function createCoverPage(SimplePDF $pdf, array $bundle, string $bookLabel, string $siteName, string $type): void
{
    $person = $bundle['person'];
    $fullName = formatPersonName($person);
    $displayName = $fullName !== '' ? $fullName : 'Unnamed Individual';
    $pdf->AddPage();
    $pdf->SetTextColor(0, 0, 0);
    $descriptorLabel = $type === 'descendants' ? 'Descendants of' : 'Ancestors of';
    $titleText = $descriptorLabel . "\n" . $displayName;
    $pdf->SetFont('Helvetica', 'B', 26);
    $pdf->MultiCell(0, 14, $titleText, 'C');
    $pdf->Ln(10);

    $imagePath = preparePdfImagePath($bundle['key_image'] ?? null);
    if ($imagePath !== null) {
        $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
        $maxKeyWidth = max(10.0, $usableWidth * 0.2);
        [$imageWidth, $imageHeight] = computeImageBox($imagePath, min(110.0, $maxKeyWidth));
        $framePadding = 4.0;
        $frameWidth = $imageWidth + ($framePadding * 2);
        $imageX = $pdf->GetLeftMargin() + max(0.0, ($usableWidth - $frameWidth) / 2.0);
        $currentY = $pdf->GetY();
        $frameColor = [45, 45, 45];
        drawFramedImage($pdf, $imagePath, $imageWidth, $imageHeight, $imageX, $currentY, $frameColor, $framePadding);
        pdfSetY($pdf, $currentY + $imageHeight + ($framePadding * 2) + 18);
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
    $title = trim($bookLabel) !== '' ? ($bookLabel . ' Index') : 'Index';
    $pdf->Cell(0, 14, $title, 1, 'C');
    $pdf->Ln(16);

    return $pageNumber;
}

function populateSimpleIndexPage(SimplePDF $pdf, int $pageNumber, array $data, string $bookLabel, string $type, int $pageOffset = 0): void
{
    if ($pageNumber < 1) {
        return;
    }

    $pdf->UsePage($pageNumber);
    $pdf->SetTextColor(0, 0, 0);
    // Ensure we start below the heading drawn when the page was created
    $pdf->SetY($pdf->GetTopMargin() + 30.0);
    $pdf->SetFont('Helvetica', '', 12);

    $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
    $pageWidth = 24.0;
    $labelWidth = max(0.0, $usableWidth - $pageWidth);

    $writeEntry = static function (SimplePDF $pdf, string $label, ?int $page, ?array $color = null, float $indent = 0.0, bool $bold = true, float $fontSize = 12.0) use ($labelWidth, $pageWidth, $pageOffset): void {
        if ($label === '') {
            return;
        }
        if ($color !== null && count($color) === 3) {
            $pdf->SetTextColor((int) $color[0], (int) $color[1], (int) $color[2]);
        } else {
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->SetFont('Helvetica', $bold ? 'B' : '', max(8.0, $fontSize));
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
        $displayPage = '';
        if ($page !== null) {
            $displayNum = $page - (int) $pageOffset;
            if ($displayNum >= 1) {
                $displayPage = (string) $displayNum;
            }
        }
        $pdf->Cell($pageWidth, 6, $displayPage, 1, 'R');
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
                $genLabel = trim((string) ($generation['label'] ?? ''));
                // Skip "Generation 1" for descendancy lines; it is implied by the line name
                if ($genLabel !== '' && preg_match('/^generation\s*1\b/i', $genLabel)) {
                    continue;
                }
                $writeEntry(
                    $pdf,
                    $genLabel,
                    $generation['page'] ?? null,
                    null,
                    6.0,
                    false,
                    11.0
                );
            }
            $pdf->Ln(2);
        }
    } else {
        foreach ($data['generations'] ?? [] as $generation) {
            $writeEntry(
                $pdf,
                (string) ($generation['label'] ?? ''),
                $generation['page'] ?? null,
                null,
                0.0,
                false,
                11.0
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
    $pdf->Cell(0, 12, 'Names Index', 1, 'C');
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

function renderIndividualPage(SimplePDF $pdf, array $bundle, string $contextLabel = '', ?array $accentColor = null, string $lineLabel = '', array $options = []): array
{
    global $rootBundleCache;

    $person = $bundle['person'] ?? null;
    if (!$person) {
        return ['page' => null, 'spouses' => []];
    }
    $personId = (int) ($person['id'] ?? 0);
    if ($personId <= 0) {
        return ['page' => null, 'spouses' => []];
    }

    $activeTrail = $options['render_guard'] ?? [];
    if (in_array($personId, $activeTrail, true)) {
        return ['page' => null, 'spouses' => []];
    }
    $activeTrail[] = $personId;
    $options['render_guard'] = $activeTrail;

    $lineColors = $options['line_colors'] ?? [];
    $directParentMap = $options['direct_parent_map'] ?? [];

    $pdf->AddPage();
    $summaryPage = $pdf->GetPageNumber();

    $fullName = formatPersonName($person);
    $fullName = $fullName !== '' ? $fullName : 'Unnamed Individual';
    $lifespan = formatLifeSpanLine($person);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', 'B', 22);
    $pdf->MultiCell(0, 10, $fullName, 'L');

    if ($contextLabel !== '' && strcasecmp($contextLabel, 'Overview') !== 0) {
        $pdf->SetFont('Helvetica', 'I', 12);
        $pdf->MultiCell(0, 6, $contextLabel, 'L');
    }
    if ($lineLabel !== '') {
        $pdf->SetFont('Helvetica', '', 10);
        if (is_array($accentColor) && count($accentColor) === 3) {
            $pdf->SetTextColor((int) $accentColor[0], (int) $accentColor[1], (int) $accentColor[2]);
        }
        $pdf->MultiCell(0, 5.5, $lineLabel, 'L');
        $pdf->SetTextColor(0, 0, 0);
    }

    if ($lifespan !== '') {
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->MultiCell(0, 6, $lifespan, 'L');
    }

    $photoPath = preparePdfImagePath($bundle['key_image'] ?? null);
    if ($photoPath === null && !empty($bundle['photos'])) {
        $firstPhoto = reset($bundle['photos']);
        $photoPath = preparePdfImagePath($firstPhoto['file_path'] ?? null);
    }
    if ($photoPath !== null) {
        $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
        $maxHeight = max(
            20.0,
            ($pdf->GetPageHeight() - $pdf->GetTopMargin() - $pdf->GetBottomMargin()) * 0.2
        );
        $maxWidth = max(40.0, $usableWidth * 0.75);
        [$imgW, $imgH] = computeImageBoxWithMaxes($photoPath, $maxWidth, $maxHeight);
        $framePadding = 3.5;
        $frameWidth = $imgW + ($framePadding * 2);
        $startX = $pdf->GetLeftMargin() + max(0, ($usableWidth - $frameWidth) / 2);
        $currentY = $pdf->GetY() + 4;
        $frameColor = determineFrameColor($accentColor);
        drawFramedImage($pdf, $photoPath, $imgW, $imgH, $startX, $currentY, $frameColor, $framePadding);
        pdfSetY($pdf, $currentY + $imgH + ($framePadding * 2) + 6);
    } else {
        $pdf->Ln(4);
    }

    $parents = Utils::getParents($personId);
    renderParentsGrid($pdf, $parents);

    $children = Utils::getChildren($personId);
    $spouses = Utils::getSpouses($personId);
    $childrenBySpouse = groupChildrenByPartner($children);
    $unknownChildren = $childrenBySpouse['unknown'] ?? [];
    if (!empty($unknownChildren)) {
        $placeholderSpouse = [
            'id' => 0,
            'first_names' => 'Unknown other parent',
            'last_name' => '',
            'keyimagepath' => null,
            '__placeholder' => true,
        ];
        $spouses[] = $placeholderSpouse;
        $childrenBySpouse['by_spouse'][0] = $unknownChildren;
        $childrenBySpouse['unknown'] = [];
    }

    renderSpouseSection(
        $pdf,
        $person,
        $spouses,
        $childrenBySpouse,
        [
            'fact_items' => $bundle['facts'] ?? [],
            'line_colors' => $lineColors,
            'direct_parent_map' => $directParentMap,
        ]
    );

    $timeline = buildTimelineNarrative($bundle);
    $timelinePage = renderTimelineChapter($pdf, $fullName, $timeline);

    $spouseChapters = [];
    if (empty($options['suppress_spouse_pages'])) {
        foreach ($spouses as $spouse) {
            $spouseId = (int) ($spouse['id'] ?? 0);
            if ($spouseId <= 0) {
                continue;
            }
            if (in_array($spouseId, $options['render_guard'], true)) {
                continue;
            }
            $spouseBundle = fetchIndividualBundle($spouseId, $rootBundleCache);
            $spouseResult = renderIndividualPage(
                $pdf,
                $spouseBundle,
                'Spouse of ' . $fullName,
                $accentColor,
                '',
                array_merge($options, [
                    'suppress_spouse_pages' => true,
                ])
            );
            if (!empty($spouseResult['page'])) {
                $spouseChapters[] = [
                    'id' => $spouseId,
                    'page' => $spouseResult['page'],
                    'name' => formatPersonName($spouseBundle['person'] ?? []),
                ];
            }
        }
    }

    return [
        'page' => $summaryPage,
        'timeline_page' => $timelinePage,
        'spouses' => $spouseChapters,
    ];
}

function formatLifeSpanLine(array $person): string
{
    $birth = formatDateFromParts($person['birth_year'] ?? null, $person['birth_month'] ?? null, $person['birth_date'] ?? null);
    $death = formatDateFromParts($person['death_year'] ?? null, $person['death_month'] ?? null, $person['death_date'] ?? null);
    if ($birth === '' && $death === '') {
        return '';
    }
    if ($birth !== '' && $death !== '') {
        return $birth . ' - ' . $death;
    }
    if ($birth !== '') {
        return 'Born ' . $birth;
    }
    return 'Died ' . $death;
}

function formatLifeSpanYears(array $person): string
{
    $birthYear = trim((string) ($person['birth_year'] ?? ''));
    $deathYear = trim((string) ($person['death_year'] ?? ''));
    if ($birthYear === '' && $deathYear === '') {
        return '';
    }
    if ($birthYear !== '' && $deathYear !== '') {
        return $birthYear . ' - ' . $deathYear;
    }
    if ($birthYear !== '') {
        return 'b. ' . $birthYear;
    }
    return 'd. ' . $deathYear;
}

function renderParentsGrid(SimplePDF $pdf, array $parents): void
{
    $pdf->Ln(6);
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Parents', 0, 1, 'L');
    $pdf->Ln(8);
    if (empty($parents)) {
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->MultiCell(0, 5.5, 'No parents recorded.', 'L');
        return;
    }

    $father = null;
    $mother = null;
    $others = [];
    foreach ($parents as $parent) {
        $gender = strtolower(trim((string) ($parent['gender'] ?? '')));
        if ($gender === 'male' && $father === null) {
            $father = $parent;
            continue;
        }
        if ($gender === 'female' && $mother === null) {
            $mother = $parent;
            continue;
        }
        $others[] = $parent;
    }
    if ($father === null && !empty($others)) {
        $father = array_shift($others);
    }
    if ($mother === null && !empty($others)) {
        $mother = array_shift($others);
    }

    $candidates = array_values(array_filter([$father, $mother], static fn($p) => $p !== null));
    if (empty($candidates)) {
        $candidates = array_slice($parents, 0, min(2, count($parents)));
    }

    $left = $pdf->GetLeftMargin();
    $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
    $gap = 8.0;
    $columnWidth = ($usableWidth - $gap) / max(1, count($candidates));
    $startY = $pdf->GetY();
    $maxY = $startY;

    foreach ($candidates as $index => $parent) {
        $x = $left + $index * ($columnWidth + $gap);
        $pdf->SetXY($x, $startY);
        $endY = renderParentCard($pdf, $parent, $columnWidth);
        $maxY = max($maxY, $endY);
    }

    $pdf->SetY($maxY + 4.0);
}

function renderParentCard(SimplePDF $pdf, ?array $parent, float $columnWidth): float
{
    $startX = $pdf->GetX();
    $startY = $pdf->GetY();
    $maxHeight = 12.0;
    $maxWidth = 16.0;
    $imagePath = $parent ? resolveProfileImagePath($parent['keyimagepath'] ?? null) : resolveProfileImagePath(null);
    [$imageWidth, $imageHeight] = computeImageBoxWithMaxes($imagePath, $maxWidth, $maxHeight);
    $pdf->Image($imagePath, $startX, $startY, $imageWidth, $imageHeight);

    $textX = $startX + $imageWidth + 4.0;
    $pdf->SetXY($textX, $startY);
    $usableWidth = max(0.0, $columnWidth - ($imageWidth + 4.0));
    $pdf->SetFont('Helvetica', 'B', 11);
    $name = $parent ? formatPersonName($parent) : 'Unknown';
    pdfSetX($pdf, $textX);
    $pdf->MultiCell($usableWidth, 5.0, $name, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $life = $parent ? formatLifeSpanLine($parent) : '';
    if ($life !== '') {
        pdfSetX($pdf, $textX);
        $pdf->MultiCell($usableWidth, 4.5, $life, 'L');
    }

    $contentBottom = $pdf->GetY();
    return max($startY + $imageHeight, $contentBottom);
}

function groupChildrenByPartner(array $children): array
{
    $groups = [
        'by_spouse' => [],
        'unknown' => [],
    ];
    foreach ($children as $child) {
        $otherParents = $child['other_parents'] ?? [];
        $assigned = false;
        foreach ($otherParents as $other) {
            $spouseId = (int) ($other['id'] ?? 0);
            if ($spouseId > 0) {
                $groups['by_spouse'][$spouseId][] = $child;
                $assigned = true;
            }
        }
        if (!$assigned) {
            $groups['unknown'][] = $child;
        }
    }
    return $groups;
}

function renderSpouseSection(SimplePDF $pdf, array $primary, array $spouses, array $childrenBySpouse, array $options): void
{
    $pdf->Ln(6);
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Family', 0, 1, 'L');
    $pdf->Ln(6);
    if (empty($spouses)) {
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->MultiCell(0, 5.5, 'No spouses recorded.', 'L');
        return;
    }

    $factGroups = $options['fact_items'] ?? [];
    $lineColors = $options['line_colors'] ?? [];
    $directParentMap = $options['direct_parent_map'] ?? [];

    foreach ($spouses as $spouse) {
        $pdf->Ln(3);
        $endY = renderSpouseCard(
            $pdf,
            $spouse,
            $childrenBySpouse['by_spouse'][(int) ($spouse['id'] ?? 0)] ?? [],
            $factGroups,
            $lineColors,
            $directParentMap
        );
        $pdf->SetY($endY + 2.0);
    }
}

function renderSpouseCard(
    SimplePDF $pdf,
    array $spouse,
    array $children,
    array $factGroups,
    array $lineColors,
    array $directParentMap
): float {
    $isPlaceholder = !empty($spouse['__placeholder']);
    $left = $pdf->GetLeftMargin();
    $startY = $pdf->GetY();
    $maxHeight = 12.0;
    $maxWidth = 16.0;
    $imagePath = resolveProfileImagePath($spouse['keyimagepath'] ?? null);
    if ($imagePath !== null) {
        [$imageWidth, $imageHeight] = computeImageBoxWithMaxes($imagePath, $maxWidth, $maxHeight);
        $pdf->Image($imagePath, $left, $startY, $imageWidth, $imageHeight);
    } else {
        $imageWidth = $maxWidth;
        $imageHeight = $maxHeight;
        $pdf->FilledRect($left, $startY, $imageWidth, $imageHeight, [220, 220, 220]);
    }

    $textX = $left + $imageWidth + 4.0;
    $usableWidth = $pdf->GetPageWidth() - $pdf->GetRightMargin() - $textX;
    $pdf->SetXY($textX, $startY);
    $pdf->SetFont('Helvetica', 'B', 12);
    $spouseName = formatPersonName($spouse);
    $spouseLabel = 'Spouse: ' . ($spouseName !== '' ? $spouseName : 'Unknown Spouse');
    if ($isPlaceholder) {
        $spouseLabel = $spouseName !== '' ? $spouseName : 'Unknown other parent';
    }
    pdfSetX($pdf, $textX);
    $pdf->MultiCell($usableWidth, 5.5, $spouseLabel, 'L');
    if (!$isPlaceholder) {
        $pdf->SetFont('Helvetica', '', 10);
        $lifeLine = formatLifeSpanLine($spouse);
        if ($lifeLine !== '') {
            pdfSetX($pdf, $textX);
            $pdf->MultiCell($usableWidth, 5.0, $lifeLine, 'L');
        }
        $marriageDate = resolveMarriageDateForSpouse($factGroups, (int) ($spouse['id'] ?? 0));
        if ($marriageDate) {
            pdfSetX($pdf, $textX);
            $pdf->MultiCell($usableWidth, 5.0, 'Married: ' . $marriageDate, 'L');
        }
    } else {
        $pdf->SetFont('Helvetica', '', 10);
    }

    if (!empty($children)) {
        $pdf->Ln(1.5);
        $pdf->SetFont('Helvetica', 'B', 11);
        pdfSetX($pdf, $textX);
        $pdf->MultiCell($usableWidth, 5.0, 'Children with this spouse', 'L');
        $pdf->SetFont('Helvetica', '', 9);
        foreach ($children as $child) {
            $childName = formatPersonName($child);
            $line = $childName !== '' ? $childName : 'Unnamed child';
            $life = formatLifeSpanYears($child);
            if ($life !== '') {
                $line .= ' (' . $life . ')';
            }
            $color = resolveChildLineColor((int) ($child['id'] ?? 0), $lineColors, $directParentMap);
            if ($color !== null) {
                $pdf->SetTextColor((int) $color[0], (int) $color[1], (int) $color[2]);
            }
            pdfSetX($pdf, $textX);
                $pdf->MultiCell($usableWidth, 4.0, '- ' . $line, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }
    } else {
        $pdf->Ln(1.5);
        $pdf->SetFont('Helvetica', '', 9);
        pdfSetX($pdf, $textX);
        $pdf->MultiCell($usableWidth, 4.0, 'No recorded children for this relationship.', 'L');
    }

    return max($pdf->GetY(), $startY + $imageHeight);
}

function resolveMarriageDateForSpouse(array $factGroups, int $spouseId): ?string
{
    if ($spouseId <= 0) {
        return null;
    }
    foreach ($factGroups as $group) {
        if (strcasecmp((string) ($group['item_group_name'] ?? ''), 'Marriage') !== 0) {
            continue;
        }
        $referencesSpouse = false;
        foreach ($group['items'] ?? [] as $item) {
            if ((int) ($item['individual_id'] ?? 0) === $spouseId) {
                $referencesSpouse = true;
                break;
            }
        }
        if (!$referencesSpouse) {
            continue;
        }
        foreach ($group['items'] ?? [] as $item) {
            if (strcasecmp((string) ($item['detail_type'] ?? ''), 'Date') === 0) {
                return formatDateValue((string) ($item['detail_value'] ?? ''));
            }
        }
    }
    return null;
}

function resolveChildLineColor(int $childId, array $lineColors, array $directParentMap): ?array
{
    if (isset($lineColors[$childId])) {
        return $lineColors[$childId];
    }
    $guard = 0;
    $current = $childId;
    while (++$guard < 100) {
        $parentId = $directParentMap[$current] ?? null;
        if (!$parentId) {
            break;
        }
        if (isset($lineColors[$parentId])) {
            return $lineColors[$parentId];
        }
        $current = $parentId;
    }
    return null;
}

function buildTimelineNarrative(array $bundle): array
{
    $events = [];
    $undated = [];
    $person = $bundle['person'] ?? [];
    $name = formatPersonName($person);
    $keyImagePath = trim((string) ($bundle['key_image'] ?? ''));
    $keyImageNormalised = $keyImagePath !== '' ? normaliseMediaPath($keyImagePath) : null;
    $deathSort = null;
    $lifeCutoffSort = null;

    $birthDate = parseTimelineDate(formatIsoDateFromParts($person['birth_year'] ?? null, $person['birth_month'] ?? null, $person['birth_date'] ?? null));
    if ($birthDate !== null) {
        $events[] = [
            'sort' => $birthDate['sort'],
            'label' => $birthDate['label'],
            'title' => 'Birth of ' . ($name !== '' ? $name : 'subject'),
            'body' => '',
            'type' => 'milestone',
            'image' => null,
        ];
    }

    $deathDate = parseTimelineDate(formatIsoDateFromParts($person['death_year'] ?? null, $person['death_month'] ?? null, $person['death_date'] ?? null));
    if ($deathDate !== null) {
        $deathSort = $deathDate['sort'];
        $events[] = [
            'sort' => $deathDate['sort'],
            'label' => $deathDate['label'],
            'title' => 'Death of ' . ($name !== '' ? $name : 'subject'),
            'body' => '',
            'type' => 'milestone',
            'image' => null,
        ];
    }

    foreach ($bundle['facts'] ?? [] as $group) {
        $groupTitle = (string) ($group['item_group_name'] ?? '');
        if (stripos($groupTitle, 'key image') !== false) {
            continue;
        }
        $dateSource = $group['sortDate'] ?? extractTimelineDateFromGroup($group);
        $date = parseTimelineDate($dateSource);
        $summary = summariseFacts([$group]);
        $entry = [
            'sort' => $date['sort'] ?? null,
            'label' => $date['label'] ?? 'Undated fact',
            'title' => $group['item_group_name'] ?? 'Fact',
            'body' => $summary[0]['detail'] ?? '',
            'type' => stripos((string) ($group['item_group_name'] ?? ''), 'historical') !== false ? 'historical' : 'fact',
            'image' => null,
        ];
        $fileType = strtolower((string) ($group['file_type'] ?? ''));
        if ($fileType === 'image') {
            $rawPath = trim((string) ($group['file_path'] ?? ''));
            $normalised = $rawPath !== '' ? normaliseMediaPath($rawPath) : null;
            if ($keyImageNormalised !== null && $normalised === $keyImageNormalised) {
                $rawPath = '';
            }
            $imagePath = $rawPath !== '' ? preparePdfImagePath($rawPath) : null;
            if ($imagePath !== null) {
                $entry['image'] = $imagePath;
                $entry['caption'] = summariseText((string) ($group['file_description'] ?? $entry['title']), 160);
            }
        }
        if ($entry['image'] === null) {
            $attachment = extractTimelineImageFromGroup($group, $keyImageNormalised);
            if ($attachment !== null) {
                $entry['image'] = $attachment['path'];
                $entry['caption'] = $attachment['caption'];
            }
        }
        if ($entry['sort'] !== null && isBurialEventTitle($entry['title'] ?? '')) {
            if ($lifeCutoffSort === null || strcmp($entry['sort'], $lifeCutoffSort) > 0) {
                $lifeCutoffSort = $entry['sort'];
            }
        }
        if (timelineEventIsKeyImage($entry)) {
            continue;
        }
        if ($entry['sort'] === null) {
            $undated[] = $entry;
        } else {
            $events[] = $entry;
        }
    }

    foreach ($bundle['stories'] ?? [] as $story) {
        $date = parseTimelineDate($story['event_date'] ?? $story['created_at'] ?? null);
        $entry = [
            'sort' => $date['sort'] ?? null,
            'label' => $date['label'] ?? 'Undated story',
            'title' => $story['title'] ?? 'Story',
            'body' => normaliseStoryContent((string) ($story['content'] ?? '')),
            'type' => !empty($story['is_historical_event']) ? 'historical' : 'story',
            'image' => null,
            'author' => trim((string) (($story['user_name'] ?? '') !== '' ? $story['user_name'] : (($story['first_name'] ?? '') . ' ' . ($story['last_name'] ?? '')))),
        ];
        if (timelineEventIsKeyImage($entry)) {
            continue;
        }
        if ($entry['sort'] === null) {
            $undated[] = $entry;
        } else {
            $events[] = $entry;
        }
    }

    $photos = filterPhotos($bundle['photos'] ?? [], $bundle['key_image'] ?? null);
    foreach ($photos as $photo) {
        $date = parseTimelineDate($photo['link_date'] ?? null);
        if ($date === null) {
            continue;
        }
        $photoEvent = [
            'sort' => $date['sort'],
            'label' => $date['label'],
            'title' => 'Photo',
            'body' => '',
            'caption' => summariseText((string) ($photo['file_description'] ?? ''), 160),
            'type' => 'photo',
            'image' => preparePdfImagePath($photo['file_path'] ?? null),
        ];
        if ($photoEvent['image'] === null || timelineEventIsKeyImage($photoEvent)) {
            continue;
        }
        $events[] = $photoEvent;
    }

    foreach ($bundle['all_files'] ?? [] as $file) {
        if (strcasecmp((string) ($file['file_type'] ?? ''), 'image') === 0) {
            continue;
        }
        $date = parseTimelineDate($file['link_date'] ?? null);
        if ($date === null) {
            continue;
        }
        $rawPath = trim((string) ($file['file_path'] ?? ''));
        if ($rawPath !== '' && $keyImageNormalised !== null && normaliseMediaPath($rawPath) === $keyImageNormalised) {
            continue;
        }
        $docEvent = [
            'sort' => $date['sort'],
            'label' => $date['label'],
            'title' => 'Document',
            'body' => summariseText((string) ($file['file_description'] ?? ''), 160),
            'type' => 'document',
            'image' => null,
        ];
        if (timelineEventIsKeyImage($docEvent)) {
            continue;
        }
        $events[] = $docEvent;
    }

    usort($events, static function (array $a, array $b): int {
        return strcmp($a['sort'], $b['sort']);
    });

    if ($lifeCutoffSort === null && $deathSort !== null) {
        $lifeCutoffSort = $deathSort;
    }

    return [
        'dated' => $events,
        'undated' => $undated,
        'life_cutoff' => $lifeCutoffSort,
    ];
}

function partitionTimelineEvents(array $timeline): array
{
    $lifeCutoff = $timeline['life_cutoff'] ?? null;
    $dated = [];
    $postLife = [];
    foreach ($timeline['dated'] ?? [] as $event) {
        $sortKey = $event['sort'] ?? null;
        if ($lifeCutoff !== null && $sortKey !== null && strcmp($sortKey, $lifeCutoff) > 0) {
            $postLife[] = $event;
        } else {
            $dated[] = $event;
        }
    }
    return [$dated, $postLife, $timeline['undated'] ?? []];
}

function timelineHasRenderableContent(array $timeline): bool
{
    [$dated, $postLife, $undated] = partitionTimelineEvents($timeline);
    return !empty($dated) || !empty($postLife) || !empty($undated);
}

function renderTimelineChapter(SimplePDF $pdf, string $name, array $timeline): ?int
{
    [$datedEvents, $postLifeEvents, $undatedEvents] = partitionTimelineEvents($timeline);
    if (empty($datedEvents) && empty($postLifeEvents) && empty($undatedEvents)) {
        return null;
    }

    $pdf->AddPage();
    $pageNumber = $pdf->GetPageNumber();

    $pdf->SetFont('Helvetica', 'B', 18);
    $pdf->Cell(0, 10, 'Timeline of ' . $name, 0, 1, 'L');
    $pdf->Ln(12);

    $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
    $storyWidth = $usableWidth * 0.8;
    $contentHeight = $pdf->GetPageHeight() - $pdf->GetTopMargin() - $pdf->GetBottomMargin();
    $maxImageWidth = min($pdf->GetPageWidth() * 0.2, $usableWidth);
    $maxImageHeight = min($pdf->GetPageHeight() * 0.15, $contentHeight);
    $hasDated = !empty($datedEvents);

    $lastDateLabel = null;
    foreach ($datedEvents as $event) {
        $label = trim((string) ($event['label'] ?? ''));
        if ($label !== '' && strcasecmp($label, (string) $lastDateLabel) !== 0) {
            $indentX = max(2.0, $pdf->GetLeftMargin() - 4.0);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->SetTextColor(20, 45, 140);
            pdfSetX($pdf, $indentX);
            $pdf->MultiCell($pdf->GetPageWidth() - $indentX - $pdf->GetRightMargin(), 5.5, $label, 'L');
            $pdf->SetTextColor(0, 0, 0);
            $lastDateLabel = $label;
        }
        $icon = timelineIconForEvent($event);
        $titleText = trim((string) ($event['title'] ?? ''));
        $author = trim((string) ($event['author'] ?? ''));
        if ($author !== '') {
            $titleText .= ' [written by ' . $author . ']';
        }
        renderTimelineHeadingLine($pdf, $usableWidth, $titleText, $icon, 6.0);
        if (!empty($event['image'])) {
            $caption = trim((string) ($event['caption'] ?? ''));
            renderTimelinePhoto($pdf, $event['image'], $maxImageWidth, $maxImageHeight, $caption);
        }
        if ($event['type'] !== 'historical' && $event['body'] !== '') {
            if ($event['type'] === 'story') {
                $pdf->SetFont('Courier', '', 8.0);
                $lineHeight = 3.8;
            } else {
                $pdf->SetFont('Helvetica', '', 9.5);
                $lineHeight = 4.2;
            }
            $pdf->MultiCell($storyWidth, $lineHeight, $event['body'], 'L');
        }
        $pdf->Ln(2);
    }

    $otherEvents = array_merge($postLifeEvents, $undatedEvents);

    if (!empty($otherEvents)) {
        if ($hasDated) {
            $pdf->Ln(3);
            $ruleY = $pdf->GetY();
            $pdf->SetLineWidth(0.6);
            $pdf->Line($pdf->GetLeftMargin(), $ruleY, $pdf->GetPageWidth() - $pdf->GetRightMargin(), $ruleY);
            $pdf->SetLineWidth(0.2);
            $pdf->Ln(4);
        } else {
            $pdf->Ln(2);
        }
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->Cell(0, 8, 'Other information on ' . normalisePdfText($name), 0, 1, 'L');
        $pdf->Ln(8);
        foreach ($otherEvents as $event) {
            $icon = timelineIconForEvent($event);
            $titleText = trim((string) ($event['title'] ?? 'Item'));
            $author = trim((string) ($event['author'] ?? ''));
            if ($author !== '') {
                $titleText .= ' [written by ' . $author . ']';
            }
            renderTimelineHeadingLine($pdf, $usableWidth, $titleText, $icon, 5.5);
            if (!empty($event['image'])) {
                $caption = trim((string) ($event['caption'] ?? ''));
                renderTimelinePhoto($pdf, $event['image'], $maxImageWidth, $maxImageHeight, $caption);
            }
            if ($event['body'] !== '') {
                if ($event['type'] === 'story') {
                    $pdf->SetFont('Courier', '', 8.0);
                    $lineHeight = 3.8;
                } else {
                    $pdf->SetFont('Helvetica', '', 10);
                    $lineHeight = 4.5;
                }
                $pdf->MultiCell($storyWidth, $lineHeight, $event['body'], 'L');
            }
            $pdf->Ln(1.5);
        }
    }

    return $pageNumber;
}

function parseTimelineDate(?string $raw): ?array
{
    $value = trim((string) $raw);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\d{4}$/', $value)) {
        return [
            'sort' => $value . '0101',
            'label' => $value,
        ];
    }
    if (preg_match('/^\d{4}-\d{2}$/', $value)) {
        $timestamp = strtotime($value . '-01');
        return [
            'sort' => date('Ymd', $timestamp),
            'label' => formatTimelineDisplayLabel($timestamp, true, false),
        ];
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $timestamp = strtotime($value);
        return [
            'sort' => date('Ymd', $timestamp),
            'label' => formatTimelineDisplayLabel($timestamp, true, true),
        ];
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }
    return [
        'sort' => date('Ymd', $timestamp),
        'label' => formatTimelineDisplayLabel($timestamp, true, true),
    ];
}

function formatTimelineDisplayLabel(int $timestamp, bool $hasMonth = true, bool $hasDay = true): string
{
    if (!$hasMonth) {
        return date('Y', $timestamp);
    }
    if (!$hasDay) {
        return date('Y, M', $timestamp);
    }
    return date('Y, M d', $timestamp);
}
function formatIsoDateFromParts($year, $month, $day): ?string
{
    if (empty($year)) {
        return null;
    }
    $month = (int) ($month ?? 1);
    $day = (int) ($day ?? 1);
    return sprintf('%04d-%02d-%02d', (int) $year, $month, $day);
}

function resolveProfileImagePath(?string $path): ?string
{
    static $default = null;
    $candidate = $path !== null && trim($path) !== '' ? preparePdfImagePath($path) : null;
    if ($candidate !== null) {
        return $candidate;
    }
    if ($default === null) {
        $default = preparePdfImagePath('/images/default_avatar.webp');
    }
    return $default;
}

function extractTimelineDateFromGroup(array $group): ?string
{
    foreach ($group['items'] ?? [] as $item) {
        $type = strtolower(trim((string) ($item['detail_type'] ?? '')));
        if ($type === 'date') {
            $normalised = normaliseTimelineDateString($item['detail_value'] ?? '');
            if ($normalised !== null) {
                return $normalised;
            }
        }
    }
    return null;
}

function normaliseTimelineDateString(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\\d{4}$/', $value)) {
        return $value;
    }
    if (preg_match('/^\\d{4}-\\d{2}$/', $value)) {
        return $value;
    }
    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
        return $value;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }
    return date('Y-m-d', $timestamp);
}

function renderTimelinePhoto(SimplePDF $pdf, string $imagePath, float $maxWidth, float $maxHeight, string $caption = ''): void
{
    if ($imagePath === '' || !is_file($imagePath)) {
        return;
    }
    [$width, $height] = computeImageBoxWithMaxes($imagePath, $maxWidth, $maxHeight);
    $startX = $pdf->GetLeftMargin();
    $startY = $pdf->GetY() + 2;
    $pdf->Image($imagePath, $startX, $startY, $width, $height);
    $pdf->Ln($height + 2);
    if ($caption !== '') {
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->MultiCell($width, 3.8, $caption, 'L');
        $pdf->Ln(2);
    }
}

function renderTimelineHeadingLine(
    SimplePDF $pdf,
    float $usableWidth,
    string $titleText,
    string $icon = '',
    float $lineHeight = 6.0,
    float $textFontSize = 11.0
): void {
    $titleText = normalisePdfText(trim($titleText));
    if ($titleText === '') {
        return;
    }

    if ($icon === '') {
        $pdf->SetFont('Helvetica', 'B', $textFontSize);
        $pdf->MultiCell($usableWidth, $lineHeight, $titleText, 'L');
        return;
    }

    $iconWidth = 6.5;
    $startX = $pdf->GetX();
    $pdf->SetFont('Font Awesome 6 Free', '', max(6.0, $textFontSize + 0.5));
    $pdf->Cell($iconWidth, $lineHeight, $icon, 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', $textFontSize);
    pdfSetX($pdf, $startX + $iconWidth + 1.0);
    $pdf->MultiCell(max(10.0, $usableWidth - $iconWidth - 1.0), $lineHeight, $titleText, 'L');
}

function determineFrameColor(?array $accentColor): array
{
    if (is_array($accentColor) && count($accentColor) === 3) {
        return [
            max(0, min(255, (int) $accentColor[0])),
            max(0, min(255, (int) $accentColor[1])),
            max(0, min(255, (int) $accentColor[2])),
        ];
    }
    return [45, 45, 45];
}

function drawFramedImage(
    SimplePDF $pdf,
    string $imagePath,
    float $imageWidth,
    float $imageHeight,
    float $frameX,
    float $frameY,
    array $frameColor,
    float $padding = 4.0
): void {
    $frameWidth = $imageWidth + ($padding * 2);
    $frameHeight = $imageHeight + ($padding * 2);
    $pdf->RoundedRect($frameX, $frameY, $frameWidth, $frameHeight, 5.0, $frameColor, 1.2, [255, 255, 255]);
    $pdf->Image($imagePath, $frameX + $padding, $frameY + $padding, $imageWidth, $imageHeight);
}

function timelineEventIsKeyImage(array $event): bool
{
    $title = strtolower(trim((string) ($event['title'] ?? '')));
    if ($title !== '' && str_contains($title, 'key image')) {
        return true;
    }
    return false;
}

function extractTimelineImageFromGroup(array $group, ?string $keyImageNormalised): ?array
{
    $candidates = [];
    $topType = strtolower((string) ($group['file_type'] ?? ''));
    $topPath = trim((string) ($group['file_path'] ?? ''));
    if ($topType === 'image' && $topPath !== '') {
        $candidates[] = [
            'path' => $topPath,
            'caption' => $group['file_description'] ?? $group['item_group_name'] ?? '',
        ];
    }

    foreach ($group['items'] ?? [] as $item) {
        $itemType = strtolower((string) ($item['file_type'] ?? ''));
        $itemPath = trim((string) ($item['file_path'] ?? ''));
        if ($itemType === 'image' && $itemPath !== '') {
            $candidates[] = [
                'path' => $itemPath,
                'caption' => $item['file_description'] ?? $item['detail_value'] ?? ($group['item_group_name'] ?? ''),
            ];
        }
    }

    foreach ($candidates as $candidate) {
        $path = $candidate['path'] ?? '';
        if ($path === '') {
            continue;
        }
        $normalised = normaliseMediaPath($path);
        if ($keyImageNormalised !== null && $normalised !== null && $normalised === $keyImageNormalised) {
            continue;
        }
        $imagePath = preparePdfImagePath($path);
        if ($imagePath !== null) {
            return [
                'path' => $imagePath,
                'caption' => summariseText((string) ($candidate['caption'] ?? ''), 160),
            ];
        }
    }

    return null;
}

function isBurialEventTitle(string $title): bool
{
    $title = strtolower($title);
    return str_contains($title, 'burial')
        || str_contains($title, 'interment')
        || str_contains($title, 'funeral')
        || str_contains($title, 'entombment');
}

function timelineIconForEvent(array $event): string
{
    $type = strtolower((string) ($event['type'] ?? ''));
    $title = strtolower((string) ($event['title'] ?? ''));
    if ($title !== '') {
        if (
            str_contains($title, 'residence') ||
            str_contains($title, 'address') ||
            str_contains($title, 'home')
        ) {
            return "\u{f015}";
        }
        if (
            str_contains($title, 'marriage') ||
            str_contains($title, 'wedding') ||
            str_contains($title, 'spouse')
        ) {
            return "\u{f70b}";
        }
        if (
            str_contains($title, 'military') ||
            str_contains($title, 'service') ||
            str_contains($title, 'army') ||
            str_contains($title, 'navy') ||
            str_contains($title, 'air force') ||
            str_contains($title, 'enlist')
        ) {
            return "\u{f807}";
        }
        if (str_contains($title, 'occupation')) {
            return "\u{f0b1}";
        }
    }
    if ($type === 'milestone' && $title !== '') {
        if (str_contains($title, 'birth')) {
            return "\u{f77c}";
        }
        if (str_contains($title, 'burial') || str_contains($title, 'funeral') || str_contains($title, 'interment')) {
            return "\u{f720}";
        }
        if (str_contains($title, 'death')) {
            return "\u{f54c}";
        }
    }
    $map = [
        'photo' => "\u{f03e}",
        'document' => "\u{f15c}",
        'story' => "\u{f518}",
        'fact' => "\u{f05a}",
        'historical' => "\u{f66f}",
        'milestone' => "\u{f277}",
    ];
    if (isset($map[$type])) {
        return $map[$type];
    }
    return '';
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
        $pdf->MultiCell(0, 7, $name . ' - ' . $relationship);
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
        $urls = [];
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
                    if (strcasecmp($type, 'URL') === 0) {
                        $urls[] = $value;
                    } else {
                        $segments[] = ($type !== '' ? $type : 'Detail') . ': ' . $value;
                    }
                }
                $notes = array_merge($notes, $detailNotes);
            }
        }

        $segments = array_values(array_filter($segments, static function ($segment) {
            return $segment !== '';
        }));
        $urls = array_values(array_filter($urls, static function ($u) {
            return trim((string) $u) !== '';
        }));
        $notes = array_values(array_filter(array_unique(array_map(static function ($note) {
            return trim($note);
        }, $notes)), static function ($note) {
            return $note !== '';
        }));

        if (empty($segments) && empty($notes) && empty($urls)) {
            continue;
        }

        $detailParts = [];
        if (!empty($segments)) {
            $detailParts[] = implode('; ', $segments);
        }
        if (!empty($notes)) {
            $detailParts[] = implode(PHP_EOL . PHP_EOL, $notes);
        }

        $facts[] = [
            'title' => $label !== '' ? $label : 'Fact',
            'detail' => !empty($detailParts) ? implode(PHP_EOL . PHP_EOL, $detailParts) : '',
            'urls' => $urls,
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
        $content = normaliseStoryContent((string) ($story['content'] ?? ''));
        if ($title === '' && $content === '') {
            continue;
        }
        if ($title === '') {
            $title = 'Story';
        }
        $output[] = [
            'title' => $title,
            'content' => $content,
        ];
    }
    return $output;
}

function normaliseStoryContent(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    // Preserve basic line breaks before stripping tags
    $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html);
    $text = preg_replace('/<\s*\/p\s*>/i', "\n\n", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Collapse spaces/tabs but keep newlines
    $text = preg_replace('/[ \t]+/', ' ', $text);
    // Limit consecutive blank lines
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    // Trim each line
    $lines = array_map('trim', explode("\n", $text));
    $text = implode("\n", $lines);
    return normalisePdfText(trim($text));
}

function summariseText(string $text, int $maxLength = 250): string
{
    $decoded = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalised = trim(preg_replace('/\s+/', ' ', $decoded));
    if ($normalised === '') {
        return '';
    }
    if (mb_strlen($normalised) > $maxLength) {
        return normalisePdfText(rtrim(mb_substr($normalised, 0, $maxLength - 1)) . '.');
    }
    return normalisePdfText($normalised);
}

function normalisePdfText(string $text): string
{
    if ($text === '') {
        return '';
    }
    $map = [
        "\u{2018}" => "'",
        "\u{2019}" => "'",
        "\u{201A}" => "'",
        "\u{201B}" => "'",
        "\u{2032}" => "'",
        "\u{2035}" => "'",
        "\u{201C}" => '"',
        "\u{201D}" => '"',
        "\u{201E}" => '"',
        "\u{201F}" => '"',
        "\u{2033}" => '"',
        "\u{2036}" => '"',
        "\u{00A0}" => ' ',
    ];
    return str_replace(array_keys($map), array_values($map), $text);
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
    $baseFont = 7;
    $lineHeight = 4.0;
    $pageBottom = $pdf->GetPageHeight() - $pdf->GetBottomMargin();

    $rendered = 0;
    foreach ($filtered as $photo) {
        $imagePath = preparePdfImagePath($photo['file_path'] ?? null);
        if ($imagePath === null) {
            continue;
        }

        if ($rendered > 0 && $rendered % $perRow === 0) {
            $y += $rowHeight + 12;
            $x = $pdf->GetLeftMargin();
            $rowHeight = 0.0;
        }

        // Constrain image to target width and at most 1/5 of content height
        $maxContentHeight = ($pdf->GetPageHeight() - $pdf->GetTopMargin() - $pdf->GetBottomMargin()) * 0.2;
        [$width, $height] = computeImageBoxWithMaxes($imagePath, $targetWidth, max(10.0, $maxContentHeight));
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

        $pdf->Image($imagePath, $x, $y, $width, $height);
        $rowHeight = max($rowHeight, $height + ($captionHeight > 0 ? $captionHeight : 0));

        $caption = summariseText((string) ($photo['file_description'] ?? ''), 120);
        if ($caption !== '') {
            $pdf->SetFont('Helvetica', '', $baseFont);
            $pdf->SetXY($x, $y + $height + 1);
            // Ensure wrapped caption lines stay aligned with the photo edge
            $pdf->MultiCell($width, $lineHeight, $caption, 'L', 0.0);
            $captionBottom = $pdf->GetY();
            $rowHeight = max($rowHeight, $captionBottom - $y);
            pdfSetY($pdf, $y);
        }

        $x += $width + $spacing;
        $rendered++;
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

function wrapTextForWidth(SimplePDF $pdf, float $width, float $lineHeight, string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }

    $usableWidth = $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin();
    if ($width <= 0.0 || $width > $usableWidth) {
        $width = $usableWidth;
    }
    // Mirror SimplePDF::wrapLine() heuristics
    $charWidth = max(0.1, $lineHeight * (0.5 / 1.35));
    $maxChars = max(1, (int) floor($width / $charWidth));

    $out = [];
    $lines = preg_split("/(\r\n|\r|\n)/", $text);
    if ($lines === false || empty($lines)) {
        $lines = [$text];
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            $out[] = '';
            continue;
        }
        $wrapped = wordwrap($line, $maxChars, "\n", true);
        $chunks = $wrapped === '' ? [''] : explode("\n", $wrapped);
        foreach ($chunks as $chunk) {
            $out[] = $chunk;
        }
    }
    return $out;
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

function computeImageBoxWithMaxes(string $path, float $maxWidth, float $maxHeight): array
{
    $real = resolveMediaPath($path);
    if (!$real || !is_file($real)) {
        return [$maxWidth, min($maxHeight, $maxWidth)];
    }
    [$w, $h] = @getimagesize($real);
    if (!$w || !$h) {
        return [$maxWidth, min($maxHeight, $maxWidth)];
    }
    $scaleW = $w > 0 ? ($maxWidth / $w) : 1.0;
    $scaleH = $h > 0 ? ($maxHeight / $h) : 1.0;
    $scale = min(1.0, $scaleW, $scaleH);
    $newW = $w * $scale;
    $newH = $h * $scale;
    return [$newW, $newH];
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

/**
 * Provide SimplePDF with an absolute path (or URL) it can load for image embedding.
 */
function preparePdfImagePath(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }

    $resolved = resolveMediaPath($path);
    if ($resolved !== null) {
        return $resolved;
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    if (is_file($path)) {
        $real = realpath($path);
        return $real !== false ? $real : $path;
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

/**
 * Build a quick lookup of direct parent for each descendant id.
 */
function buildDirectParentMap(array $generationData): array
{
    $map = [];
    foreach ($generationData as $gen => $people) {
        foreach ((array) $people as $p) {
            $id = isset($p['id']) ? (int) $p['id'] : 0;
            $parentId = isset($p['direct_parent_id']) ? (int) $p['direct_parent_id'] : 0;
            if ($id > 0 && $parentId > 0) {
                $map[$id] = $parentId;
            }
        }
    }
    return $map;
}

function buildDescendantColorMap(array $lineMetadata, array $directParentMap): array
{
    $colorMap = [];
    foreach ($lineMetadata as $lineId => $meta) {
        if (!empty($meta['color'])) {
            $colorMap[(int) $lineId] = $meta['color'];
        }
    }

    $maxIterations = count($directParentMap) + 5;
    $iteration = 0;
    while ($iteration++ < $maxIterations) {
        $progress = false;
        foreach ($directParentMap as $childId => $parentId) {
            if (isset($colorMap[$childId])) {
                continue;
            }
            if (isset($colorMap[$parentId])) {
                $colorMap[$childId] = $colorMap[$parentId];
                $progress = true;
            }
        }
        if (!$progress) {
            break;
        }
    }

    return $colorMap;
}

/**
 * Sort generation members by parent (by parent birth then name), then by member birth then name.
 */
function sortMembersByParentThenBirth(array $members, array $directParentMap): array
{
    static $parentCache = [];

    $getParent = static function (int $childId) use (&$parentCache, $directParentMap): ?array {
        $parentId = $directParentMap[$childId] ?? null;
        if (!$parentId) {
            return null;
        }
        $parentId = (int) $parentId;
        if (!isset($parentCache[$parentId])) {
            $parentCache[$parentId] = Utils::getIndividual($parentId) ?: null;
        }
        return $parentCache[$parentId];
    };

    usort($members, static function (array $a, array $b) use ($getParent): int {
        $aId = (int) ($a['id'] ?? 0);
        $bId = (int) ($b['id'] ?? 0);
        $pa = $getParent($aId);
        $pb = $getParent($bId);

        // Order by parent when different parents
        if ($pa && $pb) {
            $cmp = Utils::compareIndividualsByBirthThenName($pa, $pb);
            if ($cmp !== 0) {
                return $cmp;
            }
        } elseif ($pa && !$pb) {
            return -1; // entries with known parent first
        } elseif (!$pa && $pb) {
            return 1;
        }

        // Then by child's own birth then name
        return Utils::compareIndividualsByBirthThenName($a, $b);
    });

    return $members;
}

/**
 * Build ancestor chain string for a descendant up to the line founder.
 * Example output: "William Macdonald -> Alexander Macdonald -> Janice Macdonald".
 */
function buildAncestorChainForDescendant(int $personId, string $lineFounderId, array $directParentMap, array &$bundleCache): string
{
    $founderId = (int) $lineFounderId;
    if ($personId <= 0 || $founderId <= 0) {
        return '';
    }
    $chainIds = [];
    $current = $personId;
    $guard = 0;
    $seen = [];
    while (++$guard < 100) {
        $parentId = isset($directParentMap[$current]) ? (int) $directParentMap[$current] : 0;
        if ($parentId <= 0) {
            break;
        }
        $chainIds[] = $parentId;
        if ($parentId === $founderId) {
            break;
        }
        if (isset($seen[$parentId])) {
            break; // avoid cycles
        }
        $seen[$parentId] = true;
        $current = $parentId;
    }

    if (empty($chainIds)) {
        return '';
    }
    $chainIds = array_reverse($chainIds);
    $names = [];
    foreach ($chainIds as $id) {
        $bundle = fetchIndividualBundle((int) $id, $bundleCache);
        $name = formatPersonName($bundle['person'] ?? null);
        if ($name !== '') {
            $names[] = $name;
        }
    }
    if (empty($names)) {
        return '';
    }
    return implode(' -> ', $names);
}
