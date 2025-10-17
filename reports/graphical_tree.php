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

$rootPerson = Utils::getIndividual($individualId);
if (!$rootPerson) {
    http_response_code(404);
    echo 'Individual not found.';
    exit;
}

$treeNodes = buildDescendantTreeNodes($individualId, $maxGenerations);
if (empty($treeNodes)) {
    http_response_code(404);
    echo 'No descendants found for the selected individual.';
    exit;
}

$maxGenerationCount = getMaxGenerationCount($treeNodes);
$useA2 = $maxGenerationCount > 40;

$pdf = new SimplePDF();
if ($useA2) {
    $pdf->SetPageSize(420.0, 594.0); // A2 portrait (AddPage('L') rotates)
} else {
    $pdf->SetPageSize(297.0, 420.0); // A3 portrait
}
$pdf->SetMargins(15.0, 20.0, 15.0);
$pdf->SetBottomMargin(20.0);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage('L');
$pdf->SetTextColor(0, 0, 0);

$rootName = formatPersonLabel($rootPerson);
$title = 'Descendant Tree for ' . $rootName;
$pdf->SetFont('Helvetica', 'B', 20.0);
$pdf->Cell(0, 12, $title, 0, 'C');
$pdf->Ln(14.0);

layoutTree($individualId, $treeNodes, $pdf);
renderTree($pdf, $treeNodes);

$fileName = buildTreeFileName($rootPerson);
$pdf->Output('I', $fileName);
exit;

/**
 * Build a keyed map of tree nodes for the descendants report.
 *
 * @return array<int,array<string,mixed>>
 */
function buildDescendantTreeNodes(int $rootId, ?int $maxGenerations): array
{
    $nodes = [];
    $rootPerson = Utils::getIndividual($rootId);
    if (!$rootPerson) {
        return [];
    }

    $nodes[$rootId] = [
        'id' => $rootId,
        'person' => $rootPerson,
        'generation' => 0,
        'parent_id' => null,
        'children' => [],
        'child_spouse_map' => [],
    ];

    $generationData = Utils::getDescendantsByGeneration($rootId, $maxGenerations);
    foreach ($generationData as $generation => $people) {
        foreach ($people as $entry) {
            $childId = isset($entry['id']) ? (int) $entry['id'] : 0;
            if ($childId <= 0) {
                continue;
            }

            if (!isset($nodes[$childId])) {
                $person = Utils::getIndividual($childId);
                if (!$person) {
                    continue;
                }
                $nodes[$childId] = [
                    'id' => $childId,
                    'person' => $person,
                    'generation' => (int) $generation,
                    'parent_id' => null,
                    'children' => [],
                    'child_spouse_map' => [],
                ];
            } else {
                $nodes[$childId]['generation'] = min(
                    $nodes[$childId]['generation'],
                    (int) $generation
                );
            }

            $parentId = isset($entry['direct_parent_id']) ? (int) $entry['direct_parent_id'] : 0;
            if ($parentId > 0) {
                $nodes[$childId]['parent_id'] = $parentId;
                if (!isset($nodes[$parentId])) {
                    $parentPerson = Utils::getIndividual($parentId);
                    if (!$parentPerson) {
                        continue;
                    }
                    $nodes[$parentId] = [
                        'id' => $parentId,
                        'person' => $parentPerson,
                        'generation' => max(0, (int) $generation - 1),
                        'parent_id' => null,
                        'children' => [],
                        'child_spouse_map' => [],
                    ];
                }
                if (!in_array($childId, $nodes[$parentId]['children'], true)) {
                    $nodes[$parentId]['children'][] = $childId;
                }
                if (!isset($nodes[$parentId]['child_spouse_map'])) {
                    $nodes[$parentId]['child_spouse_map'] = [];
                }
                $otherParentId = findOtherParentId($childId, $parentId);
                $nodes[$parentId]['child_spouse_map'][$childId] = $otherParentId > 0 ? $otherParentId : 0;
            }
        }
    }

    foreach ($nodes as &$node) {
        if (!empty($node['children'])) {
            usort($node['children'], static function (int $a, int $b) use ($nodes): int {
                if (!isset($nodes[$a], $nodes[$b])) {
                    return $a <=> $b;
                }
                return Utils::compareIndividualsByBirthThenName(
                    $nodes[$a]['person'],
                    $nodes[$b]['person']
                );
            });
        }
    }
    unset($node);

    return $nodes;
}

