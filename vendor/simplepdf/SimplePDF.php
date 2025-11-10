
<?php
/**
 * A minimal PDF generator supporting text, embedded TrueType fonts, and JPEG images.
 *
 * This class intentionally implements only the features required by the genealogy
 * reports. It keeps the original API surface so existing code continues to work,
 * but adds optional TrueType font embedding so that Unicode glyphs (e.g. gender
 * symbols) and monospaced output are supported.
 */
class SimplePDF
{
    private const K = 72 / 25.4; // Conversion factor from mm to points.

    private float $pageWidth = 210.0;
    private float $pageHeight = 297.0;
    private float $basePageWidth = 210.0;
    private float $basePageHeight = 297.0;
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

    private string $currentFontKey = '';
    private array $fontCatalog = [];
    private array $fontResources = [];
    private array $fontObjects = [];
    private int $fontCounter = 0;

    private array $textColor = [0.0, 0.0, 0.0];
    private bool $textColorDirty = true;

    private array $fillColor = [0.0, 0.0, 0.0];
    private float $lineWidth = 0.2;

    private array $images = [];
    private array $tempImages = [];
    private array $imageCache = [];

    private bool $autoPageBreak = true;

    private bool $pageNumbersEnabled = false;
    private string $pageNumberFormat = 'Page %d';
    private float $pageNumberFontSize = 10.0;
    private array $pageNumberColor = [0, 0, 0];
    private array $pageNumberRendered = [];
    private ?int $pageNumberStartIndex = null;
    private int $pageNumberStartValue = 1;

    public function __construct()
    {
        $this->initializeFonts();
        $this->SetFont('Helvetica', '', $this->fontSizePt);
    }

