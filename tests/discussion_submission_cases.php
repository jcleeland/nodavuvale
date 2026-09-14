<?php
// Run by public_access_integration_test.php against its disposable database and HTTP site.
if (!isset($pdo, $http, $adminCookie, $memberCookie)) {
    exit("Run tests/public_access_integration_test.php to exercise these cases.\n");
}
$pdo->exec('ALTER TABLE users ADD avatar VARCHAR(255) NULL, ADD show_presence INT DEFAULT 0');
$pdo->exec('CREATE TABLE discussion_files (id INT AUTO_INCREMENT PRIMARY KEY, discussion_id INT, user_id INT, file_path TEXT, file_type TEXT)');
$pdo->exec('CREATE TABLE discussion_comments (id INT PRIMARY KEY, discussion_id INT, user_id INT, comment TEXT, created_at DATETIME)');
$discussionPath = '/index.php?to=communications/discussions';
$standalonePath = '/index.php?to=communications/newdiscussion';
$response = $http($discussionPath, $adminCookie);
integrationAssert($response['status'] === 200, 'Main discussion form renders');
preg_match('/name="csrf_token" value="([^"]+)"/', $response['body'], $matches);
$discussionCsrf = $matches[1];
$draft = ['new_discussion'=>'1', 'csrf_token'=>$discussionCsrf, 'user_id'=>99999,
    'title'=>'', 'content'=>'<p>A new family discussion</p>', 'event_date'=>'', 'event_date_finish'=>''];
$response = $http($discussionPath, $adminCookie, $draft);
integrationAssert($response['status'] === 303 && $response['body'] === '', 'Success redirects before any HTML output');
preg_match('/Location: (index.php\?to=communications\/discussions&discussion_id=(\d+))/', $response['headers'], $matches);
integrationAssert(isset($matches[2]), 'Success redirects to the inserted discussion');
$savedId = (int) $matches[2];
$row = $pdo->query('SELECT * FROM discussions WHERE id=' . $savedId)->fetch(PDO::FETCH_ASSOC);
integrationAssert((int) $row['user_id'] === 1 && (int) $row['individual_id'] === 0, 'Use session author and explicit general-discussion scope');
integrationAssert($row['title'] === '' && $row['event_date'] === null && $row['event_date_finish'] === null, 'Optional title works and blank dates are NULL');
$response = $http('/' . $matches[1], $adminCookie);
integrationAssert($response['status'] === 200 && str_contains($response['body'], 'A new family discussion')
    && str_contains($response['body'], 'Discussion posted.'), 'Saved discussion and success notice appear');
$http($discussionPath, $adminCookie);
integrationAssert((int) $pdo->query('SELECT COUNT(*) FROM discussions')->fetchColumn() === 1, 'Refreshing the GET does not create a duplicate');
$pdo->exec("INSERT INTO discussions (user_id,title,content,created_at) VALUES (1,'Legacy null scope','Legacy general discussion',NOW())");
integrationAssert(str_contains($http($discussionPath, $adminCookie)['body'], 'Legacy general discussion'), 'Legacy NULL-scope general discussions remain visible');

// Exercise the separate form as an ordinary approved member (previously its INSERT lacked an author).
$response = $http($standalonePath, $memberCookie);
preg_match('/name="csrf_token" value="([^"]+)"/', $response['body'], $matches);
$memberDraft = ['csrf_token'=>$matches[1], 'title'=>'Standalone discussion', 'content'=>'Member post', 'is_news'=>'on'];
$response = $http($standalonePath, $memberCookie, $memberDraft);
integrationAssert($response['status'] === 303 && $response['body'] === '', 'Standalone form saves with a pre-output redirect');
$row = $pdo->query("SELECT * FROM discussions WHERE title='Standalone discussion'")->fetch(PDO::FETCH_ASSOC);
integrationAssert((int) $row['user_id'] === 2 && (int) $row['is_news'] === 1, 'Standalone form persists member author and flags');
$response = $http($standalonePath, $memberCookie, array_replace($memberDraft, ['title'=>'']));
integrationAssert($response['status'] === 422 && str_contains($response['body'], 'Please enter a title.')
    && str_contains($response['body'], '>Member post</textarea>'), 'Standalone validation preserves the draft');

$eventDraft = array_replace($draft, ['is_historical_event'=>'on', 'event_date'=>'1850-01-02 03:04:00',
    'event_date_finish'=>'1850-01-03 03:04:00', 'event_location'=>'Old village']);