function getMaxGenerationCount(array $nodes): int
{
    $counts = [];
    foreach ($nodes as $node) {
        $generation = isset($node['generation']) ? (int) $node['generation'] : 0;
        $counts[$generation] = ($counts[$generation] ?? 0) + 1;
    }

    return empty($counts) ? 0 : (int) max($counts);
}

function findOtherParentId(int $childId, int $knownParentId): int
{
    static $cache = [];
    $cacheKey = $childId . ':' . $knownParentId;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $parents = Utils::getParents($childId);
    if (empty($parents)) {
        $cache[$cacheKey] = 0;
        return 0;
    }

    foreach ($parents as $parent) {
        $pid = isset($parent['id']) ? (int) $parent['id'] : 0;
        if ($pid > 0 && $pid !== $knownParentId) {
            $cache[$cacheKey] = $pid;
            return $pid;
        }
    }

    $cache[$cacheKey] = 0;
    return 0;
}

function layoutTree(int $rootId, array &$nodes, SimplePDF $pdf): array
{
    if (!isset($nodes[$rootId])) {
        return [
            'left' => $pdf->GetLeftMargin(),
            'top' => $pdf->GetTopMargin(),
            'usable_width' => $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin(),
            'usable_height' => $pdf->GetPageHeight() - $pdf->GetTopMargin() - $pdf->GetBottomMargin(),
            'box_width' => 60.0,
            'box_height' => 24.0,
        ];
    }

    $orderCounter = 0.0;
    assignTreeOrder($rootId, $nodes, $orderCounter);

    $visibleNodes = array_filter($nodes, static function (array $node): bool {
        return isset($node['layout']);
    });
    if (empty($visibleNodes)) {
        return [
            'left' => $pdf->GetLeftMargin(),
            'top' => $pdf->GetTopMargin(),
            'usable_width' => $pdf->GetPageWidth() - $pdf->GetLeftMargin() - $pdf->GetRightMargin(),
            'usable_height' => $pdf->GetPageHeight() - $pdf->GetTopMargin() - $pdf->GetBottomMargin(),
            'box_width' => 60.0,
            'box_height' => 24.0,
        ];
    }

    $maxGeneration = 0;
    $leafCount = max(1, (int) ceil($orderCounter));
    foreach ($visibleNodes as $node) {
        $maxGeneration = max($maxGeneration, (int) ($node['generation'] ?? 0));
    }

    $leftMargin = $pdf->GetLeftMargin();
    $rightMargin = $pdf->GetRightMargin();
    $topMargin = $pdf->GetTopMargin() + 16.0; // leave room for title
    $bottomMargin = $pdf->GetBottomMargin();
    $usableWidth = $pdf->GetPageWidth() - $leftMargin - $rightMargin;
    $usableHeight = $pdf->GetPageHeight() - $topMargin - $bottomMargin;

    $columnCount = max(1, $maxGeneration + 1);
    $columnSpacing = $usableWidth / $columnCount;

    $unitSpacing = $usableHeight / max(1, $leafCount + 1);
    $boxWidth = min(80.0, max(28.0, $columnSpacing * 0.85));
    $boxHeight = min(28.0, max(10.0, $unitSpacing * 0.8));

    if ($boxHeight > $unitSpacing - 2.0) {
        $boxHeight = max(8.0, $unitSpacing - 2.0);
    }

    foreach ($visibleNodes as $nodeId => $node) {
        $orderValue = $node['layout']['order'];
        $generation = (int) ($node['generation'] ?? 0);
        $columnCenter = $leftMargin + ($generation + 0.5) * $columnSpacing;
        $centerY = $topMargin + ($orderValue + 1) * $unitSpacing;
        $centerY = max(
            $topMargin + ($boxHeight / 2.0),
            min($topMargin + $usableHeight - ($boxHeight / 2.0), $centerY)
        );
        $boxX = $columnCenter - ($boxWidth / 2.0);
        $boxY = $centerY - ($boxHeight / 2.0);

        if ($boxX < $leftMargin) {
            $boxX = $leftMargin;
        }
        if ($boxX + $boxWidth > $leftMargin + $usableWidth) {
            $boxX = $leftMargin + $usableWidth - $boxWidth;
        }

        $paddingLeft = 5.0;
        $nodes[$nodeId]['layout']['box'] = [
            'x' => $boxX,
            'y' => $boxY,
            'width' => $boxWidth,
            'height' => $boxHeight,
            'center_x' => $boxX + ($boxWidth / 2.0),
            'center_y' => $centerY,
            'padding_left' => $paddingLeft,
            'generation' => $generation,
        ];
    }

    return [
        'left' => $leftMargin,
        'top' => $topMargin,
        'usable_width' => $usableWidth,
        'usable_height' => $usableHeight,
        'box_width' => $boxWidth,
        'box_height' => $boxHeight,
    ];
}