    public function __destruct()
    {
        foreach ($this->tempImages as $tmp) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function initializeFonts(): void
    {
        $this->registerCoreFont('Helvetica', '', 'Helvetica');
        $this->registerCoreFont('Helvetica', 'B', 'Helvetica-Bold');
        $this->registerCoreFont('Helvetica', 'I', 'Helvetica-Oblique');
        $this->registerCoreFont('Helvetica', 'BI', 'Helvetica-BoldOblique');
        $this->registerCoreFont('Courier', '', 'Courier', true);
        $this->registerCoreFont('Courier', 'B', 'Courier-Bold', true);
        $this->registerCoreFont('Courier', 'I', 'Courier-Oblique', true);
        $this->registerCoreFont('Courier', 'BI', 'Courier-BoldOblique', true);
        $this->registerCoreFont('Symbol', '', 'Symbol');
        $this->registerCoreFont('ZapfDingbats', '', 'ZapfDingbats');

        $this->registerBundledTrueTypeFonts();
    }

    private function registerBundledTrueTypeFonts(): void
    {
        $fontDir = __DIR__ . '/fonts';
        $bundled = [
            ['family' => 'DejaVu Sans', 'style' => '', 'file' => $fontDir . '/DejaVuSans.ttf', 'mono' => false],
            ['family' => 'DejaVu Sans', 'style' => 'B', 'file' => $fontDir . '/DejaVuSans-Bold.ttf', 'mono' => false],
            ['family' => 'DejaVu Sans Mono', 'style' => '', 'file' => $fontDir . '/DejaVuSansMono.ttf', 'mono' => true],
            ['family' => 'Font Awesome 6 Free', 'style' => '', 'file' => $fontDir . '/fa-solid-900.ttf', 'mono' => false],
        ];

        foreach ($bundled as $bundle) {
            if (!is_file($bundle['file'])) {
                continue;
            }
            $this->registerTrueTypeFont(
                $bundle['family'],
                $bundle['style'],
                $bundle['file'],
                (bool) $bundle['mono']
            );
        }
    }

    private function registerCoreFont(string $family, string $style, string $baseFont, bool $monospaced = false): void
    {
        $key = $this->fontKey($family, $style);
        if (isset($this->fontCatalog[$key])) {
            return;
        }
        $this->fontCatalog[$key] = [
            'type' => 'core',
            'family' => $family,
            'style' => $this->normalizeStyle($style),
            'baseFont' => '/' . ltrim($baseFont, '/'),
            'resource' => null,
            'used' => false,
            'isMono' => $monospaced,
        ];
    }

    private function registerTrueTypeFont(string $family, string $style, string $filePath, bool $monospaced = false): void
    {
        $key = $this->fontKey($family, $style);
        if (isset($this->fontCatalog[$key])) {
            return;
        }

        $parsed = $this->parseTrueTypeFont($filePath);
        if ($parsed === null) {
            return;
        }

        $this->fontCatalog[$key] = array_merge($parsed, [
            'type' => 'truetype',
            'family' => $family,
            'style' => $this->normalizeStyle($style),
            'resource' => null,
            'used' => false,
            'isMono' => $monospaced,
            'usedChars' => [],
            'cidToGlyph' => [],
            'cidWidths' => [],
            'toUnicode' => [],
            'nextCid' => 1,
        ]);
    }

    private function fontKey(string $family, string $style): string
    {
        return strtolower(trim($family)) . ':' . $this->normalizeStyle($style);
    }

    private function normalizeStyle(string $style): string
    {
        $style = strtoupper($style);
        $flags = [];
        if (str_contains($style, 'B')) {
            $flags[] = 'B';
        }
        if (str_contains($style, 'I')) {
            $flags[] = 'I';
        }
        return implode('', $flags);
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

    public function SetLineWidth(float $width): void
    {
        $this->lineWidth = max(0.01, $width);
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

    public function SetPageNumberingStart(int $pageIndex, int $startAt = 1): void
    {
        $this->pageNumberStartIndex = max(1, $pageIndex);
        $this->pageNumberStartValue = max(0, $startAt);
    }

    public function SetPageSize(float $width, float $height): void
    {
        $this->basePageWidth = max(10.0, $width);
        $this->basePageHeight = max(10.0, $height);
    }

    public function AddPage(string $orientation = 'P'): void
    {
        if ($this->pageNumbersEnabled && $this->currentPage > 0) {
            $this->finalizePageNumber($this->currentPage);
        }
        $orientation = strtoupper($orientation);
        $portraitWidth = $this->basePageWidth;
        $portraitHeight = $this->basePageHeight;
        if ($orientation === 'L') {
            $this->pageWidth = $portraitHeight;
            $this->pageHeight = $portraitWidth;
        } else {
            $this->pageWidth = $portraitWidth;
            $this->pageHeight = $portraitHeight;
        }

        $this->currentPage++;
        $this->pages[$this->currentPage] = [
            'content' => '',
            'images' => [],
            'orientation' => $orientation,
            'annots' => [],
            'lineWidth' => null,
        ];

        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->updateLineHeight();
        $this->textColorDirty = true;
    }

    public function SetFont(string $family, string $style = '', float $size = 12.0): void
    {
        $family = trim($family) !== '' ? $family : 'Helvetica';
        $style = $this->normalizeStyle($style);
        $key = $this->fontKey($family, $style);

        if (!isset($this->fontCatalog[$key])) {
            $plainKey = $this->fontKey($family, '');
            if (isset($this->fontCatalog[$plainKey])) {
                $key = $plainKey;
            } else {
                $key = $this->fontKey('Helvetica', '');
            }
        }

        $this->currentFontKey = $key;
        $this->fontSizePt = max(1.0, $size);
        $this->updateLineHeight();
        $this->ensureFontResource($key);
    }

    public function AddTrueTypeFont(string $family, string $style, string $filePath, bool $isMonospaced = false): bool
    {
        $before = count($this->fontCatalog);
        $this->registerTrueTypeFont($family, $style, $filePath, $isMonospaced);
        return count($this->fontCatalog) > $before;
    }
    private function ensureFontResource(string $fontKey): void
    {
        if (!isset($this->fontCatalog[$fontKey])) {
            return;
        }
        if (!isset($this->fontResources[$fontKey])) {
            $this->fontCounter++;
            $resourceName = 'F' . $this->fontCounter;
            $this->fontResources[$fontKey] = $resourceName;
            $this->fontCatalog[$fontKey]['resource'] = $resourceName;
        }
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
        $this->lineHeight = $this->fontSizePt * 0.352778 * 1.35;
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
        $portraitWidth = $this->basePageWidth;
        $portraitHeight = $this->basePageHeight;
        if ($orientation === 'L') {
            $this->pageWidth = $portraitHeight;
            $this->pageHeight = $portraitWidth;
        } else {
            $this->pageWidth = $portraitWidth;
            $this->pageHeight = $portraitHeight;
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

    public function GetPageNumber(): int
    {
        return $this->currentPage;
    }

    public function PageNo(): int
    {
        return $this->GetPageNumber();
    }

    public function GetPageWidth(): float
    {
        return $this->pageWidth;
    }

    public function FilledRect(float $x, float $y, float $width, float $height, ?array $color = null): void
    {
        if ($width <= 0.0 || $height <= 0.0) {
            return;
        }
        if ($this->currentPage === 0) {
            $this->AddPage();
        }

        $fill = $this->fillColor;
        if (is_array($color) && count($color) === 3) {
            $fill = [
                max(0.0, min(1.0, (int) $color[0] / 255)),
                max(0.0, min(1.0, (int) $color[1] / 255)),
                max(0.0, min(1.0, (int) $color[2] / 255)),
            ];
        }

        $xPt = $x * self::K;
        $yPt = ($this->pageHeight - $y - $height) * self::K;
        $wPt = $width * self::K;
        $hPt = $height * self::K;

        $this->pages[$this->currentPage]['content'] .= sprintf(
            "%.3F %.3F %.3F rg %.3F %.3F %.3F RG %.3F %.3F %.3F %.3F re f\n",
            $fill[0],
            $fill[1],
            $fill[2],
            $fill[0],
            $fill[1],
            $fill[2],
            $xPt,
            $yPt,
            $wPt,
            $hPt
        );

        $this->textColorDirty = true;
    }

    public function RoundedRect(
        float $x,
        float $y,
        float $width,
        float $height,
        float $radius = 3.0,
        ?array $strokeColor = null,
        float $strokeWidth = 0.4,
        ?array $fillColor = null
    ): void {
        if ($width <= 0.0 || $height <= 0.0) {
            return;
        }
        if ($this->currentPage === 0) {
            $this->AddPage();
        }

        $radius = max(0.0, min($radius, min($width, $height) / 2.0));
        $k = self::K;
        $left = $x * $k;
        $right = ($x + $width) * $k;
        $top = ($this->pageHeight - $y) * $k;
        $bottom = ($this->pageHeight - $y - $height) * $k;
        $r = $radius * $k;
        $c = $r * 0.5522847498;

        $path = sprintf("%.3F %.3F m ", $left + $r, $top);
        $path .= sprintf("%.3F %.3F l ", $right - $r, $top);
        $path .= sprintf("%.3F %.3F %.3F %.3F %.3F %.3F c ", $right - $r + $c, $top, $right, $top - $r + $c, $right, $top - $r);
        $path .= sprintf("%.3F %.3F l ", $right, $bottom + $r);
        $path .= sprintf("%.3F %.3F %.3F %.3F %.3F %.3F c ", $right, $bottom + $r - $c, $right - $r + $c, $bottom, $right - $r, $bottom);
        $path .= sprintf("%.3F %.3F l ", $left + $r, $bottom);
        $path .= sprintf("%.3F %.3F %.3F %.3F %.3F %.3F c ", $left + $r - $c, $bottom, $left, $bottom + $r - $c, $left, $bottom + $r);
        $path .= sprintf("%.3F %.3F l ", $left, $top - $r);
        $path .= sprintf("%.3F %.3F %.3F %.3F %.3F %.3F c ", $left, $top - $r + $c, $left + $r - $c, $top, $left + $r, $top);
        $path .= "h\n";

        $cmd = "q ";

        if (is_array($fillColor) && count($fillColor) === 3) {
            $fill = [
                max(0.0, min(1.0, (int) $fillColor[0] / 255)),
                max(0.0, min(1.0, (int) $fillColor[1] / 255)),
                max(0.0, min(1.0, (int) $fillColor[2] / 255)),
            ];
            $cmd .= sprintf("%.3F %.3F %.3F rg ", $fill[0], $fill[1], $fill[2]);
        } else {
            $fill = null;
        }

        if (is_array($strokeColor) && count($strokeColor) === 3) {
            $stroke = [
                max(0.0, min(1.0, (int) $strokeColor[0] / 255)),
                max(0.0, min(1.0, (int) $strokeColor[1] / 255)),
                max(0.0, min(1.0, (int) $strokeColor[2] / 255)),
            ];
        } else {
            $stroke = $this->textColor;
        }

        $strokeWidth = max(0.01, $strokeWidth);
        $cmd .= sprintf("%.3F %.3F %.3F RG %.3F w ", $stroke[0], $stroke[1], $stroke[2], $strokeWidth * self::K);

        if ($fill !== null && $stroke !== null) {
            $operator = 'B';
        } elseif ($fill !== null) {
            $operator = 'f';
        } else {
            $operator = 'S';
        }

        $cmd .= $path . $operator . "\nQ\n";
        $this->pages[$this->currentPage]['content'] .= $cmd;
    }

    public function GetPageHeight(): float
    {
        return $this->pageHeight;
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

        $baseX = $this->x;

        foreach ($lines as $line) {
            $chunks = $this->wrapLine($line, $width);
            if (empty($chunks)) {
                $chunks = [''];
            }
            foreach ($chunks as $chunk) {
                if ($align === 'L') {
                    $this->x = $baseX + max(0.0, $indent);
                }
                $this->Cell($width, $height, $chunk, 1, $align);
            }
        }
    }

    public function Line(float $x1, float $y1, float $x2, float $y2): void
    {
        if ($this->currentPage === 0) {
            $this->AddPage();
        }
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
        $currentLineWidth = $this->pages[$this->currentPage]['lineWidth'] ?? null;
        if ($currentLineWidth === null || abs($currentLineWidth - $this->lineWidth) > 1e-6) {
            $this->pages[$this->currentPage]['content'] .= sprintf(
                "%.3F w\n",
                $this->lineWidth * self::K
            );
            $this->pages[$this->currentPage]['lineWidth'] = $this->lineWidth;
        }
        $x1Pt = $x1 * self::K;
        $y1Pt = ($this->pageHeight - $y1) * self::K;
        $x2Pt = $x2 * self::K;
        $y2Pt = ($this->pageHeight - $y2) * self::K;
        $this->pages[$this->currentPage]['content'] .= sprintf(
            "%.3F %.3F m %.3F %.3F l S\n",
            $x1Pt,
            $y1Pt,
            $x2Pt,
            $y2Pt
        );
    }

    public function AddUriAnnotation(float $x, float $y, float $w, float $h, string $uri): void
    {
        if ($this->currentPage === 0) {
            $this->AddPage();
        }
        if ($w <= 0 || $h <= 0) {
            return;
        }
        $this->pages[$this->currentPage]['annots'][] = [
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
            'uri' => $uri,
        ];
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

        $font = $this->fontCatalog[$this->currentFontKey] ?? null;
        if ($font !== null && $font['type'] === 'truetype') {
            return $this->wrapLineTruetype($line, $width, $font);
        }

        $charWidth = max(0.1, $this->fontSizePt * 0.352778 * 0.5);
        $maxChars = max(1, (int) floor($width / $charWidth));
        $wrapped = wordwrap($line, $maxChars, "\n", true);
        return explode("\n", $wrapped);
    }

    private function wrapLineTruetype(string $line, float $width, array $font): array
    {
        $chunks = [];
        $current = '';
        $currentWidth = 0.0;
        $words = preg_split('/(\s+)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($words === false) {
            return [$line];
        }

        foreach ($words as $word) {
            $wordWidth = $this->estimateTextWidthForFont($word, $font);
            if ($current === '') {
                $current = $word;
                $currentWidth = $wordWidth;
                continue;
            }
            if ($currentWidth + $wordWidth <= $width || trim($word) === '') {
                $current .= $word;
                $currentWidth += $wordWidth;
                continue;
            }
            $chunks[] = $current;
            $current = ltrim($word);
            $currentWidth = $this->estimateTextWidthForFont($current, $font);
        }
        if ($current !== '') {
            $chunks[] = $current;
        }
        return empty($chunks) ? [''] : $chunks;
    }
    public function Image(string $file, float $x, float $y, float $width = 0.0, float $height = 0.0): void
    {
        $prepared = $this->prepareImage($file);
        if ($prepared === null) {
            return;
        }

        [$pixelsWidth, $pixelsHeight] = getimagesize($prepared);
        if ($width <= 0 && $height <= 0) {
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
        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            $corrected = $this->correctJpegOrientation($path);
            $this->imageCache[$path] = $corrected;
            return $corrected;
        }
        $imageContent = @file_get_contents($path);
        if ($imageContent === false || !function_exists('imagecreatefromstring')) {
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

    private function correctJpegOrientation(string $path): string
    {
        if (!function_exists('exif_read_data') || !function_exists('imagecreatefromjpeg')) {
            return $path;
        }
        $exif = @exif_read_data($path);
        if ($exif === false) {
            return $path;
        }
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if ($orientation === 1) {
            return $path;
        }
        $resource = @imagecreatefromjpeg($path);
        if (!$resource) {
            return $path;
        }
        $transformed = false;
        $canFlip = function_exists('imageflip');
        switch ($orientation) {
            case 2:
                if ($canFlip) {
                    imageflip($resource, defined('IMG_FLIP_HORIZONTAL') ? IMG_FLIP_HORIZONTAL : 1);
                    $transformed = true;
                }
                break;
            case 3:
                if (function_exists('imagerotate')) {
                    $rotated = imagerotate($resource, 180, 0);
                    if ($rotated !== false) {
                        imagedestroy($resource);
                        $resource = $rotated;
                        $transformed = true;
                    }
                }
                break;
            case 4:
                if ($canFlip) {
                    imageflip($resource, defined('IMG_FLIP_VERTICAL') ? IMG_FLIP_VERTICAL : 2);
                    $transformed = true;
                }
                break;
            case 5:
                if ($canFlip) {
                    imageflip($resource, defined('IMG_FLIP_HORIZONTAL') ? IMG_FLIP_HORIZONTAL : 1);
                    $transformed = true;
                }
                if (function_exists('imagerotate')) {
                    $rotated = imagerotate($resource, -90, 0);
                    if ($rotated !== false) {
                        imagedestroy($resource);
                        $resource = $rotated;
                        $transformed = true;
                    }
                }
                break;
            case 6:
                if (function_exists('imagerotate')) {
                    $rotated = imagerotate($resource, -90, 0);
                    if ($rotated !== false) {
                        imagedestroy($resource);
                        $resource = $rotated;
                        $transformed = true;
                    }
                }
                break;
            case 7:
                if ($canFlip) {
                    imageflip($resource, defined('IMG_FLIP_HORIZONTAL') ? IMG_FLIP_HORIZONTAL : 1);
                    $transformed = true;
                }
                if (function_exists('imagerotate')) {
                    $rotated = imagerotate($resource, 90, 0);
                    if ($rotated !== false) {
                        imagedestroy($resource);
                        $resource = $rotated;
                        $transformed = true;
                    }
                }
                break;
            case 8:
                if (function_exists('imagerotate')) {
                    $rotated = imagerotate($resource, 90, 0);
                    if ($rotated !== false) {
                        imagedestroy($resource);
                        $resource = $rotated;
                        $transformed = true;
                    }
                }
                break;
        }
        if (!$transformed) {
            imagedestroy($resource);
            return $path;
        }
        $tempFile = tempnam(sys_get_temp_dir(), 'nvpdf_orient_');
        if ($tempFile === false) {
            imagedestroy($resource);
            return $path;
        }
        $tempFile .= '.jpg';
        if (!imagejpeg($resource, $tempFile, 90)) {
            imagedestroy($resource);
            @unlink($tempFile);
            return $path;
        }
        imagedestroy($resource);
        $this->tempImages[] = $tempFile;
        return $tempFile;
    }

    private function resolvePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
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
            header(
                'Content-Disposition: ' .
                ($dest === 'D' ? 'attachment' : 'inline') .
                '; filename="' . $name . '"'
            );
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

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '';

        $nextObjNum = 2;
        $this->appendFontObjects($objects, $nextObjNum);

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

            $annotRefs = [];
            if (!empty($page['annots'])) {
                foreach ($page['annots'] as $annot) {
                    $nextObjNum++;
                    $annotObj = $nextObjNum;
                    $objects[$annotObj] = $this->buildLinkAnnotationObject($annot);
                    $annotRefs[] = $annotObj . ' 0 R';
                }
            }

            $nextObjNum++;
            $pageObj = $nextObjNum;
            $objects[$pageObj] = $this->buildPageObject($page, $contentObj, $annotRefs);
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

    private function appendFontObjects(array &$objects, int &$nextObjNum): void
    {
        foreach ($this->fontResources as $fontKey => $resourceName) {
            $font = $this->fontCatalog[$fontKey] ?? null;
            if ($font === null) {
                continue;
            }
            if ($font['type'] === 'core') {
                $nextObjNum++;
                $objects[$nextObjNum] = '<< /Type /Font /Subtype /Type1 /BaseFont ' . $font['baseFont'] . ' >>';
                $this->fontObjects[$fontKey] = $nextObjNum;
            } elseif (!empty($font['usedChars'])) {
                $this->appendTrueTypeFontObjects($fontKey, $objects, $nextObjNum);
            }
        }
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

    private function buildPageObject(array $page, int $contentObj, array $annotRefs = []): string
    {
        $fontEntries = [];
        foreach ($this->fontResources as $fontKey => $resourceName) {
            $objNum = $this->fontObjects[$fontKey] ?? null;
            if ($objNum === null) {
                continue;
            }
            $fontEntries[] = '/' . $resourceName . ' ' . $objNum . ' 0 R';
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
        $annotsSection = '';
        if (!empty($annotRefs)) {
            $annotsSection = ' /Annots [' . implode(' ', $annotRefs) . ']';
        }
        return '<< /Type /Page /Parent 2 0 R /MediaBox ' . $mediaBox . ' /Resources ' . $resources . ' /Contents ' . $contentObj . ' 0 R' . $annotsSection . ' >>';
    }

    private function buildLinkAnnotationObject(array $annot): string
    {
        $x = (float) ($annot['x'] ?? 0.0);
        $y = (float) ($annot['y'] ?? 0.0);
        $w = (float) ($annot['w'] ?? 0.0);
        $h = (float) ($annot['h'] ?? 0.0);
        $uri = (string) ($annot['uri'] ?? '');
        $x1 = $x * self::K;
        $y1 = ($this->pageHeight - $y - $h) * self::K;
        $x2 = ($x + $w) * self::K;
        $y2 = ($this->pageHeight - $y) * self::K;
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $uri);
        return '<< /Type /Annot /Subtype /Link /Rect [' . sprintf('%.2F %.2F %.2F %.2F', $x1, $y1, $x2, $y2) . '] /Border [0 0 0] /A << /S /URI /URI (' . $escaped . ') >> >>';
    }
    private function writeText(string $text, float $x, float $y): void
    {
        if ($this->currentPage === 0) {
            $this->AddPage();
        }
        $font = $this->fontCatalog[$this->currentFontKey] ?? null;
        if ($font === null) {
            return;
        }
        $this->ensureFontResource($this->currentFontKey);
        $resource = $this->fontResources[$this->currentFontKey] ?? 'F1';

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

        $xPt = $x * self::K;
        $yPt = ($this->pageHeight - $y) * self::K;

        if ($font['type'] === 'core') {
            $escaped = $this->escapeCoreText($text);
            $this->pages[$this->currentPage]['content'] .= sprintf(
                "BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
                $resource,
                $this->fontSizePt,
                $xPt,
                $yPt,
                $escaped
            );
            $this->fontCatalog[$this->currentFontKey]['used'] = true;
            return;
        }

        $encoded = $this->encodeTextToHex($this->currentFontKey, $text);
        if ($encoded === '') {
            return;
        }
        $this->pages[$this->currentPage]['content'] .= sprintf(
            "BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm <%s> Tj ET\n",
            $resource,
            $this->fontSizePt,
            $xPt,
            $yPt,
            $encoded
        );
        $this->fontCatalog[$this->currentFontKey]['used'] = true;
    }

    private function escapeCoreText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
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
        $savedFontKey = $this->currentFontKey;
        $savedColor = $this->textColor;

        $orientation = $this->pages[$pageIndex]['orientation'] ?? 'P';
        $portraitWidth = $this->basePageWidth;
        $portraitHeight = $this->basePageHeight;
        if ($orientation === 'L') {
            $this->pageWidth = $portraitHeight;
            $this->pageHeight = $portraitWidth;
        } else {
            $this->pageWidth = $portraitWidth;
            $this->pageHeight = $portraitHeight;
        }
        $this->currentPage = $pageIndex;

        if ($this->pageNumberStartIndex !== null && $pageIndex < $this->pageNumberStartIndex) {
            return;
        }
        $displayNumber = $pageIndex;
        if ($this->pageNumberStartIndex !== null) {
            $displayNumber = $this->pageNumberStartValue + ($pageIndex - $this->pageNumberStartIndex);
        }
        $label = sprintf($this->pageNumberFormat, $displayNumber);
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
                $this->pageWidth = $portraitHeight;
                $this->pageHeight = $portraitWidth;
            } else {
                $this->pageWidth = $portraitWidth;
                $this->pageHeight = $portraitHeight;
            }
        } else {
            $this->pageWidth = $savedWidth;
            $this->pageHeight = $savedHeight;
        }
        $this->x = $savedX;
        $this->y = $savedY;
        if ($savedFontKey !== '') {
            $fontMeta = $this->fontCatalog[$savedFontKey] ?? null;
            if ($fontMeta !== null) {
                $this->SetFont($fontMeta['family'], $fontMeta['style'], $savedFontSize);
            } else {
                $this->SetFont('Helvetica', '', $savedFontSize);
            }
        } else {
            $this->SetFont('Helvetica', '', $savedFontSize);
        }
        $this->textColor = $savedColor;
        $this->textColorDirty = true;
    }
    private function estimateTextWidth(string $text, float $fontSize): float
    {
        $font = $this->fontCatalog[$this->currentFontKey] ?? null;
        if ($font === null || $font['type'] === 'core') {
            $length = mb_strlen($text, 'UTF-8');
            $averageCharWidth = max(0.1, $fontSize * 0.352778 * 0.5);
            return ($length * $averageCharWidth) + $averageCharWidth;
        }
        return $this->estimateTextWidthForFont($text, $font);
    }

    private function estimateTextWidthForFont(string $text, array $font): float
    {
        $codepoints = $this->utf8ToCodepoints($text);
        if (empty($codepoints)) {
            return 0.0;
        }
        $width = 0.0;
        foreach ($codepoints as $cp) {
            $glyphId = $font['cmap'][$cp] ?? $font['cmap'][0x3F] ?? null;
            if ($glyphId === null) {
                continue;
            }
            $glyphWidth = $font['glyphWidths'][$glyphId] ?? $font['defaultWidth'];
            $width += ($glyphWidth / 1000.0) * ($this->fontSizePt ?? 12.0);
        }
        return max(0.0, $width * 0.352778);
    }

    private function encodeTextToHex(string $fontKey, string $text): string
    {
        $font = &$this->fontCatalog[$fontKey];
        $codepoints = $this->utf8ToCodepoints($text);
        if (empty($codepoints)) {
            return '';
        }
        $bytes = '';
        foreach ($codepoints as $codepoint) {
            $cid = $this->mapCodepointToCid($fontKey, $codepoint);
            if ($cid <= 0) {
                continue;
            }
            $bytes .= pack('n', $cid);
        }
        return strtoupper(bin2hex($bytes));
    }

    private function mapCodepointToCid(string $fontKey, int $codepoint): int
    {
        $font = &$this->fontCatalog[$fontKey];
        $cmap = $font['cmap'] ?? [];
        if (!isset($cmap[$codepoint])) {
            $codepoint = 0x3F;
            if (!isset($cmap[$codepoint])) {
                return 0;
            }
        }
        if (isset($font['usedChars'][$codepoint])) {
            return $font['usedChars'][$codepoint];
        }
        $cid = $font['nextCid'];
        $font['nextCid']++;

        $glyphId = $cmap[$codepoint];
        $font['usedChars'][$codepoint] = $cid;
        $font['cidToGlyph'][$cid] = $glyphId;
        $font['cidWidths'][$cid] = $font['glyphWidths'][$glyphId] ?? $font['defaultWidth'];
        $font['toUnicode'][$cid] = $codepoint;
        return $cid;
    }
    private function appendTrueTypeFontObjects(string $fontKey, array &$objects, int &$nextObjNum): void
    {
        $font = &$this->fontCatalog[$fontKey];

        $nextObjNum++;
        $fontFileObj = $nextObjNum;
        $objects[$fontFileObj] = $this->buildFontFileObject($font);

        $nextObjNum++;
        $descriptorObj = $nextObjNum;
        $objects[$descriptorObj] = $this->buildFontDescriptorObject($font, $fontFileObj);

        $cidToGidObj = null;
        if (!empty($font['cidToGlyph'])) {
            $nextObjNum++;
            $cidToGidObj = $nextObjNum;
            $objects[$cidToGidObj] = $this->buildCidToGidObject($font);
        }

        $toUnicodeObj = null;
        if (!empty($font['toUnicode'])) {
            $nextObjNum++;
            $toUnicodeObj = $nextObjNum;
            $objects[$toUnicodeObj] = $this->buildToUnicodeObject($font);
        }

        $nextObjNum++;
        $cidFontObj = $nextObjNum;
        $objects[$cidFontObj] = $this->buildCidFontObject($font, $descriptorObj, $cidToGidObj);

        $nextObjNum++;
        $type0Obj = $nextObjNum;
        $objects[$type0Obj] = $this->buildType0FontObject($font, $cidFontObj, $toUnicodeObj);

        $this->fontObjects[$fontKey] = $type0Obj;
    }

    private function buildFontFileObject(array $font): string
    {
        $data = $font['fontData'];
        $length = strlen($data);
        return '<< /Length ' . $length . ' /Length1 ' . $length . " >>\nstream\n" . $data . "\nendstream";
    }

    private function buildFontDescriptorObject(array $font, int $fontFileObj): string
    {
        $flags = 32;
        if (!empty($font['isMono'])) {
            $flags |= 1;
        }
        if (abs($font['italicAngle']) > 0.1) {
            $flags |= 64;
        }

        $bbox = implode(' ', $font['bbox']);
        return '<< /Type /FontDescriptor /FontName /' . $font['postScriptName'] .
            ' /Flags ' . $flags .
            ' /FontBBox [' . $bbox . ']' .
            ' /ItalicAngle ' . sprintf('%.2F', $font['italicAngle']) .
            ' /Ascent ' . $font['ascent'] .
            ' /Descent ' . $font['descent'] .
            ' /CapHeight ' . $font['capHeight'] .
            ' /StemV ' . max(1, (int) $font['stemV']) .
            ' /FontFile2 ' . $fontFileObj . ' 0 R >>';
    }

    private function buildCidToGidObject(array $font): string
    {
        $maxCid = max(array_keys($font['cidToGlyph']));
        $map = '';
        for ($cid = 0; $cid <= $maxCid; $cid++) {
            $glyph = $font['cidToGlyph'][$cid] ?? 0;
            $map .= pack('n', $glyph);
        }
        return '<< /Length ' . strlen($map) . " >>\nstream\n" . $map . "\nendstream";
    }

    private function buildToUnicodeObject(array $font): string
    {
        $segments = [];
        $chunk = [];
        $count = 0;
        ksort($font['toUnicode']);
        foreach ($font['toUnicode'] as $cid => $codepoint) {
            $cidHex = sprintf('%04X', $cid);
            $unicodeHex = $codepoint <= 0xFFFF ? sprintf('%04X', $codepoint) : sprintf('%08X', $codepoint);
            $chunk[] = '<' . $cidHex . '> <' . $unicodeHex . '>';
            $count++;
            if ($count === 100) {
                $segments[] = $chunk;
                $chunk = [];
                $count = 0;
            }
        }
        if (!empty($chunk)) {
            $segments[] = $chunk;
        }

        $cmap = "/CIDInit /ProcSet findresource begin\n" .
            "12 dict begin\n" .
            "begincmap\n" .
            "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n" .
            "/CMapName /Adobe-Identity-UCS def\n" .
            "/CMapType 2 def\n" .
            "1 begincodespacerange\n" .
            "<0000> <FFFF>\n" .
            "endcodespacerange\n";
        foreach ($segments as $chunk) {
            $cmap .= count($chunk) . " beginbfchar\n" . implode("\n", $chunk) . "\nendbfchar\n";
        }
        $cmap .= "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";

        return '<< /Length ' . strlen($cmap) . " >>\nstream\n" . $cmap . "\nendstream";
    }

    private function buildCidFontObject(array $font, int $descriptorObj, ?int $cidToGidObj): string
    {
        $defaultWidth = max(1, (int) round($font['defaultWidth']));

        $widthEntries = [];
        if (!empty($font['cidWidths'])) {
            ksort($font['cidWidths']);
            foreach ($font['cidWidths'] as $cid => $width) {
                $widthEntries[] = $cid . ' [' . $this->formatWidth($width) . ']';
            }
        }
        $widthSection = '';
        if (!empty($widthEntries)) {
            $widthSection = ' /W [' . implode(' ', $widthEntries) . ']';
        }

        $dict = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /' . $font['postScriptName'] .
            ' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>' .
            ' /FontDescriptor ' . $descriptorObj . ' 0 R' .
            ' /DW ' . $defaultWidth;
        if ($cidToGidObj !== null) {
            $dict .= ' /CIDToGIDMap ' . $cidToGidObj . ' 0 R';
        } else {
            $dict .= ' /CIDToGIDMap /Identity';
        }
        $dict .= $widthSection . ' >>';
        return $dict;
    }

    private function buildType0FontObject(array $font, int $cidFontObj, ?int $toUnicodeObj): string
    {
        $dict = '<< /Type /Font /Subtype /Type0 /BaseFont /' . $font['postScriptName'] .
            ' /Encoding /Identity-H /DescendantFonts [' . $cidFontObj . ' 0 R]';
        if ($toUnicodeObj !== null) {
            $dict .= ' /ToUnicode ' . $toUnicodeObj . ' 0 R';
        }
        $dict .= ' >>';
        return $dict;
    }

    private function formatWidth(float $width): string
    {
        $formatted = rtrim(rtrim(sprintf('%.3F', $width), '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    private function utf8ToCodepoints(string $text): array
    {
        $codepoints = [];
        $length = strlen($text);
        $i = 0;
        while ($i < $length) {
            $byte = ord($text[$i]);
            if ($byte <= 0x7F) {
                $codepoints[] = $byte;
                $i++;
                continue;
            }
            if (($byte & 0xE0) === 0xC0 && ($i + 1) < $length) {
                $code = (($byte & 0x1F) << 6) | (ord($text[$i + 1]) & 0x3F);
                $codepoints[] = $code;
                $i += 2;
                continue;
            }
            if (($byte & 0xF0) === 0xE0 && ($i + 2) < $length) {
                $code = (($byte & 0x0F) << 12) |
                    ((ord($text[$i + 1]) & 0x3F) << 6) |
                    (ord($text[$i + 2]) & 0x3F);
                $codepoints[] = $code;
                $i += 3;
                continue;
            }
            if (($byte & 0xF8) === 0xF0 && ($i + 3) < $length) {
                $code = (($byte & 0x07) << 18) |
                    ((ord($text[$i + 1]) & 0x3F) << 12) |
                    ((ord($text[$i + 2]) & 0x3F) << 6) |
                    (ord($text[$i + 3]) & 0x3F);
                $codepoints[] = $code;
                $i += 4;
                continue;
            }
            $codepoints[] = 0x3F;
            $i++;
        }
        return $codepoints;
    }

    private function parseTrueTypeFont(string $filePath): ?array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return null;
        }
        $data = file_get_contents($filePath);
        if ($data === false || strlen($data) < 100) {
            return null;
        }

        $tables = $this->readTableDirectory($data);
        if ($tables === null) {
            return null;
        }

        $head = $this->readTable($data, $tables, 'head');
        $hhea = $this->readTable($data, $tables, 'hhea');
        $maxp = $this->readTable($data, $tables, 'maxp');
        $hmtx = $this->readTable($data, $tables, 'hmtx');
        $cmap = $this->readTable($data, $tables, 'cmap');
        $os2 = $this->readTable($data, $tables, 'OS/2');
        $post = $this->readTable($data, $tables, 'post');
        $name = $this->readTable($data, $tables, 'name');

        if ($head === null || $hhea === null || $maxp === null || $hmtx === null || $cmap === null || $name === null) {
            return null;
        }

        $unitsPerEm = $this->readUInt16($head, 18);
        if ($unitsPerEm <= 0) {
            return null;
        }
        $xMin = $this->readInt16($head, 36);
        $yMin = $this->readInt16($head, 38);
        $xMax = $this->readInt16($head, 40);
        $yMax = $this->readInt16($head, 42);

        $ascent = $this->readInt16($hhea, 4);
        $descent = $this->readInt16($hhea, 6);
        $numberOfHMetrics = $this->readUInt16($hhea, 34);
        $numGlyphs = $this->readUInt16($maxp, 4);

        if ($numberOfHMetrics <= 0 || $numGlyphs <= 0) {
            return null;
        }

        $glyphWidths = $this->parseGlyphWidths($hmtx, $numberOfHMetrics, $numGlyphs, $unitsPerEm);
        $defaultWidth = $glyphWidths[0] ?? 600.0;

        $cmapMap = $this->parseCmap($cmap);
        if (empty($cmapMap)) {
            return null;
        }

        $postScriptName = $this->extractPostScriptName($name);
        if ($postScriptName === null || $postScriptName === '') {
            $postScriptName = 'Font' . substr(md5($filePath), 0, 8);
        }

        $stemV = 80;
        $capHeight = (int) round(($yMax / $unitsPerEm) * 1000);
        $italicAngle = 0.0;
        if ($os2 !== null) {
            $weightClass = $this->readUInt16($os2, 4);
            if ($weightClass > 0) {
                $stemV = max(50, min(200, (int) round($weightClass / 5)));
            }
            $version = $this->readUInt16($os2, 0);
            if ($version >= 2) {
                $cap = $this->readInt16($os2, 88);
                if ($cap > 0) {
                    $capHeight = (int) round(($cap / $unitsPerEm) * 1000);
                }
            }
            $typoAscent = $this->readInt16($os2, 68);
            $typoDesc = $this->readInt16($os2, 70);
            if ($typoAscent !== 0 && $typoDesc !== 0) {
                $ascent = $typoAscent;
                $descent = $typoDesc;
            }
        }
        if ($post !== null) {
            $italicAngle = $this->readFixed($post, 4);
        }

        $bbox = [
            (int) floor(($xMin / $unitsPerEm) * 1000),
            (int) floor(($yMin / $unitsPerEm) * 1000),
            (int) ceil(($xMax / $unitsPerEm) * 1000),
            (int) ceil(($yMax / $unitsPerEm) * 1000),
        ];

        return [
            'postScriptName' => $postScriptName,
            'unitsPerEm' => $unitsPerEm,
            'ascent' => (int) round(($ascent / $unitsPerEm) * 1000),
            'descent' => (int) round(($descent / $unitsPerEm) * 1000),
            'capHeight' => $capHeight,
            'bbox' => $bbox,
            'italicAngle' => $italicAngle,
            'stemV' => $stemV,
            'fontData' => $data,
            'fontDataLength' => strlen($data),
            'cmap' => $cmapMap,
            'glyphWidths' => $glyphWidths,
            'defaultWidth' => $defaultWidth,
        ];
    }

    private function readTableDirectory(string $data): ?array
    {
        if (strlen($data) < 12) {
            return null;
        }
        $numTables = $this->readUInt16($data, 4);
        $offset = 12;
        $tables = [];
        for ($i = 0; $i < $numTables; $i++) {
            if ($offset + 16 > strlen($data)) {
                break;
            }
            $entry = unpack('a4tag/NcheckSum/Noffset/Nlength', substr($data, $offset, 16));
            $offset += 16;
            if ($entry === false) {
                continue;
            }
            $tables[$entry['tag']] = [
                'offset' => $entry['offset'],
                'length' => $entry['length'],
            ];
        }
        return $tables;
    }

    private function readTable(string $data, array $tables, string $tag): ?string
    {
        if (!isset($tables[$tag])) {
            return null;
        }
        $offset = $tables[$tag]['offset'];
        $length = $tables[$tag]['length'];
        if ($offset < 0 || $length <= 0 || ($offset + $length) > strlen($data)) {
            return null;
        }
        return substr($data, $offset, $length);
    }

    private function readUInt16(string $data, int $offset): int
    {
        $value = unpack('n', substr($data, $offset, 2));
        return $value === false ? 0 : $value[1];
    }

    private function readInt16(string $data, int $offset): int
    {
        $value = unpack('n', substr($data, $offset, 2));
        if ($value === false) {
            return 0;
        }
        $v = $value[1];
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    private function readUInt32(string $data, int $offset): int
    {
        $value = unpack('N', substr($data, $offset, 4));
        return $value === false ? 0 : $value[1];
    }

    private function readInt32(string $data, int $offset): int
    {
        $value = unpack('N', substr($data, $offset, 4));
        if ($value === false) {
            return 0;
        }
        $v = $value[1];
        return $v >= 0x80000000 ? $v - 0x100000000 : $v;
    }

    private function readFixed(string $data, int $offset): float
    {
        $value = $this->readInt32($data, $offset);
        return $value / 65536.0;
    }

    private function parseGlyphWidths(string $hmtx, int $numberOfHMetrics, int $numGlyphs, int $unitsPerEm): array
    {
        $advanceWidths = [];
        $offset = 0;
        $length = strlen($hmtx);
        for ($i = 0; $i < $numberOfHMetrics && ($offset + 4) <= $length; $i++) {
            $advance = $this->readUInt16($hmtx, $offset);
            $advanceWidths[$i] = $advance;
            $offset += 4;
        }
        if (empty($advanceWidths)) {
            return [];
        }
        $lastAdvance = end($advanceWidths);
        if ($lastAdvance === false) {
            $lastAdvance = 0;
        }
        for ($i = $numberOfHMetrics; $i < $numGlyphs; $i++) {
            $advanceWidths[$i] = $lastAdvance;
        }

        $scale = 1000 / $unitsPerEm;
        $glyphWidths = [];
        foreach ($advanceWidths as $glyphId => $advance) {
            $glyphWidths[$glyphId] = round($advance * $scale, 3);
        }
        return $glyphWidths;
    }

    private function parseCmap(string $cmapData): array
    {
        if (strlen($cmapData) < 4) {
            return [];
        }
        $numTables = $this->readUInt16($cmapData, 2);
        $offset = 4;
        $preferredOffset = null;
        $fallbackOffset = null;

        for ($i = 0; $i < $numTables; $i++) {
            if ($offset + 8 > strlen($cmapData)) {
                break;
            }
            $platformId = $this->readUInt16($cmapData, $offset);
            $encodingId = $this->readUInt16($cmapData, $offset + 2);
            $subtableOffset = $this->readUInt32($cmapData, $offset + 4);
            $offset += 8;
            if ($platformId === 3 && ($encodingId === 10 || $encodingId === 1)) {
                $preferredOffset = $subtableOffset;
                break;
            }
            if ($preferredOffset === null && $platformId === 0) {
                $fallbackOffset = $subtableOffset;
            }
        }

        $tableOffset = $preferredOffset ?? $fallbackOffset;
        if ($tableOffset === null || $tableOffset >= strlen($cmapData)) {
            return [];
        }
        $format = $this->readUInt16($cmapData, $tableOffset);
        if ($format === 4) {
            return $this->parseCmapFormat4(substr($cmapData, $tableOffset));
        }
        if ($format === 12) {
            return $this->parseCmapFormat12(substr($cmapData, $tableOffset));
        }
        return [];
    }

    private function parseCmapFormat4(string $data): array
    {
        $length = $this->readUInt16($data, 2);
        if ($length > strlen($data)) {
            $length = strlen($data);
        }
        $segCount = $this->readUInt16($data, 6) / 2;
        $endCodes = [];
        $startCodes = [];
        $idDeltas = [];
        $idRangeOffsets = [];

        $offset = 14;
        for ($i = 0; $i < $segCount; $i++) {
            $endCodes[$i] = $this->readUInt16($data, $offset);
            $offset += 2;
        }
        $offset += 2;
        for ($i = 0; $i < $segCount; $i++) {
            $startCodes[$i] = $this->readUInt16($data, $offset);
            $offset += 2;
        }
        for ($i = 0; $i < $segCount; $i++) {
            $idDeltas[$i] = $this->readInt16($data, $offset);
            $offset += 2;
        }
        $idRangeOffsetStart = $offset;
        for ($i = 0; $i < $segCount; $i++) {
            $idRangeOffsets[$i] = $this->readUInt16($data, $offset);
            $offset += 2;
        }

        $glyphMap = [];
        for ($i = 0; $i < $segCount; $i++) {
            $start = $startCodes[$i];
            $end = $endCodes[$i];
            $delta = $idDeltas[$i];
            $rangeOffset = $idRangeOffsets[$i];
            for ($code = $start; $code <= $end; $code++) {
                if ($rangeOffset === 0) {
                    $glyphId = ($code + $delta) & 0xFFFF;
                } else {
                    $offsetWithinTable = $idRangeOffsetStart + (2 * $i) + $rangeOffset + 2 * ($code - $start);
                    if ($offsetWithinTable >= strlen($data)) {
                        continue;
                    }
                    $glyphId = $this->readUInt16($data, $offsetWithinTable);
                    if ($glyphId === 0) {
                        continue;
                    }
                    $glyphId = ($glyphId + $delta) & 0xFFFF;
                }
                if ($glyphId !== 0) {
                    $glyphMap[$code] = $glyphId;
                }
            }
        }
        return $glyphMap;
    }

    private function parseCmapFormat12(string $data): array
    {
        $glyphMap = [];
        $length = $this->readUInt32($data, 4);
        if ($length > strlen($data)) {
            $length = strlen($data);
        }
        $nGroups = $this->readUInt32($data, 12);
        $offset = 16;
        for ($i = 0; $i < $nGroups; $i++) {
            if ($offset + 12 > $length) {
                break;
            }
            $startChar = $this->readUInt32($data, $offset);
            $endChar = $this->readUInt32($data, $offset + 4);
            $startGlyph = $this->readUInt32($data, $offset + 8);
            $offset += 12;
            for ($code = $startChar; $code <= $endChar; $code++) {
                $glyphMap[$code] = $startGlyph + ($code - $startChar);
            }
        }
        return $glyphMap;
    }

    private function extractPostScriptName(string $nameData): ?string
    {
        if (strlen($nameData) < 6) {
            return null;
        }
        $count = $this->readUInt16($nameData, 2);
        $stringOffset = $this->readUInt16($nameData, 4);
        $offset = 6;
        for ($i = 0; $i < $count; $i++) {
            if ($offset + 12 > strlen($nameData)) {
                break;
            }
            $platformId = $this->readUInt16($nameData, $offset);
            $nameId = $this->readUInt16($nameData, $offset + 6);
            $length = $this->readUInt16($nameData, $offset + 8);
            $valueOffset = $this->readUInt16($nameData, $offset + 10);
            $offset += 12;
            if ($nameId !== 6) {
                continue;
            }
            $pos = $stringOffset + $valueOffset;
            if ($pos + $length > strlen($nameData)) {
                continue;
            }
            $raw = substr($nameData, $pos, $length);
            if ($platformId === 0 || $platformId === 3) {
                return $this->decodeUtf16Be($raw);
            }
            return $raw;
        }
        return null;
    }

    private function decodeUtf16Be(string $data): string
    {
        $result = '';
        $length = strlen($data);
        for ($i = 0; $i + 1 < $length; $i += 2) {
            $first = unpack('n', substr($data, $i, 2));
            if ($first === false) {
                continue;
            }
            $code = $first[1];
            if ($code >= 0xD800 && $code <= 0xDBFF && ($i + 3) < $length) {
                $second = unpack('n', substr($data, $i + 2, 2));
                if ($second !== false) {
                    $next = $second[1];
                    if ($next >= 0xDC00 && $next <= 0xDFFF) {
                        $code = (($code - 0xD800) << 10) + ($next - 0xDC00) + 0x10000;
                        $i += 2;
                    }
                }
            }
            $result .= $this->codepointToUtf8($code);
        }
        return $result;
    }

    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7FF) {
            return chr(0xC0 | (($codepoint >> 6) & 0x1F)) .
                chr(0x80 | ($codepoint & 0x3F));
        }
        if ($codepoint <= 0xFFFF) {
            return chr(0xE0 | (($codepoint >> 12) & 0x0F)) .
                chr(0x80 | (($codepoint >> 6) & 0x3F)) .
                chr(0x80 | ($codepoint & 0x3F));
        }
        return chr(0xF0 | (($codepoint >> 18) & 0x07)) .
            chr(0x80 | (($codepoint >> 12) & 0x3F)) .
            chr(0x80 | (($codepoint >> 6) & 0x3F)) .
            chr(0x80 | ($codepoint & 0x3F));
    }
}







