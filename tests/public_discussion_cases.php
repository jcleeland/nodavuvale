<?php
// Uses the disposable database, test HTTP server and cookies from the integration harness.
if (!isset($pdo, $http, $site, $adminCookie, $memberCookie, $discussionCsrf)) {
    exit("Run tests/public_access_integration_test.php to exercise these cases.\n");
}
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jfZkAAAAASUVORK5CYII=');
$pdo->exec('ALTER TABLE discussion_files ADD file_description TEXT NULL');
mkdir($site . '/uploads/tinymce');
file_put_contents($site . '/uploads/tinymce/public.png', $png);
file_put_contents($site . '/uploads/discussions/gallery.png', $png);
file_put_contents($site . '/uploads/tinymce/comment-private.png', $png);
file_put_contents($site . '/uploads/tinymce/private.png', $png);
file_put_contents($site . '/uploads/tinymce/not-an-image.png', 'private document disguised as an image');
$insertArticle = $pdo->prepare('INSERT INTO discussions (user_id,title,content,created_at) VALUES (2,?,?,NOW())');
$articleContent = '<h2>Shared history</h2><p>Public story text</p><img src="uploads/tinymce/public.png" alt="Family picture">'
    . '<img src="uploads/tinymce/not-an-image.png"><script>privateScript()</script>';
$insertArticle->execute(['Published <story>', $articleContent]);
$articleId = (int) $pdo->lastInsertId();
$insertArticle->execute(['Private article sentinel', '<p>Never published</p><img src="uploads/tinymce/private.png">']);
$privateId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO discussion_comments (id,discussion_id,user_id,comment,created_at) VALUES (777,?,2,?,NOW())')
    ->execute([$articleId, 'Private comment sentinel <img src="uploads/tinymce/comment-private.png">']);
$pdo->prepare('INSERT INTO discussion_files (discussion_id,user_id,file_path,file_type) VALUES (?,2,?,?)')
    ->execute([$articleId, 'uploads/discussions/gallery.png', 'image/png']);
$pdo->prepare('INSERT INTO discussion_files (discussion_id,user_id,file_path,file_type) VALUES (?,2,?,?)')
    ->execute([$articleId, 'uploads/private.txt', 'text/plain']);
$articleUrl = '/index.php?to=public/discussion&discussion_id=' . $articleId;
$publicImage = '/public_discussion_image.php?discussion_id=' . $articleId . '&path=';
$publication = ['action'=>'discussion_publication','discussion_id'=>(string) $articleId,
    'published'=>'1','privacy_review'=>'1','csrf_token'=>$discussionCsrf];
integrationAssert($http($articleUrl)['status'] === 404, 'Discussion is private by default');
integrationAssert($http($publicImage . rawurlencode('uploads/tinymce/public.png'))['status'] === 404, 'Private discussion image unavailable');
$home = $http('/index.php');
integrationAssert(!str_contains($home['body'], 'id="public-stories-heading"'), 'No empty published-articles section');
integrationAssert($http($discussionPath, $memberCookie, $publication)['status'] === 403, 'Member cannot publish');
integrationAssert($http($discussionPath, null, $publication)['status'] === 302, 'Guest cannot publish');
integrationAssert($http($discussionPath, $adminCookie, array_replace($publication, ['csrf_token'=>'bad']))['status'] === 403, 'Publication requires CSRF token');
integrationAssert($http($discussionPath, $adminCookie, array_replace($publication, ['privacy_review'=>'0']))['status'] === 422, 'Publication requires explicit privacy review');
$memberPage = $http($discussionPath, $memberCookie);
integrationAssert(!str_contains($memberPage['body'], 'name="action" value="discussion_publication"'), 'Publication controls are admin-only');
$adminPage = $http($discussionPath, $adminCookie);
integrationAssert(str_contains($adminPage['body'], 'privacy implications') && str_contains($adminPage['body'], 'Future edits and added images'), 'Admin receives privacy and continuing-publication warning');
integrationAssert($http($discussionPath, $adminCookie, $publication)['status'] === 303, 'Admin can publish');
$article = $http($articleUrl . '&individual_id=2');
integrationAssert($article['status'] === 200 && str_contains($article['body'], 'Published &lt;story&gt;')
    && str_contains($article['body'], 'Public story text') && str_contains($article['body'], 'container hero-content'), 'Public article has escaped title, content and standard hero');
