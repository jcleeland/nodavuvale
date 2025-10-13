<?php
/**
 * A minimal PDF generator supporting text and JPEG images.
 *
 * This class intentionally implements only the features required by the
 * genealogy reports. It is not a drop-in replacement for larger PDF
 * libraries such as FPDF, but it follows a similar workflow: instantiate,
 * add pages, write text and images, then call Output().
 */
class SimplePDF
{
    private const K = 72 / 25.4; // Conversion factor from mm to points.

    private float $pageWidth = 210.0;   // Default A4 portrait dimensions (mm)
    private float $pageHeight = 297.0;
    private float $lMargin = 20.0;
    private float $rMargin = 20.0;
    private float $tMargin = 20.0;
    private float $bMargin = 20.0;

    private array $pages = [];
    private int $currentPage = 0;

    private float $x = 20.0;
    private float $y = 20.0;

    private float $fontSizePt = 12.0;
    private float $lineHeight = 5.0;

    private string $currentFontStyle = '';
    private array $fontDefinitions = [
        '' => '/Helvetica',
        'B' => '/Helvetica-Bold',
    ];
    private array $fontResourceNames = [];
    private array $fontObjectNumbers = [];
    private int $fontCounter = 0;

    private array $textColor = [0.0, 0.0, 0.0];
    private bool $textColorDirty = true;

    private array $fillColor = [0.0, 0.0, 0.0];

    private array $images = [];
    private array $tempImages = [];
    private array $imageCache = [];

    private bool $autoPageBreak = true;

    private bool $pageNumbersEnabled = false;
    private string $pageNumberFormat = 'Page %d';
    private float $pageNumberFontSize = 10.0;
    private array $pageNumberColor = [0, 0, 0];
    private array $pageNumberRendered = [];

