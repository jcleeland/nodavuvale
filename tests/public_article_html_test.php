<?php
require_once dirname(__DIR__) . '/system/PublicArticleHtml.php';
function articleAssert(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
$renderer = new PublicArticleHtml(7, 'family.test');
$result = $renderer->render('<h2>Our history</h2><p style="text-align:center;color:#123456;position:fixed;background-image:url(https://bad.test)">Text <strong>bold</strong></p>'
    . '<img src="uploads/tinymce/family%20photo.png" onerror="alert(1)" alt="Family &amp; friends">'
    . '<a href="javascript:alert(1)" onclick="alert(2)">unsafe link</a><script>alert(3)</script>'
    . '<div><unknown><img src="https://images.test/photo.png" onload="alert(4)"></unknown></div>'
    . '<iframe src="/private"></iframe><form action="/login"><input name="password"></form>'
    . '<svg><script>alert(5)</script></svg><img src="data:text/html,unsafe"><!-- private marker -->');
articleAssert(str_contains($result['html'], '<h2>Our history</h2>') && str_contains($result['html'], '<strong>bold</strong>'), 'Semantic formatting preserved');
articleAssert(str_contains($result['html'], 'text-align:center;color:#123456'), 'Safe editor formatting preserved');
foreach (['onerror','onclick','onload','javascript:','alert(','position:','background-image','<iframe','<form','<svg','private marker','data:text/html'] as $unsafe) {
    articleAssert(!str_contains($result['html'], $unsafe), 'Strip unsafe content: ' . $unsafe);
}
articleAssert($result['images'] === ['uploads/tinymce/family photo.png'], 'Only inline upload references authorize local images');
articleAssert(str_contains($result['html'], 'public_discussion_image.php?discussion_id=7&amp;path=uploads%2Ftinymce%2Ffamily%20photo.png'), 'Local image URL rewritten');
articleAssert(str_contains($result['html'], 'https://images.test/photo.png'), 'External image retained');
foreach (['uploads/../config.php', 'uploads/%2e%2e/config.php', 'uploads/a%00.png', 'uploads\\secret.png',
    'https://another.test/uploads/private.png', 'file:///uploads/private.png', '//another.test/uploads/a.png'] as $unsafe) {
    articleAssert(PublicArticleHtml::uploadPath($unsafe, 'family.test') === null, 'Reject unsafe upload reference: ' . $unsafe);
}
articleAssert(PublicArticleHtml::uploadPath('https://family.test/uploads/photo.png', 'family.test') === 'uploads/photo.png', 'Same-site absolute image URL recognized');
articleAssert(PublicArticleHtml::uploadPath('/uploads/photo.png', 'family.test') === 'uploads/photo.png', 'Root-relative image URL recognized');
$result = $renderer->render('<p>Fijian café &amp; family</p><img src="data:image/png;base64,YWJj">');
articleAssert(str_contains($result['html'], 'café &amp; family') && str_contains($result['html'], 'data:image/png;base64,YWJj'), 'Unicode and embedded raster images retained');
echo "Public article HTML tests passed.\n";