integrationAssert($http($discussionPath, $adminCookie, $eventDraft)['status'] === 303, 'Valid historical dates save');
$row = $pdo->query('SELECT * FROM discussions ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
integrationAssert($row['event_date'] === $eventDraft['event_date'] && $row['event_date_finish'] === $eventDraft['event_date_finish'], 'Dates preserved without format changes');
$countBefore = (int) $pdo->query('SELECT COUNT(*) FROM discussions')->fetchColumn();
foreach (['<p>&nbsp;<br></p>', ''] as $emptyContent) {
    integrationAssert($http($discussionPath, $adminCookie, array_replace($draft, ['content'=>$emptyContent]))['status'] === 422, 'Empty editor content rejected');
}
foreach (['1850-02-30', '1849-01-01'] as $invalidFinish) {
    $response = $http($discussionPath, $adminCookie, array_replace($eventDraft, ['event_date_finish'=>$invalidFinish]));
    integrationAssert($response['status'] === 422 && str_contains($response['body'], 'value="Old village"')
        && preg_match('/id="is_historical_event"[^>]*checked/', $response['body']), 'Invalid event retains dates, location and flags');
}
$response = $http($discussionPath, $adminCookie, array_replace($draft, ['csrf_token'=>'forged']));
integrationAssert($response['status'] === 403 && str_contains($response['body'], 'Your form has expired.'), 'Invalid CSRF token rejected visibly');
$response = $http($discussionPath, null, $draft);
integrationAssert($response['status'] === 302 && str_contains($response['headers'], 'to=login'), 'Guest cannot create a discussion');

// Force a real database failure while keeping the schema readable for draft redisplay.
$pdo->exec("CREATE TRIGGER reject_discussion BEFORE INSERT ON discussions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Intentional insert failure'");
foreach ([$discussionPath, $standalonePath] as $path) {
    $response = $http($path, $adminCookie, array_replace($draft, ['title'=>'Retained title', 'content'=>'<p>Retained & safe draft</p>']));
    integrationAssert($response['status'] === 500 && !str_contains($response['headers'], 'Location:'), 'Failed insert never redirects to success');
    integrationAssert(str_contains($response['body'], 'The discussion could not be saved.')
        && str_contains($response['body'], 'value="Retained title"')
        && str_contains($response['body'], '&lt;p&gt;Retained &amp; safe draft&lt;/p&gt;</textarea>'), 'Database error is visible and draft redisplayed safely');
    integrationAssert(!str_contains($response['body'], 'Intentional insert failure'), 'SQL details stay in server log');
    if ($path === $discussionPath) {
        preg_match('/<form[^>]+id="newDiscussionForm">/', $response['body'], $form);
        integrationAssert(isset($form[0]) && !str_contains($form[0], 'hidden'), 'Failed main form stays expanded');
    }
}
$pdo->exec('DROP TRIGGER reject_discussion');
integrationAssert((int) $pdo->query('SELECT COUNT(*) FROM discussions')->fetchColumn() === $countBefore, 'Rejected submissions insert no records');

// Reproduce production's 1406 error on the old schema, then upgrade via the admin button.
$pdo->exec('ALTER TABLE discussions MODIFY COLUMN content TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
$pdo->exec("DELETE FROM schema_migrations WHERE version='20260914_002_discussion_content_capacity'");
$longContent = '<p>' . str_repeat('Family history &amp; memories. ', 3000) . '</p>';
integrationAssert(strlen($longContent) > 65535, 'Regression post exceeds the old TEXT limit');
$longDraft = array_replace($draft, ['title'=>'Long formatted discussion', 'content'=>$longContent]);
$response = $http($discussionPath, $adminCookie, $longDraft);
integrationAssert($response['status'] === 422 && !str_contains($response['headers'], 'Location:')
    && str_contains($response['body'], 'exceeds the current storage limit')
    && str_contains($response['body'], 'pending database migrations'), 'Oversized content explains the cause and remedy');
integrationAssert(str_contains($response['body'], htmlspecialchars($longContent, ENT_QUOTES, 'UTF-8') . '</textarea>'), 'Oversized draft is retained in full');
integrationAssert((int) $pdo->query('SELECT COUNT(*) FROM discussions')->fetchColumn() === $countBefore, 'Oversized post is not truncated or inserted');
$response = $http('/index.php?to=admin/migrations', $adminCookie);
integrationAssert(str_contains($response['body'], 'Increase discussion content capacity') && str_contains($response['body'], 'Run migration(s)'), 'Capacity migration is offered to administrators');
$response = $http('/index.php?to=admin/migrations', $adminCookie, ['action'=>'run_migrations', 'csrf_token'=>$discussionCsrf]);
integrationAssert($response['status'] === 303, 'Admin button applies capacity migration');
foreach ([$discussionPath, $standalonePath] as $path) {
    $response = $http($path, $adminCookie, $longDraft);
    integrationAssert($response['status'] === 303 && $response['body'] === '', 'Long formatted discussion saves after migration');
    integrationAssert($pdo->query('SELECT content FROM discussions ORDER BY id DESC LIMIT 1')->fetchColumn() === $longContent, 'Long content round-trips without truncation');
}
$capacityMigration = require dirname(__DIR__) . '/system/migrations/20260914_002_discussion_content_capacity.php';
($capacityMigration['up'])($pdo);
integrationAssert($pdo->query("SHOW COLUMNS FROM discussions LIKE 'content'")->fetch(PDO::FETCH_ASSOC)['Type'] === 'mediumtext', 'Restarted capacity migration is a no-op');
$pdo->exec('ALTER TABLE discussions MODIFY COLUMN content LONGTEXT NOT NULL');
($capacityMigration['up'])($pdo);
integrationAssert($pdo->query("SHOW COLUMNS FROM discussions LIKE 'content'")->fetch(PDO::FETCH_ASSOC)['Type'] === 'longtext', 'Larger manually configured capacity is never reduced');
echo "Discussion submission regression cases passed.\n";