    public function __destruct()
    {
        foreach ($this->tempImages as $tmp) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function SetMargins(float $left, float $top, ?float $right = null): void
    {
        $this->lMargin = max(0.0, $left);
        $this->tMargin = max(0.0, $top);
        $this->rMargin = $right === null ? $this->lMargin : max(0.0, $right);
    }

    public function SetBottomMargin(float $bottom): void
    {
        $this->bMargin = max(0.0, $bottom);
    }

    public function SetAutoPageBreak(bool $auto): void
    {
        $this->autoPageBreak = $auto;
    }

    public function EnablePageNumbers(string $format = 'Page %d', float $fontSize = 10.0, ?array $color = null): void
    {
        $this->pageNumbersEnabled = true;
        $this->pageNumberFormat = $format;
        $this->pageNumberFontSize = max(6.0, $fontSize);
        if ($color !== null && count($color) === 3) {
            $this->pageNumberColor = [
                max(0, min(255, (int) $color[0])),
                max(0, min(255, (int) $color[1])),
                max(0, min(255, (int) $color[2])),
            ];
        }
        $this->pageNumberRendered = [];
    }

    public function AddPage(string $orientation = 'P'): void
    {
        if ($this->pageNumbersEnabled && $this->currentPage > 0) {
            $this->finalizePageNumber($this->currentPage);
        }
        $orientation = strtoupper($orientation);
        if ($orientation === 'L') {
            $this->pageWidth = 297.0;
            $this->pageHeight = 210.0;
        } else {
            $this->pageWidth = 210.0;
            $this->pageHeight = 297.0;
        }

        $this->currentPage++;
        $this->pages[$this->currentPage] = [
            'content' => '',
            'images' => [],
            'orientation' => $orientation,
        ];

        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->updateLineHeight();
        $this->textColorDirty = true;
    }

    public function SetFont(string $family, string $style = '', float $size = 12.0): void
    {
        $style = strtoupper($style);
        $style = str_contains($style, 'B') ? 'B' : '';
        $this->ensureFontStyle($style);
        $this->currentFontStyle = $style;
        $this->fontSizePt = max(1.0, $size);
        $this->updateLineHeight();
    }

    public function SetTextColor(int $r, int $g, int $b): void
    {
        $this->textColor = [
            max(0.0, min(1.0, $r / 255)),
            max(0.0, min(1.0, $g / 255)),
            max(0.0, min(1.0, $b / 255)),
        ];
        $this->textColorDirty = true;
    }

    public function SetFillColor(int $r, int $g, int $b): void
    {
        $this->fillColor = [
            max(0.0, min(1.0, $r / 255)),
            max(0.0, min(1.0, $g / 255)),
            max(0.0, min(1.0, $b / 255)),
        ];
    }

    private function updateLineHeight(): void
    {
        // Roughly 1.35 line-height multiplier converted to mm
        $this->lineHeight = $this->fontSizePt * 0.352778 * 1.35;
    }

    private function ensureFontStyle(string $style): void
    {
        if (!isset($this->fontDefinitions[$style])) {
            $style = '';
        }
        if (!isset($this->fontResourceNames[$style])) {
            $this->fontCounter++;
            $this->fontResourceNames[$style] = 'F' . $this->fontCounter;
        }
        if (!isset($this->fontResourceNames[''])) {
            $this->fontCounter++;
            $this->fontResourceNames[''] = 'F' . $this->fontCounter;
        }
    }

    public function Ln(?float $height = null): void
    {
        $this->x = $this->lMargin;
        $this->y += $height === null ? $this->lineHeight : $height;
        $this->checkPageBreak();
    }

    public function SetXY(float $x, float $y): void
    {
        $this->x = $x;
        $this->y = $y;
        $this->checkPageBreak();
    }

    public function SetY(float $y): void
    {
        $this->y = $y;
        $this->checkPageBreak();
    }

    public function UsePage(int $pageNumber): void
    {
        if ($pageNumber < 1 || !isset($this->pages[$pageNumber])) {
            return;
        }
        $this->currentPage = $pageNumber;
        $orientation = $this->pages[$pageNumber]['orientation'] ?? 'P';
        if ($orientation === 'L') {
            $this->pageWidth = 297.0;
            $this->pageHeight = 210.0;
        } else {
            $this->pageWidth = 210.0;
            $this->pageHeight = 297.0;
        }
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->textColorDirty = true;
    }

    public function GetX(): float
    {
        return $this->x;
    }

    public function GetY(): float
    {
        return $this->y;
    }

    public function GetPageWidth(): float
    {
        return $this->pageWidth;
    }

    public function GetPageHeight(): float
    {
        return $this->pageHeight;
    }

    public function GetPageNumber(): int
    {
        return $this->currentPage;
    }

    public function GetLeftMargin(): float
    {
        return $this->lMargin;
    }

    public function GetRightMargin(): float
    {
        return $this->rMargin;
    }

    public function GetTopMargin(): float
    {
        return $this->tMargin;
    }

    public function GetBottomMargin(): float
    {
        return $this->bMargin;
    }

    public function Cell(float $width, float $height = 0.0, string $text = '', int $ln = 0, string $align = 'L'): void
    {
        $height = $height > 0 ? $height : $this->lineHeight;
        $usableWidth = $this->pageWidth - $this->lMargin - $this->rMargin;
        $width = $width > 0 ? $width : $usableWidth;
        $x = $this->x;
        if ($align === 'C') {
            $x = max($this->lMargin, $this->lMargin + ($usableWidth - $width) / 2.0);
        } elseif ($align === 'R') {
            $x = $this->pageWidth - $this->rMargin - $width;
        }

        $this->writeText($text, $x, $this->y + ($height * 0.8));

        if ($ln > 0) {
            $this->x = $this->lMargin;
            $this->y += $height;
            $this->checkPageBreak();
        } else {
            $this->x += $width;
        }
    }

    public function FilledRect(float $x, float $y, float $width, float $height, ?array $color = null): void
    {
        if ($width <= 0 || $height <= 0) {
            return;
        }
        if ($color !== null && count($color) === 3) {
            $this->SetFillColor((int) $color[0], (int) $color[1], (int) $color[2]);
        }
        if ($this->currentPage === 0) {
            $this->AddPage();
        }
        [$r, $g, $b] = $this->fillColor;
        $xPt = $x * self::K;
        $yPt = ($this->pageHeight - $y - $height) * self::K;
        $wPt = $width * self::K;
        $hPt = $height * self::K;
        $this->pages[$this->currentPage]['content'] .= sprintf(
            "q %.3F %.3F %.3F rg %.3F %.3F %.3F %.3F re f Q\n",
            $r,
            $g,
            $b,
            $xPt,
            $yPt,
            $wPt,
            $hPt
        );
    }

    public function MultiCell(float $width, float $height, string $text, string $align = 'L', float $indent = 0.0): void
    {
        $height = $height > 0 ? $height : $this->lineHeight;
        $usableWidth = $this->pageWidth - $this->lMargin - $this->rMargin;
        if ($indent < 0) {
            $indent = 0.0;
        }
        if ($width <= 0 || $width > $usableWidth) {
            $width = $usableWidth;
        }
        if ($align === 'L' && $indent > 0) {
            $maxWidth = max(0.0, $usableWidth - $indent);
            if ($width > $maxWidth) {
                $width = $maxWidth;
            }
        }

        $lines = preg_split("/(\r\n|\r|\n)/", $text);
        if ($lines === false) {
            $lines = [$text];
        }

        foreach ($lines as $line) {
            $chunks = $this->wrapLine($line, $width);
            if (empty($chunks)) {
                $chunks = [''];
            }
            foreach ($chunks as $chunk) {
                if ($align === 'L' && $indent > 0) {
                    $this->x = $this->lMargin + $indent;
                }
                $this->Cell($width, $height, $chunk, 1, $align);
            }
        }
    }

    private function wrapLine(string $line, float $width): array
    {
        $line = trim($line);
        if ($line === '') {
            return [''];
        }
        $usableWidth = $this->pageWidth - $this->lMargin - $this->rMargin;
        if ($width <= 0 || $width > $usableWidth) {
            $width = $usableWidth;
        }
        $charWidth = max(0.1, $this->fontSizePt * 0.352778 * 0.5);
        $maxChars = max(1, (int) floor($width / $charWidth));
        $wrapped = wordwrap($line, $maxChars, "\n", true);
        return explode("\n", $wrapped);
    }

    private function estimateTextWidth(string $text, float $fontSize): float
    {
        $length = mb_strlen($text, 'UTF-8');
        $averageCharWidth = max(0.1, $fontSize * 0.352778 * 0.5);
        return $length * $averageCharWidth;
    }

    private function writeText(string $text, float $x, float $y): void
    {
        if ($this->currentPage === 0) {
            $this->AddPage();
        }
        $this->ensureFontStyle($this->currentFontStyle);
        $pageHeight = $this->pageHeight;
        $yPt = ($pageHeight - $y) * self::K;
        $xPt = $x * self::K;
        $escaped = $this->escape($text);
        if ($this->textColorDirty) {
            [$r, $g, $b] = $this->textColor;
            $this->pages[$this->currentPage]['content'] .= sprintf(
                "%.3F %.3F %.3F rg %.3F %.3F %.3F RG\n",
                $r,
                $g,
                $b,
                $r,
                $g,
                $b
            );
            $this->textColorDirty = false;
        }

        $fontResource = $this->fontResourceNames[$this->currentFontStyle] ?? $this->fontResourceNames[''] ?? 'F1';
        $this->pages[$this->currentPage]['content'] .= sprintf(
            "BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $fontResource,
            $this->fontSizePt,
            $xPt,
            $yPt,
            $escaped
        );
    }

    private function checkPageBreak(): void
    {
        if (!$this->autoPageBreak) {
            return;
        }
        if ($this->y > ($this->pageHeight - $this->bMargin)) {
            $currentOrientation = $this->pages[$this->currentPage]['orientation'] ?? 'P';
            $this->AddPage($currentOrientation);
        }
    }

    private function finalizePageNumber(int $pageIndex): void
    {
        if (!$this->pageNumbersEnabled || $pageIndex < 1) {
            return;
        }
        if (!isset($this->pages[$pageIndex]) || isset($this->pageNumberRendered[$pageIndex])) {
            return;
        }

        $savedPage = $this->currentPage;
        $savedWidth = $this->pageWidth;
        $savedHeight = $this->pageHeight;
        $savedX = $this->x;
        $savedY = $this->y;
        $savedFontSize = $this->fontSizePt;
        $savedStyle = $this->currentFontStyle;
        $savedColor = $this->textColor;

        $orientation = $this->pages[$pageIndex]['orientation'] ?? 'P';
        if ($orientation === 'L') {
            $this->pageWidth = 297.0;
            $this->pageHeight = 210.0;
        } else {
            $this->pageWidth = 210.0;
            $this->pageHeight = 297.0;
        }
        $this->currentPage = $pageIndex;

        $label = sprintf($this->pageNumberFormat, $pageIndex);
        $textWidth = $this->estimateTextWidth($label, $this->pageNumberFontSize);
        $usableWidth = $this->pageWidth - $this->lMargin - $this->rMargin;
        if ($textWidth > $usableWidth) {
            $textWidth = $usableWidth;
        }
        $x = $this->pageWidth - $this->rMargin - $textWidth;
        if ($x < $this->lMargin) {
            $x = $this->lMargin;
        }
        $y = $this->pageHeight - max(5.0, $this->bMargin * 0.6);

        $this->SetFont('Helvetica', '', $this->pageNumberFontSize);
        $this->SetTextColor(
            $this->pageNumberColor[0],
            $this->pageNumberColor[1],
            $this->pageNumberColor[2]
        );
        $this->writeText($label, $x, $y);
        $this->pageNumberRendered[$pageIndex] = true;

        $this->currentPage = $savedPage;
        if ($savedPage > 0) {
            $savedOrientation = $this->pages[$savedPage]['orientation'] ?? 'P';
            if ($savedOrientation === 'L') {
                $this->pageWidth = 297.0;
                $this->pageHeight = 210.0;
            } else {
                $this->pageWidth = 210.0;
                $this->pageHeight = 297.0;
            }
        } else {
            $this->pageWidth = $savedWidth;
            $this->pageHeight = $savedHeight;
        }
        $this->x = $savedX;
        $this->y = $savedY;
        $this->SetFont('Helvetica', $savedStyle, $savedFontSize);
        $this->textColor = $savedColor;
        $this->textColorDirty = true;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    public function Image(string $file, float $x, float $y, float $width = 0.0, float $height = 0.0): void
    {
        $prepared = $this->prepareImage($file);
        if ($prepared === null) {
            return; // Unsupported image type
        }

        [$pixelsWidth, $pixelsHeight] = getimagesize($prepared);
        if ($width <= 0 && $height <= 0) {
            // Default width to 60mm, keep aspect ratio
            $width = 60.0;
            $height = $width * ($pixelsHeight / $pixelsWidth);
        } elseif ($width > 0 && $height <= 0) {
            $height = $width * ($pixelsHeight / $pixelsWidth);
        } elseif ($height > 0 && $width <= 0) {
            $width = $height * ($pixelsWidth / $pixelsHeight);
        }

        $key = md5($prepared);
        if (!isset($this->images[$key])) {
            $data = file_get_contents($prepared);
            if ($data === false) {
                return;
            }
            $this->images[$key] = [
                'name' => 'Im' . (count($this->images) + 1),
                'data' => $data,
                'width_px' => $pixelsWidth,
                'height_px' => $pixelsHeight,
            ];
        }
        $image = $this->images[$key];
        $this->pages[$this->currentPage]['images'][$image['name']] = $key;

        $xPt = $x * self::K;
        $yPt = ($this->pageHeight - $y - $height) * self::K;
        $wPt = $width * self::K;
        $hPt = $height * self::K;
        $this->pages[$this->currentPage]['content'] .= sprintf(
            "q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
            $wPt,
            $hPt,
            $xPt,
            $yPt,
            $image['name']
        );
    }

    private function prepareImage(string $path): ?string
    {
        $path = $this->resolvePath($path);
        if ($path === null || !is_file($path)) {
            return null;
        }
        if (isset($this->imageCache[$path])) {
            return $this->imageCache[$path];
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg'])) {
            $this->imageCache[$path] = $path;
            return $path;
        }
        $imageContent = @file_get_contents($path);
        if ($imageContent === false) {
            return null;
        }
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $resource = @imagecreatefromstring($imageContent);
        if (!$resource) {
            return null;
        }
        $tempFile = tempnam(sys_get_temp_dir(), 'nvpdf_');
        if ($tempFile === false) {
            imagedestroy($resource);
            return null;
        }
        $tempFile .= '.jpg';
        if (!imagejpeg($resource, $tempFile, 85)) {
            imagedestroy($resource);
            return null;
        }
        imagedestroy($resource);
        $this->tempImages[] = $tempFile;
        $this->imageCache[$path] = $tempFile;
        return $tempFile;
    }

    private function resolvePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null; // Remote images are not supported in this minimal implementation
        }
        $full = realpath(__DIR__ . '/../' . ltrim($path, '/'));
        if ($full !== false && is_file($full)) {
            return $full;
        }
        $full = realpath(__DIR__ . '/../..' . '/' . ltrim($path, '/'));
        if ($full !== false && is_file($full)) {
            return $full;
        }
        $full = realpath($path);
        if ($full !== false && is_file($full)) {
            return $full;
        }
        return null;
    }

