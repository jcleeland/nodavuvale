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

$startYearRaw = isset($_GET['start_year']) ? trim((string) $_GET['start_year']) : '';
$endYearRaw = isset($_GET['end_year']) ? trim((string) $_GET['end_year']) : '';

if ($startYearRaw === '' || $endYearRaw === '') {
    http_response_code(400);
    echo 'Both start_year and end_year must be provided.';
    exit;
}

if (!ctype_digit($startYearRaw) || !ctype_digit($endYearRaw)) {
    http_response_code(400);
    echo 'Years must be positive integers.';
    exit;
}

$startYear = (int) $startYearRaw;
$endYear = (int) $endYearRaw;

if ($startYear > $endYear) {
    http_response_code(400);
    echo 'The start year cannot be greater than the end year.';
    exit;
}

$maximumSpan = 500;
if ($endYear - $startYear > $maximumSpan) {
    http_response_code(400);
    echo "The requested range is too large. Please select a span of {$maximumSpan} years or fewer.";
    exit;
}

$individualRows = fetchIndividualsWithinSpan($db, $startYear, $endYear);
$timelineItems = fetchTimelineItems($startYear, $endYear); // placeholder for future expansion
$timelineEntries = buildTimelineEntries($individualRows, $timelineItems, $startYear, $endYear);

$pdf = new SimplePDF();
$pdf->SetPageSize(210.0, 297.0); // A4 portrait
$pdf->SetMargins(20.0, 20.0, 20.0);
$pdf->SetBottomMargin(20.0);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage('P');
$pdf->SetTextColor(0, 0, 0);

$title = sprintf('Timeline: %d - %d', $startYear, $endYear);
$pdf->SetFont('Helvetica', 'B', 18.0);
$pdf->Cell(0, 10, $title, 0, 'C');
$pdf->Ln(12.0);

if (empty($timelineEntries)) {
    $pdf->SetFont('Helvetica', '', 12.0);
    $pdf->MultiCell(0, 6.0, 'No individual events were found within the requested span.', 0, 'L');
    $pdf->Output('I', buildTimelineFileName($startYear, $endYear));
    exit;
}

$axisTop = 40.0;
$axisBottom = $pdf->GetPageHeight() - 30.0;
$axisX = 70.0;
$axisHeight = max(10.0, $axisBottom - $axisTop);
$yearSpan = max(1, $endYear - $startYear);
$scale = $axisHeight / $yearSpan;

$pdf->SetLineWidth(0.4);
$pdf->Line($axisX, $axisTop, $axisX, $axisBottom);

$pdf->SetFont('Helvetica', '', 9.0);
$tickInterval = determineTickInterval($yearSpan);
$firstTick = (int) (ceil($startYear / $tickInterval) * $tickInterval);
for ($year = $firstTick; $year <= $endYear; $year += $tickInterval) {
    $y = $axisBottom - (($year - $startYear) * $scale);
    if ($y < $axisTop - 2.0 || $y > $axisBottom + 2.0) {
        continue;
    }
    $pdf->Line($axisX - 2.5, $y, $axisX + 2.5, $y);
    $pdf->SetXY($axisX + 4.0, $y - 3.0);
    $pdf->Cell(0, 6.0, (string) $year, 0, 0, 'L');
}

$legendY = $axisTop - 12.0;
$pdf->SetFont('Helvetica', '', 9.5);
$pdf->SetXY($axisX - 50.0, $legendY);
$pdf->Cell(40.0, 5.0, 'Births', 0, 0, 'R');
$pdf->SetXY($axisX + 10.0, $legendY);
$pdf->Cell(40.0, 5.0, 'Deaths', 0, 0, 'L');

usort($timelineEntries, static function (array $a, array $b): int {
    if ($a['year'] === $b['year']) {
        return $a['sort'] <=> $b['sort'];
    }
    return $a['year'] <=> $b['year'];
});

$positionOffsets = [];
$pdf->SetFont('DejaVu Sans', '', 9.5);