foreach (['Private comment sentinel','PrivateLiving','discussion-reactions','newDiscussionForm','editDiscussionForm','privateScript()', 'private document disguised'] as $hidden) {
    integrationAssert(!str_contains($article['body'], $hidden), 'Public article omits member content or unsafe markup: ' . $hidden);
}
integrationAssert(str_contains($article['headers'], 'no-store'), 'Public article not cached');
$ajaxGuest = $http('/ajax.php', null, ['method'=>'get_discussion','data'=>json_encode(['discussion_id'=>$articleId])]);
integrationAssert(!str_contains($ajaxGuest['body'], 'Public story text'), 'Public article does not expose member AJAX API');
integrationAssert($http($articleUrl, null, ['comment'=>'Forged public comment'])['status'] === 405, 'Public article POST rejected');
integrationAssert($http($publicImage . rawurlencode('uploads/tinymce/public.png'), null, [])['status'] === 405, 'Public image POST rejected');
$image = $http($publicImage . rawurlencode('uploads/tinymce/public.png'));
integrationAssert($image['status'] === 200 && $image['body'] === $png && str_contains($image['headers'], 'Content-Type: image/png'), 'Inline image accessible without login');
integrationAssert(str_contains($image['headers'], 'no-store') && str_contains($image['headers'], "sandbox; default-src 'none'")
    && str_contains($image['headers'], 'nosniff'), 'Public images cannot be cached or execute active content');
integrationAssert($http($publicImage . rawurlencode('uploads/discussions/gallery.png'))['status'] === 200
    && str_contains($article['body'], 'uploads%2Fdiscussions%2Fgallery.png'), 'Image attachments displayed and publicly accessible');
foreach (['uploads/tinymce/private.png', 'uploads/tinymce/comment-private.png', 'uploads/private.txt',
    'uploads/tinymce/not-an-image.png', 'uploads/../system/config.php', 'uploads/%2e%2e/system/config.php'] as $privatePath) {
    integrationAssert($http($publicImage . rawurlencode($privatePath))['status'] === 404, 'Public image access excludes unrelated or non-image file: ' . $privatePath);
}
integrationAssert($http('/uploads/tinymce/public.png')['status'] === 403, 'Original upload URLs retain member protection');
$home = $http('/index.php');
integrationAssert(str_contains($home['body'], 'id="public-stories-heading"') && str_contains($home['body'], 'Published &lt;story&gt;')
    && !str_contains($home['body'], 'Private article sentinel'), 'Homepage lists only published articles');
integrationAssert(strpos($home['body'], 'id="public-stories-heading"') > strpos($home['body'], '>Login or Register</h3>'), 'Full-width article section follows the existing cards');

// Existing author edits immediately update the public article; no second publication step.
$edit = $http('/ajax.php', $memberCookie, ['method'=>'update_discussion', 'data'=>json_encode([
    'discussion_id'=>$articleId, 'title'=>'Updated public title', 'content'=>'<p>Updated public text</p><img src="uploads/tinymce/private.png">'])]);
integrationAssert(json_decode($edit['body'], true)['status'] === 'success', 'Author uses existing edit route');
$article = $http($articleUrl);
integrationAssert(str_contains($article['body'], 'Updated public text') && str_contains($article['body'], 'Updated public title'), 'Subsequent edits are automatically public');
integrationAssert($http($publicImage . rawurlencode('uploads/tinymce/private.png'))['status'] === 200, 'New inline image becomes public with edit');
integrationAssert($http($publicImage . rawurlencode('uploads/tinymce/public.png'))['status'] === 404, 'Removed inline image authorization is withdrawn');
integrationAssert(str_contains($http('/index.php')['body'], 'Updated public title'), 'Homepage reflects current title');
integrationAssert($http($discussionPath, $adminCookie, array_replace($publication, ['published'=>'0']))['status'] === 303, 'Admin can make article private');
$private = $http($articleUrl);
$absent = $http('/index.php?to=public/discussion&discussion_id=999999');
integrationAssert($private['status'] === 404 && $private['body'] === $absent['body'], 'Private and missing public articles are indistinguishable');
integrationAssert($http($publicImage . rawurlencode('uploads/tinymce/private.png'))['status'] === 404
    && $http($publicImage . rawurlencode('uploads/discussions/gallery.png'))['status'] === 404, 'Unpublishing revokes public article images');
integrationAssert(!str_contains($http('/index.php')['body'], 'Updated public title'), 'Unpublished article removed from homepage');

// Even a stale publication flag must not expose data when its migration is missing.
$pdo->exec('UPDATE discussions SET is_public=1 WHERE id=' . $articleId);
$pdo->exec("UPDATE schema_migrations SET status='failed' WHERE version='20260914_003_public_discussions'");
integrationAssert($http($articleUrl)['status'] === 404 && $http($publicImage . rawurlencode('uploads/tinymce/private.png'))['status'] === 404,
    'Incomplete migration fails closed for page and image access');
integrationAssert(!str_contains($http('/index.php')['body'], 'Updated public title'), 'Incomplete migration hides public listings');
integrationAssert($http('/index.php?to=admin/migrations', $adminCookie,
    ['action'=>'run_migrations','csrf_token'=>$discussionCsrf])['status'] === 303, 'Interrupted publication migration can be retried');
integrationAssert($http($articleUrl)['status'] === 200, 'Retry preserves the article and existing publication choice');
$pdo->exec('DELETE FROM discussions WHERE id=' . $articleId);
integrationAssert($http($articleUrl)['status'] === 404 && $http($publicImage . rawurlencode('uploads/discussions/gallery.png'))['status'] === 404,
    'Deleting an article also revokes public page and image access');
echo "Public discussion publication, privacy, images and live-edit tests passed.\n";