    public function Output(string $dest = 'I', string $name = 'document.pdf'): ?string
    {
        $content = $this->buildDocument();
        if ($dest === 'S') {
            return $content;
        }
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($content));
            header('Content-Disposition: ' . ($dest === 'D' ? 'attachment' : 'inline') . '; filename="' . $name . '"');
        }
        echo $content;
        return null;
    }

    private function buildDocument(): string
    {
        if (empty($this->pages)) {
            $this->AddPage();
        }
        if ($this->pageNumbersEnabled && $this->currentPage > 0) {
            $this->finalizePageNumber($this->currentPage);
        }
        $this->fontObjectNumbers = [];
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '';

        $this->ensureFontStyle($this->currentFontStyle);
        $nextObjNum = 2;

        foreach ($this->fontResourceNames as $style => $name) {
            $nextObjNum++;
            $base = $this->fontDefinitions[$style] ?? '/Helvetica';
            $objects[$nextObjNum] = '<< /Type /Font /Subtype /Type1 /BaseFont ' . $base . ' >>';
            $this->fontObjectNumbers[$style] = $nextObjNum;
        }

        $nextObjNum = max(array_keys($objects));
        foreach ($this->images as $key => $image) {
            $nextObjNum++;
            $this->images[$key]['objNum'] = $nextObjNum;
            $objects[$nextObjNum] = $this->buildImageObject($image);
        }

        $kids = [];
        foreach ($this->pages as $index => $page) {
            $content = $page['content'];
            $nextObjNum++;
            $contentObj = $nextObjNum;
            $objects[$contentObj] = $this->buildContentObject($content);

            $nextObjNum++;
            $pageObj = $nextObjNum;
            $objects[$pageObj] = $this->buildPageObject($page, $contentObj);
            $kids[] = $pageObj . ' 0 R';
            $this->pages[$index]['pageObj'] = $pageObj;
        }

        $objects[2] = '<< /Type /Pages /Count ' . count($kids) . ' /Kids [' . implode(' ', $kids) . '] >>';

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $obj . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $maxObj = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObj; $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

        return $pdf;
    }

    private function buildImageObject(array $image): string
    {
        $length = strlen($image['data']);
        $dict = '<< /Type /XObject /Subtype /Image /Width ' . $image['width_px'] . ' /Height ' . $image['height_px'] . ' ' .
            '/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . $length . ' >>';
        return $dict . "\nstream\n" . $image['data'] . "\nendstream";
    }

    private function buildContentObject(string $content): string
    {
        $length = strlen($content);
        return '<< /Length ' . $length . " >>\nstream\n" . $content . "endstream";
    }

    private function buildPageObject(array $page, int $contentObj): string
    {
        $fontEntries = [];
        foreach ($this->fontResourceNames as $style => $name) {
            $objNum = $this->fontObjectNumbers[$style] ?? null;
            if ($objNum === null) {
                continue;
            }
            $fontEntries[] = '/' . $name . ' ' . $objNum . ' 0 R';
        }
        if (empty($fontEntries)) {
            $fontEntries[] = '/F1 3 0 R';
        }
        $resources = '<< /Font << ' . implode(' ', $fontEntries) . ' >>';
        if (!empty($page['images'])) {
            $resources .= ' /XObject << ';
            foreach ($page['images'] as $name => $key) {
                $objNum = $this->images[$key]['objNum'];
                $resources .= '/' . $name . ' ' . $objNum . ' 0 R ';
            }
            $resources .= '>>';
        }
        $resources .= ' >>';
        $mediaBox = '[0 0 ' . ($this->pageWidth * self::K) . ' ' . ($this->pageHeight * self::K) . ']';
        return '<< /Type /Page /Parent 2 0 R /MediaBox ' . $mediaBox . ' /Resources ' . $resources . ' /Contents ' . $contentObj . ' 0 R >>';
    }
}