function assignTreeOrder(int $nodeId, array &$nodes, float &$nextOrder): float
{
    if (!isset($nodes[$nodeId])) {
        return $nextOrder;
    }
    $node =& $nodes[$nodeId];
    $children = $node['children'] ?? [];
    $orders = [];
    if (!empty($children)) {
        foreach ($children as $childId) {
            $orders[] = assignTreeOrder($childId, $nodes, $nextOrder);
        }
        if (!empty($orders)) {
            $node['layout']['order'] = (min($orders) + max($orders)) / 2.0;
            return $node['layout']['order'];
        }
    }
    $node['layout']['order'] = $nextOrder;
    $nextOrder += 1.0;
    return $node['layout']['order'];
}

function renderTree(SimplePDF $pdf, array &$nodes): void
{
    $anchors = [];
    foreach ($nodes as $nodeId => &$node) {
        if (!isset($node['layout']['box'])) {
            continue;
        }
        $box = $node['layout']['box'];
        $anchors[$nodeId] = drawPersonBox($pdf, $box, $node['person'], $node['id']);
        $node['layout']['anchors'] = $anchors[$nodeId];
    }
    unset($node);

    foreach ($nodes as $nodeId => $node) {
        if (empty($node['children']) || !isset($anchors[$nodeId])) {
            continue;
        }
        $parentBox = $node['layout']['box'];
        $padding = $parentBox['padding_left'] ?? 0.0;
        $parentRightX = $parentBox['x'] + $parentBox['width'] - max(0.0, $padding * 0.4);
        $parentAnchors = $anchors[$nodeId];
        $defaultParentY = $parentAnchors['name_line'] ?? $parentBox['center_y'];
        $spouseAnchors = $parentAnchors['spouses'] ?? [];
        $spouseColors = $parentAnchors['spouse_colors'] ?? [];

        $children = $node['children'];
        if (!empty($children) && !empty($node['child_spouse_map'])) {
            usort($children, static function (int $a, int $b) use ($node, $nodes): int {
                $map = $node['child_spouse_map'];
                $spouseA = $map[$a] ?? 0;
                $spouseB = $map[$b] ?? 0;
                if ($spouseA !== $spouseB) {
                    return $spouseA <=> $spouseB;
                }
                if (!isset($nodes[$a]['person'], $nodes[$b]['person'])) {
                    return $a <=> $b;
                }
                return Utils::compareIndividualsByBirthThenName($nodes[$a]['person'], $nodes[$b]['person']);
            });
        }
        foreach ($children as $childId) {
            if (!isset($anchors[$childId])) {
                continue;
            }
            $childBox = $nodes[$childId]['layout']['box'];
            $childPadding = $childBox['padding_left'] ?? 0.0;
            $childLeftX = $childBox['x'] + max(0.0, $childPadding * 0.6);
            $childAnchor = $anchors[$childId]['entry_y'] ?? $childBox['center_y'];

            $spouseId = $node['child_spouse_map'][$childId] ?? 0;
            $parentAnchorY = $spouseAnchors[$spouseId] ?? $defaultParentY;
            $color = $spouseColors[$spouseId] ?? [0, 0, 0];

            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->Line($parentRightX, $parentAnchorY, $childLeftX, $childAnchor);
            $pdf->SetTextColor(0, 0, 0);
        }
    }
}