foreach ($timelineEntries as $entry) {
    $relativeYear = clampYear($entry['year'], $startYear, $endYear);
    $baseY = $axisBottom - (($relativeYear - $startYear) * $scale);

    $key = $entry['year'] . '_' . $entry['side'];
    $usedCount = $positionOffsets[$key] ?? 0;
    $verticalOffset = $usedCount * 3.5;
    $positionOffsets[$key] = $usedCount + 1;

    $y = $baseY - $verticalOffset;
    if ($y < $axisTop + 5.0) {
        $y = $axisTop + 5.0;
    }
    if ($y > $axisBottom - 5.0) {
        $y = $axisBottom - 5.0;
    }

    $text = sprintf('%s (%d)', $entry['label'], $entry['year']);
    $textWidth = estimateTextWidth($pdf, $text, 9.5);
    $markerLength = 6.0;

    if ($entry['side'] === 'left') {
        $startX = $axisX - $markerLength;
        $pdf->Line($axisX, $y, $startX, $y);
        $pdf->SetXY($startX - $textWidth - 2.0, $y - 3.0);
        $pdf->Cell($textWidth + 2.0, 6.0, $text, 0, 0, 'L');
    } else {
        $endX = $axisX + $markerLength;
        $pdf->Line($axisX, $y, $endX, $y);
        $pdf->SetXY($endX + 2.0, $y - 3.0);
        $pdf->Cell(0, 6.0, $text, 0, 0, 'L');
    }
}

$pdf->Output('I', buildTimelineFileName($startYear, $endYear));
exit;

/**
 * Fetch all individuals with relevant life events within the span.
 *
 * @return array<int,array<string,mixed>>
 */
function fetchIndividualsWithinSpan(Database $db, int $startYear, int $endYear): array
{
    $sql = "
        SELECT id, first_names, last_name, birth_year, death_year
        FROM individuals
        WHERE
            (birth_year IS NOT NULL AND birth_year BETWEEN ? AND ?)
            OR
            (death_year IS NOT NULL AND death_year BETWEEN ? AND ?)
        ORDER BY COALESCE(birth_year, death_year)
    ";

    return $db->fetchAll($sql, [$startYear, $endYear, $startYear, $endYear]);
}

/**
 * Placeholder for future timeline items (events not tied to individuals).
 *
 * @return array<int,array<string,mixed>>
 */
function fetchTimelineItems(int $startYear, int $endYear): array
{
    return [];
}

/**
 * Build timeline entries for individuals and items.
 *
 * @param array<int,array<string,mixed>> $individualRows
 * @param array<int,array<string,mixed>> $items
 * @return array<int,array<string,mixed>>
 */
function buildTimelineEntries(array $individualRows, array $items, int $startYear, int $endYear): array
{
    $entries = [];

    foreach ($individualRows as $row) {
        $label = formatPersonLabel($row);

        if (!empty($row['birth_year']) && (int) $row['birth_year'] >= $startYear && (int) $row['birth_year'] <= $endYear) {
            $entries[] = [
                'year' => (int) $row['birth_year'],
                'side' => 'left',
                'sort' => 0,
                'label' => $label . ' born',
            ];
        }

        if (!empty($row['death_year']) && (int) $row['death_year'] >= $startYear && (int) $row['death_year'] <= $endYear) {
            $entries[] = [
                'year' => (int) $row['death_year'],
                'side' => 'right',
                'sort' => 1,
                'label' => $label . ' died',
            ];
        }
    }

    foreach ($items as $item) {
        // Reserved for future expansion.
        $entries[] = $item;
    }

    return $entries;
}

function determineTickInterval(int $span): int
{
    if ($span <= 20) {
        return 1;
    }
    if ($span <= 50) {
        return 5;
    }
    if ($span <= 120) {
        return 10;
    }
    if ($span <= 300) {
        return 20;
    }
    return 50;
}

function clampYear(int $year, int $startYear, int $endYear): int
{
    return max($startYear, min($endYear, $year));
}

function buildTimelineFileName(int $startYear, int $endYear): string
{
    return sprintf('Timeline_%d_to_%d.pdf', $startYear, $endYear);
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

function formatPersonLabel(array $person): string
{
    $first = isset($person['first_names']) ? cleanNameToken(str_replace('_', ' ', (string) $person['first_names'])) : '';
    $last = isset($person['last_name']) ? cleanNameToken((string) $person['last_name']) : '';
    $name = trim($first . ' ' . $last);
    return $name !== '' ? $name : 'Unknown';
}

function estimateTextWidth(SimplePDF $pdf, string $text, float $fontSize): float
{
    $length = mb_strlen($text, 'UTF-8');
    $averageCharWidth = max(0.12, $fontSize * 0.352778 * 0.5);
    return ($length * $averageCharWidth) + $averageCharWidth;
}
