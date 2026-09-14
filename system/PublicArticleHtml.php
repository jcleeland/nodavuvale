<?php

/** Render member-authored rich text without scripts, forms, embeds or member-only UI. */
final class PublicArticleHtml
{
    public function __construct(private int $articleId, private string $host) {}

    public static function uploadPath(string $url, string $host): ?string
    {
        $parts = parse_url(trim($url));
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) { return null; }
        if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) { return null; }
        if (isset($parts['host']) && strtolower($parts['host']) !== strtolower((string) parse_url('http://' . $host, PHP_URL_HOST))) { return null; }
        $path = ltrim(rawurldecode($parts['path'] ?? ''), '/');
        if (str_starts_with($path, './')) { $path = substr($path, 2); }
        if (!str_starts_with($path, 'uploads/') || preg_match('~[\\\\\x00-\x1f]|(?:^|/)\.{1,2}(?:/|$)~', $path)) { return null; }
        return $path;
    }

    public function render(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $images = [];
        $output = '';
        $body = $document->getElementsByTagName('body')->item(0);
        if ($body) {
            foreach ($body->childNodes as $node) { $output .= $this->node($node, $images); }
        }
        return ['html' => $output, 'images' => array_values(array_unique($images))];
    }

    private function node(DOMNode $node, array &$images): string
    {
        if ($node instanceof DOMText) { return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
        if (!$node instanceof DOMElement) { return ''; }
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea',
            'select', 'svg', 'math', 'template', 'noscript', 'head'], true)) { return ''; }
        $allowed = ['p','br','div','strong','b','em','i','u','s','del','sub','sup','ul','ol','li','a','blockquote',
            'span','h1','h2','h3','h4','h5','h6','pre','code','table','thead','tbody','tfoot','tr','th','td',
            'img','figure','figcaption','hr'];
        $attributes = '';
        if ($tag === 'img') {
            $src = trim($node->getAttribute('src'));
            $path = self::uploadPath($src, $this->host);
            if ($path !== null) {
                $images[] = $path;
                $src = 'public_discussion_image.php?discussion_id=' . $this->articleId . '&path=' . rawurlencode($path);
            } elseif (!preg_match('~^https?://~i', $src) && !preg_match('~^/?images/(?!.*(?:^|/)\.{1,2}(?:/|$))[a-zA-Z0-9_./ -]+$~D', $src)) {
                // Embedded raster data images can be displayed without publishing another file.
                if (!preg_match('~^data:image/(?:png|jpeg|gif|webp);base64,[a-zA-Z0-9+/=\r\n]+$~iD', $src)) { return ''; }
            }
            $attributes .= $this->attribute('src', $src) . $this->attribute('alt', $node->getAttribute('alt'));
            $attributes .= ' loading="lazy" referrerpolicy="no-referrer"';
        } elseif ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            // Do not turn arbitrary links to private uploads into public downloads.
            $path = self::uploadPath($href, $this->host);
            if ($path !== null) {
                $href = 'public_discussion_image.php?discussion_id=' . $this->articleId . '&path=' . rawurlencode($path);
            }
            if ($href !== '' && !preg_match('/[\x00-\x20\\\\]/', $href)
                && (preg_match('~^(?:https?://|mailto:|/|\#|\?|[a-zA-Z0-9_.-]+(?:/|\.php))~', $href))) {
                $attributes .= $this->attribute('href', $href) . ' rel="noopener noreferrer"';
            }
        }
        foreach (['title', 'colspan', 'rowspan', 'width', 'height'] as $name) {
            if ($node->hasAttribute($name) && ($name === 'title' || ctype_digit($node->getAttribute($name)))) {
                $attributes .= $this->attribute($name, $node->getAttribute($name));
            }
        }
        // Publication approves the article's presentation too. Preserve the entire
        // declaration, including image layout, custom properties, functions and !important.
        // Escape it as an HTML attribute; never concatenate authored CSS into markup raw.
        if ($node->hasAttribute('style')) {
            $attributes .= $this->attribute('style', $node->getAttribute('style'));
        }
        $children = '';
        foreach ($node->childNodes as $child) { $children .= $this->node($child, $images); }
        if (!in_array($tag, $allowed, true)) { return $children; }
        return '<' . $tag . $attributes . '>' . (in_array($tag, ['img','br','hr'], true) ? '' : $children . '</' . $tag . '>');
    }

    private function attribute(string $name, string $value): string
    {
        return ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }
}