function drawPersonBox(SimplePDF $pdf, array $box, array $person, int $personId): array
{
    $x = $box['x'];
    $y = $box['y'];
    $width = $box['width'];

    $paddingLeft = $box['padding_left'] ?? 0.0;
    $paddingTop = 0.8;
    $innerX = $x + $paddingLeft;
    $innerWidth = max(0.0, $width - $paddingLeft);
    $cursorY = $y + $paddingTop;

    $anchors = [
        'name_line' => $y + min($box['height'] - 1.0, 3.0),
        'entry_y' => $box['center_y'],
        'spouses' => [0 => $box['center_y']],
        'spouse_colors' => [0 => [0, 0, 0]],
    ];

    $generation = isset($box['generation']) ? (int) $box['generation'] : 0;
    $nameFontSize = max(6.0, 10.0 - $generation);
    $lifespanFontSize = max(5.5, 9.0 - $generation);
    $spouseFontSize = max(5.5, 9.0 - $generation);
    $nameLineHeight = max(3.4, 3.8 - ($generation * 0.1));
    $lifespanLineHeight = max(3.0, 3.4 - ($generation * 0.1));
    $spouseGap = max(1.5, 3.0 - ($generation * 0.4));

    $name = formatPersonLabel($person);
    $lifespan = buildYearRange($person['birth_year'] ?? null, $person['death_year'] ?? null);
    $genderSymbol = getGenderSymbol($person);
    $displayName = $genderSymbol !== '' ? $genderSymbol . ' ' . $name : $name;

    $nameLines = wrapTextForWidth($pdf, $displayName, $nameFontSize, $innerWidth);
    $pdf->SetFont('DejaVu Sans', 'B', $nameFontSize);
    foreach ($nameLines as $index => $line) {
        $lineY = $cursorY + ($index * $nameLineHeight);
        $pdf->SetXY($innerX, $lineY);
        $pdf->Cell($innerWidth, $nameLineHeight, $line, 0, 0, 'L');
    }
    $lineCount = max(1, count($nameLines));
    $currentY = $cursorY + ($lineCount * $nameLineHeight);

    if ($lifespan !== '') {
        $lastLine = end($nameLines);
        $lastLineWidth = estimateTextWidth($pdf, $lastLine, $nameFontSize);
        $gap = determineNameGapFactor($lastLineWidth);
        $lifespanText = '(' . $lifespan . ')';
        $lifespanWidth = estimateTextWidth($pdf, $lifespanText, $lifespanFontSize);
        $availableWidth = max(0.0, $innerWidth - ($lastLineWidth + $gap));
        $lifespanTopY = $cursorY + max(0.0, ($lineCount - 1) * $nameLineHeight);
        $lifespanY = $lifespanTopY + max(0.0, ($nameLineHeight - $lifespanLineHeight) / 2.0);

        $pdf->SetFont('DejaVu Sans', '', $lifespanFontSize);
        if ($lifespanWidth <= $availableWidth && $availableWidth > 0.0) {
            $pdf->SetXY($innerX + $lastLineWidth + $gap, $lifespanY);
            $pdf->Cell($availableWidth, $lifespanLineHeight, $lifespanText, 0, 0, 'L');
            $currentY = max($currentY, $lifespanY + $lifespanLineHeight);
        } else {
            $extraGap = max(0.6, $gap * 0.35);
            $pdf->SetXY($innerX, $currentY + $extraGap);
            $pdf->Cell($innerWidth, $lifespanLineHeight, $lifespanText, 0, 0, 'L');
            $currentY += $lifespanLineHeight + $extraGap;
        }
    }

    $cursorY = $currentY + 0.1;
    $anchors['name_line'] = min($box['center_y'], $cursorY - 1.2);

    $spouses = fetchSpouses($personId);
    if (!empty($spouses)) {
        $multipleSpouses = count($spouses) > 1;
        $palette = spouseColorPalette();
        foreach ($spouses as $index => $spouse) {
            $label = 'with ' . formatPersonLabel($spouse);
            $spouseId = isset($spouse['id']) ? (int) $spouse['id'] : 0;
            $color = selectSpouseColor($spouseId, $index, $palette);

            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->SetXY($innerX, $cursorY);
            $lineStartY = $cursorY;
            $pdf->SetFont('DejaVu Sans', 'B', $spouseFontSize);
            $pdf->MultiCell($innerWidth, max(3.0, $lifespanLineHeight), $label, 'L');
            $cursorY = $pdf->GetY();

            if (!isset($anchors['spouses'][$spouseId])) {
                $anchors['spouses'][$spouseId] = min($cursorY - 1.0, $lineStartY + 2.1);
            }
            if (!isset($anchors['spouse_colors'][$spouseId])) {
                $anchors['spouse_colors'][$spouseId] = $color;
            }

            if ($multipleSpouses && $generation === 0 && $index < count($spouses) - 1) {
                $cursorY += $spouseGap;
            }
        }
        $pdf->SetTextColor(0, 0, 0);
    }

    return $anchors;
}

function formatPersonLabel(array $person): string
{
    $first = isset($person['first_names']) ? cleanNameToken(str_replace('_', ' ', (string) $person['first_names'])) : '';
    $last = isset($person['last_name']) ? cleanNameToken((string) $person['last_name']) : '';
    $name = trim($first . ' ' . $last);
    return $name !== '' ? $name : 'Unknown';
}

function cleanNameToken(string $value): string
{
    $text = trim($value);
    if ($text === '') {
        return '';
    }
    $text = preg_replace('/\s+/', ' ', $text);
    return htmlspecialchars_decode((string) $text, ENT_QUOTES);
}

function buildYearRange($birthYear, $deathYear): string
{
    $birthYear = (int) $birthYear;
    $deathYear = (int) $deathYear;
    if ($birthYear <= 0 && $deathYear <= 0) {
        return '';
    }
    $birthLabel = $birthYear > 0 ? (string) $birthYear : '';
    $deathLabel = $deathYear > 0 ? (string) $deathYear : '';
    if ($birthLabel === '' && $deathLabel === '') {
        return '';
    }
    return trim($birthLabel . ' - ' . $deathLabel, ' -');
}

function estimateTextWidth(SimplePDF $pdf, string $text, float $fontSize): float
{
    $length = mb_strlen($text, 'UTF-8');
    $averageCharWidth = max(0.1, $fontSize * 0.352778 * 0.5);
    return ($length * $averageCharWidth) + $averageCharWidth;
}

function wrapTextForWidth(SimplePDF $pdf, string $text, float $fontSize, float $maxWidth): array
{
    if ($maxWidth <= 0.0) {
        return [$text];
    }
    $lines = preg_split("/(\r\n|\r|\n)/", $text);
    if ($lines === false || empty($lines)) {
        $lines = [$text];
    }

    $wrapped = [];
    $charWidth = max(0.1, $fontSize * 0.352778 * 0.5);
    $maxChars = max(1, (int) floor($maxWidth / $charWidth));
    foreach ($lines as $rawLine) {
        $rawLine = (string) $rawLine;
        if ($rawLine === '') {
            $wrapped[] = '';
            continue;
        }
        $chunks = wordwrap($rawLine, $maxChars, "\n", true);
        $wrapped = array_merge($wrapped, explode("\n", $chunks === '' ? '' : $chunks));
    }
    if (empty($wrapped)) {
        $wrapped[] = '';
    }
    return $wrapped;
}

function determineNameGapFactor(float $lastLineWidth): float
{
    if ($lastLineWidth <= 28.0) {
        return 4.0;
    }
    if ($lastLineWidth <= 40.0) {
        return 5.5;
    }
    if ($lastLineWidth <= 55.0) {
        return 7.5;
    }
    if ($lastLineWidth <= 70.0) {
        return 9.5;
    }
    if ($lastLineWidth <= 90.0) {
        return 11.5;
    }
    return 13.5;
}

function spouseColorPalette(): array
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

function selectSpouseColor(int $spouseId, int $index, array $palette): array
{
    if (empty($palette)) {
        return [0, 0, 0];
    }
    if ($spouseId > 0) {
        $hash = abs((int) crc32((string) $spouseId));
        return $palette[$hash % count($palette)];
    }
    return $palette[$index % count($palette)];
}

function fetchSpouses(int $personId): array
{
    static $cache = [];
    if (isset($cache[$personId])) {
        return $cache[$personId];
    }
    $spouses = Utils::getSpouses($personId);
    if (!is_array($spouses)) {
        $spouses = [];
    }
    $cache[$personId] = $spouses;
    return $spouses;
}

function buildTreeFileName(array $person): string
{
    $name = formatPersonLabel($person);
    $safe = preg_replace('/[^A-Za-z0-9_\-]+/', '_', str_replace(' ', '_', $name));
    $safe = trim((string) $safe, '_');
    if ($safe === '') {
        $safe = 'individual';
    }
    return $safe . '_Descendant_Tree.pdf';
}

function getGenderSymbol(array $person): string
{
    $genderRaw = isset($person['gender']) ? strtolower(trim((string) $person['gender'])) : '';
    return match ($genderRaw) {
        'male', 'man', 'm' => '♂',
        'female', 'woman', 'f' => '♀',
        '', 'other', 'unknown', 'u' => '⚲',
        default => '⚲',
    };
}
