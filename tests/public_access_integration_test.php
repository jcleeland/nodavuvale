<?php
/**
 * Opt-in, disposable MySQL/MariaDB integration test. Never reads system/config.php.
 * Set NV_TEST_MYSQL_DSN=mysql:host=127.0.0.1;port=33077 (no dbname),
 * NV_TEST_MYSQL_USER and NV_TEST_MYSQL_PASSWORD for a LOCAL test server with CREATE DATABASE.
 * Creates and drops only its randomly named nv_public_test_* database.
 */
require_once dirname(__DIR__) . '/system/admin/MigrationRunner.php';
require_once dirname(__DIR__) . '/system/admin/PublicAccessAdmin.php';

function integrationAssert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
$dsn = getenv('NV_TEST_MYSQL_DSN');
if (!$dsn) { echo "SKIP: set NV_TEST_MYSQL_DSN to an isolated local test server.\n"; exit; }
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=\d+$/D', $dsn)) {
    throw new RuntimeException('The integration test requires an explicit loopback host/port and no existing database name.');
}
$user = getenv('NV_TEST_MYSQL_USER') ?: 'root';
$password = getenv('NV_TEST_MYSQL_PASSWORD') ?: '';
$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'nv_public_test_' . bin2hex(random_bytes(6));
$temp = sys_get_temp_dir() . '/'.$database;
mkdir($temp);
$process = null;
$pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $pdo->exec("USE `$database`");
    $pdo->exec("CREATE TABLE individuals (id INT PRIMARY KEY, first_names VARCHAR(100), aka_names VARCHAR(100),
        last_name VARCHAR(100), birth_prefix VARCHAR(20), birth_year INT, birth_month INT, birth_date INT,
        death_prefix VARCHAR(20), death_year INT, death_month INT, death_date INT, gender VARCHAR(20),
        is_deceased INT DEFAULT 0, created_by INT DEFAULT 1) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE relationships (id INT PRIMARY KEY AUTO_INCREMENT, individual_id_1 INT, individual_id_2 INT, relationship_type VARCHAR(20))");
    $pdo->exec("CREATE TABLE users (id INT PRIMARY KEY, first_name VARCHAR(80), last_name VARCHAR(80),
        approved INT, role VARCHAR(20), last_view DATETIME, individuals_id INT)");
    $pdo->exec("INSERT INTO users VALUES (1,'Test','Admin',1,'admin',NULL,1),(2,'Test','Member',1,'member',NULL,2),(3,'Test','Unapproved',0,'member',NULL,3)");
    $pdo->exec("CREATE TABLE site_settings (name VARCHAR(100), value TEXT)");
    $pdo->exec("INSERT INTO site_settings VALUES ('site_name','Test family')");
    $pdo->exec("CREATE TABLE files (id INT, file_path TEXT)");
    $pdo->exec("CREATE TABLE file_links (file_id INT, item_id INT, individual_id INT)");
    $pdo->exec("CREATE TABLE items (item_id INT, detail_type VARCHAR(100))");
    $pdo->exec("CREATE TABLE discussions (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, title VARCHAR(255),
        content TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Formatted content',
        is_sticky INT DEFAULT 0, is_news INT DEFAULT 0, is_event INT DEFAULT 0, is_historical_event INT DEFAULT 0,
        event_date DATETIME NULL, event_date_finish DATETIME NULL, event_location VARCHAR(255) NULL,
        individual_id INT DEFAULT NULL, created_at DATETIME NOT NULL
    ) ENGINE=MyISAM");
    $pdo->exec("INSERT INTO discussions (user_id,title,content,created_at) VALUES (1,'Preserved','<p>Existing post</p>',NOW())");
    $insert = $pdo->prepare("INSERT INTO individuals (id, first_names, last_name, death_year, death_prefix) VALUES (?, ?, 'Test', ?, '')");
    $insert->execute([1, 'HistoricAncestor', 1800]);
    $insert->execute([2, 'PrivateLiving', null]);
    $insert->execute([3, 'ExcludedAncestor', 1800]);
    $insert->execute([4, 'RecentDeath', 2020]);
    $insert->execute([5, 'HistoricParent', 1750]);
    $insert->execute([6, 'HistoricSibling', 1805]);
    $insert->execute([7, 'HistoricSpouse', 1810]);
    $insert->execute([8, '<script>name</script>', 1810]);
    for ($i = 20; $i < 55; ++$i) { $insert->execute([$i, 'OtherAncestor' . $i, 1800]); }
    $pdo->exec("INSERT INTO relationships (individual_id_1,individual_id_2,relationship_type)
        VALUES (5,1,'child'),(5,6,'child'),(1,2,'child'),(1,3,'spouse'),(1,7,'spouse')");

    $directory = dirname(__DIR__) . '/system/migrations';
    $runner = new MigrationRunner($pdo, $directory);
    integrationAssert($runner->status()[PublicAccess::MIGRATION]['status'] === 'pending', 'Detect pending without bootstrap');
    integrationAssert(!(new PublicAccess($pdo))->ready(), 'Old schema stays private');
    integrationAssert((int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='schema_migrations'")->fetchColumn() === 0, 'GET/status must not create ledger');
    integrationAssert(count($runner->runPending(1)) === 3, 'Initial migrations apply');
    integrationAssert($runner->runPending(1) === [], 'Repeated migration is a no-op');
    $contentColumn = $pdo->query("SHOW FULL COLUMNS FROM discussions LIKE 'content'")->fetch(PDO::FETCH_ASSOC);
    integrationAssert($contentColumn['Type'] === 'mediumtext' && $contentColumn['Collation'] === 'utf8mb4_bin'
        && $contentColumn['Null'] === 'NO' && $contentColumn['Comment'] === 'Formatted content', 'Capacity migration preserves column attributes');
    integrationAssert($pdo->query('SELECT content FROM discussions')->fetchColumn() === '<p>Existing post</p>', 'Capacity migration preserves existing posts');
    integrationAssert((int) $pdo->query('SELECT is_public FROM discussions')->fetchColumn() === 0, 'Existing discussions stay private after migration');
    $pdo->exec('DELETE FROM discussions');
    integrationAssert((int) $pdo->query('SELECT COUNT(*) FROM individuals')->fetchColumn() === 43, 'Migration preserves records');
    $access = new PublicAccess($pdo);
    integrationAssert($access->ready() && !$access->enabled() && $access->person(1) === null, 'New site starts disabled');
    integrationAssert($access->person(1, true) !== null, 'Policy supports controlled administrator preview');
    $manager = new PublicAccessAdmin($pdo);
    $manager->exclude(3, true, 1);
    $manager->saveSettings(['enabled' => 1, 'threshold_years' => 50, 'timezone' => 'Australia/Sydney'], 1);
    $access = new PublicAccess($pdo);
    integrationAssert($access->person(1) !== null && $access->person(2) === null && $access->person(3) === null, 'Eligible only');
    $relatives = $access->relatives(1);
    integrationAssert(array_keys($relatives['Parents']) === [5] && array_keys($relatives['Spouses']) === [7]
        && array_keys($relatives['Siblings']) === [6] && $relatives['Children'] === [], 'Private relatives omitted');
    $listing = $access->directory('', 1);
    integrationAssert($listing['total'] === 40 && count($listing['people']) === 30, 'Filter before counts and pagination');
    integrationAssert(count($access->directory('', 2)['people']) === 10, 'Second page');
    // Surname grouping and date ordering apply globally, before the 30-record page boundary.
    $pdo->exec("UPDATE individuals SET birth_year=1750, birth_month=1, birth_date=2 WHERE id=1");
    $pdo->exec("UPDATE individuals SET birth_year=1750, birth_month=1, birth_date=1 WHERE id IN (6,7)");
    $pdo->exec("UPDATE individuals SET last_name='Adams' WHERE id=8");
    $pdo->exec("UPDATE individuals SET last_name=' test ' WHERE id=20");
    $pdo->exec("UPDATE individuals SET last_name='' WHERE id=54");
    $ordered = array_merge($access->directory('', 1)['people'], $access->directory('', 2)['people']);
    integrationAssert(array_slice(array_column($ordered, 'id'), 0, 5) === [8,6,7,1,5],
        'Surname first, then birth date (including month/day), death fallback, and death tie-break');
    integrationAssert((int) end($ordered)['id'] === 54, 'Missing surname sorts last');
    integrationAssert(count(array_unique(array_column($ordered, 'id'))) === 40, 'Pagination neither duplicates nor omits records');
    $pdo->exec("UPDATE individuals SET birth_year=NULL, birth_month=NULL, birth_date=NULL WHERE id IN (1,6,7)");
    $pdo->exec("UPDATE individuals SET last_name='Test' WHERE id IN (8,20,54)");
    integrationAssert($access->directory('PrivateLiving', 1)['total'] === 0, 'Search cannot expose private records');
    $manager->exclude(1, true, 1);
    integrationAssert($access->person(1) === null && $access->directory('HistoricAncestor', 1)['total'] === 0, 'Exclusion effective immediately');
    $manager->exclude(1, false, 1);
    $pdo->exec('UPDATE individuals SET death_year=2020 WHERE id=1');
    integrationAssert($access->person(1) === null, 'Date correction withdraws access');
    $pdo->exec('UPDATE individuals SET death_year=1800 WHERE id=1');

    // Exercise real advisory locking with two independent connections.
    $other = new PDO($dsn . ';dbname=' . $database, $user, $password);
    $lockName = 'nv_migrations_' . substr(hash('sha256', $database), 0, 40);
    $lock = $other->prepare('SELECT GET_LOCK(?,0)');
    $lock->execute([$lockName]);
    try { $runner->runPending(1); throw new LogicException('Concurrent runner was accepted'); }
    catch (RuntimeException $error) { integrationAssert(str_contains($error->getMessage(), 'in progress'), 'Concurrent run rejected'); }
    $other->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    $other = null;

    // Restartable partial DDL, failure recording, interrupted run, and checksum drift.
    mkdir($temp . '/migrations');
    foreach (glob($directory . '/*.php') as $file) { copy($file, $temp . '/migrations/' . basename($file)); }
    $failureFile = $temp . '/migrations/20260915_001_retry_test.php';
    file_put_contents($failureFile, <<<'PHP'
<?php
return ['description'=>'Test a partial failure', 'up'=>static function(PDO $pdo): void {
    if (!$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='retry_marker'")->fetchColumn()) {
        $pdo->exec('CREATE TABLE retry_marker (id INT)');
        throw new RuntimeException('Intentional test failure after DDL');
    }
}];
PHP);
    $retryRunner = new MigrationRunner($pdo, $temp . '/migrations');
    try { $retryRunner->runPending(1); throw new LogicException('Expected partial failure'); }
    catch (RuntimeException $error) { integrationAssert(str_contains($error->getMessage(), 'Migration failed'), 'Failure propagated'); }
    integrationAssert($retryRunner->status()['20260915_001_retry_test']['status'] === 'failed', 'Failure recorded');
    $pdo->exec("UPDATE schema_migrations SET status='running' WHERE version='20260915_001_retry_test'");
    integrationAssert(count($retryRunner->runPending(1)) === 1, 'Interrupted migration resumed without duplicate DDL');
    file_put_contents($failureFile, "\n", FILE_APPEND);
    integrationAssert($retryRunner->status()['20260915_001_retry_test']['status'] === 'changed', 'Checksum drift detected');
    try { $retryRunner->runPending(1); throw new LogicException('Changed migration accepted'); }
    catch (RuntimeException $error) { integrationAssert(str_contains($error->getMessage(), 'original'), 'Changed migration blocked'); }
    unlink($failureFile);
    integrationAssert($retryRunner->status()['20260915_001_retry_test']['status'] === 'missing', 'Missing history detected');
    $pdo->exec("DELETE FROM schema_migrations WHERE version='20260915_001_retry_test'");

    // HTTP test runs a disposable copy. There are no fixtures or login shortcuts in production files.
    $site = $temp . '/site';
    mkdir($site);
    $repo = dirname(__DIR__);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repo, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($repo) + 1));
        if (preg_match('~^(\.git|uploads|tests|js|styles|images)/~', $relative)
            || pathinfo($relative, PATHINFO_EXTENSION) !== 'php' || $relative === 'system/config.php') { continue; }
        $target = $site . '/' . $relative;
        if (!is_dir(dirname($target))) { mkdir(dirname($target), 0777, true); }
        copy($file->getPathname(), $target);
    }
    preg_match('/port=(\d+)$/', $dsn, $portMatch);
    $config = "<?php\ndefine('DB_HOST', " . var_export('127.0.0.1;port=' . $portMatch[1], true) . ");\n"
        . "define('DB_NAME', " . var_export($database, true) . ");\n"
        . "define('DB_USER', " . var_export($user, true) . ");\n"
        . "define('DB_PASS', " . var_export($password, true) . ");\n";
    file_put_contents($site . '/system/config.php', $config);
    mkdir($site . '/uploads');
    file_put_contents($site . '/uploads/private.txt', 'private attachment sentinel');
    file_put_contents($site . '/router.php', <<<'PHP'
<?php
if (str_starts_with($_SERVER['REQUEST_URI'], '/__test_login')) {
    require __DIR__.'/system/nodavuvale_web.php';
    Web::startSession();
    $id=(int)($_GET['id']??1);
    $_SESSION=['user_id'=>$id,'approved'=>$id===3?0:1,'role'=>$id===1?'admin':'member',
        'first_name'=>'Test','last_name'=>'User','individuals_id'=>$id];
    echo 'test login'; return;
}
if (str_starts_with($_SERVER['REQUEST_URI'], '/uploads/')) { require __DIR__.'/private_file.php'; return; }
return false;
PHP);
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-d', 'output_buffering=0', '-S', $address,
        '-t', $site, $site . '/router.php'], [0 => ['pipe','r'], 1 => ['file',$temp.'/http.log','a'], 2 => ['file',$temp.'/http.log','a']], $pipes, $site);
    if (!is_resource($process)) { throw new RuntimeException('Unable to start test HTTP server'); }
    fclose($pipes[0]);
    $http = static function (string $path, ?string $cookie = null, ?array $post = null) use ($address): array {
        $curl = curl_init('http://' . $address . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HEADER=>true, CURLOPT_TIMEOUT=>15]);
        if ($cookie) { curl_setopt($curl, CURLOPT_COOKIE, $cookie); }
        if ($post !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post)); }
        $raw = curl_exec($curl);
        if ($raw === false) { throw new RuntimeException(curl_error($curl)); }
        $length = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $result = ['status'=>curl_getinfo($curl,CURLINFO_HTTP_CODE), 'headers'=>substr($raw,0,$length), 'body'=>substr($raw,$length)];
        curl_close($curl);
        integrationAssert(!preg_match('/(?:Warning|Fatal error|Notice)(?:<\/b>)?:/', $result['body']), 'No PHP errors in HTTP response: '.$path);
        return $result;
    };
    for ($attempt = 0; $attempt < 50; ++$attempt) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($probe) { fclose($probe); break; }
        usleep(100000);
    }
    $response = $http('/index.php?to=family/individual&individual_id=1');
    integrationAssert($response['status'] === 200 && str_contains($response['body'], 'HistoricAncestor'), 'Existing profile link public');
    integrationAssert(!str_contains($response['body'], 'PrivateLiving') && !str_contains($response['body'], 'ExcludedAncestor'), 'HTML does not leak relatives');
    integrationAssert(str_contains($response['headers'], 'no-store'), 'Public response not cached');
    $private = $http('/index.php?to=family/individual&individual_id=2');
    $absent = $http('/index.php?to=family/individual&individual_id=99999');
    integrationAssert($private['status'] === 404 && $private['body'] === $absent['body'], 'Private and nonexistent indistinguishable');
    $response = $http('/index.php?to=public/ancestors&individual_id=2');
    integrationAssert(!str_contains($response['body'], 'PrivateLiving'), 'Private ID cannot leak through title');
    integrationAssert(str_contains($response['body'], 'container hero-content') && str_contains($response['body'], 'research and general information'), 'Directory uses the site hero and research introduction');
    integrationAssert(substr_count($response['body'], '>Ancestors</a>') === 2, 'Guest sees Ancestors in desktop and mobile navigation');
    $response = $http('/index.php?to=public/individual&individual_id=8');
    integrationAssert(str_contains($response['body'], '&lt;script&gt;name&lt;/script&gt;'), 'Names escaped in page and title');
    integrationAssert($http('/index.php?to=family/individual&individual_id=1', null, ['action'=>'update_individual'])['status'] === 405, 'Guest profile POST rejected');
    integrationAssert($http('/index.php?to=admin/migrations')['status'] === 403, 'Guest migration access denied');
    integrationAssert($http('/index.php?to=origins/../family/individual&individual_id=2')['status'] === 404, 'Traversal blocked');
    integrationAssert($http('/uploads/private.txt')['status'] === 403, 'Private original download denied');
    $response = $http('/upload.php', null, []);
    integrationAssert($response['status'] === 403, 'Guest upload denied: ' . json_encode($response));
    integrationAssert($http('/tinymce_image_upload.php', null, [])['status'] === 403, 'Guest editor upload denied');
    foreach (['book','family_story','timelines','graphical_tree'] as $report) {
        integrationAssert($http('/reports/'.$report.'.php?individual_id=1')['status'] === 403, 'Guest report denied: '.$report);
    }
    $login = $http('/__test_login?id=1');
    preg_match('/Set-Cookie: ([^;\r\n]+)/i', $login['headers'], $matches);
    $adminCookie = $matches[1];
    $response = $http('/index.php?to=admin/migrations', $adminCookie);
    integrationAssert($response['status'] === 200 && str_contains($response['body'], 'All database migrations have been applied'), 'Admin migration page renders');
    integrationAssert(!str_contains($response['body'], '>Ancestors</a>'), 'Admin navigation omits Ancestors even while public access is enabled');
    integrationAssert($http('/index.php?to=admin/migrations', $adminCookie, ['action'=>'run_migrations','csrf_token'=>'forged'])['status'] === 403, 'Forged admin migration rejected');
    $response = $http('/index.php?to=admin/public_access', $adminCookie);
    integrationAssert(str_contains($response['body'], 'Save exclusion') && str_contains($response['body'], 'Preview public profile'), 'Exclusion controls and previews remain on the public ancestor administration page');
    preg_match('/name="csrf_token" value="([^"]+)"/', $response['body'], $matches);
    $csrf = $matches[1];
    $navigationLogin = $http('/__test_login?id=2');
    preg_match('/Set-Cookie: ([^;\r\n]+)/i', $navigationLogin['headers'], $navigationCookie);
    $memberNavigation = $http('/index.php?to=public/ancestors', $navigationCookie[1]);
    integrationAssert($memberNavigation['status'] === 200 && !str_contains($memberNavigation['body'], '>Ancestors</a>'), 'Member navigation omits Ancestors without restricting direct public-directory access');
    $pendingPath = $site . '/system/migrations/20260916_001_http_test.php';
    file_put_contents($pendingPath, "<?php return ['description'=>'HTTP pending migration', 'up'=>static function(PDO \$pdo): void { \$pdo->exec('CREATE TABLE IF NOT EXISTS http_migration_marker (id INT)'); }];");
    $response = $http('/index.php?to=admin/migrations', $adminCookie);
    integrationAssert(str_contains($response['body'], 'HTTP pending migration') && str_contains($response['body'], 'Run migration(s)')
        && str_contains($response['body'], 'Database updates (1)'), 'Pending migration flagged with one-click control');
    integrationAssert($http('/index.php?to=admin/public_access', $adminCookie,
        ['action'=>'public_exclusion','csrf_token'=>$csrf,'individual_id'=>1,'excluded'=>1])['status'] === 303, 'Admin exclusion POST succeeds');
    integrationAssert($http('/index.php?to=family/individual&individual_id=1')['status'] === 404, 'Admin exclusion immediately reflected for guests');
    $manager->exclude(1, false, 1);
    $response = $http('/index.php?to=admin/migrations', $adminCookie, ['action'=>'run_migrations','csrf_token'=>$csrf]);
    integrationAssert($response['status'] === 303, 'One-click migration route succeeds');
    integrationAssert($pdo->query("SELECT status FROM schema_migrations WHERE version='20260916_001_http_test'")->fetchColumn() === 'applied', 'One-click route records successful migration');
    $response = $http('/index.php?to=admin/public_access', $adminCookie,
        ['action'=>'public_settings','csrf_token'=>$csrf,'threshold_years'=>50,'timezone'=>'Australia/Sydney']);
    integrationAssert($response['status'] === 303 && (int) $pdo->query('SELECT enabled FROM public_access_settings WHERE id=1')->fetchColumn() === 0, 'Admin settings POST disables access');
    integrationAssert($http('/index.php?to=public/ancestors&preview=1')['status'] === 404, 'Guest cannot use preview to bypass switch');
    integrationAssert($http('/index.php?to=public/ancestors&preview=1', $adminCookie)['status'] === 200, 'Administrator preview works while disabled');
    $login = $http('/__test_login?id=2');
    preg_match('/Set-Cookie: ([^;\r\n]+)/i', $login['headers'], $matches);
    $memberCookie = $matches[1];
    integrationAssert($http('/index.php?to=admin/migrations',$memberCookie)['status'] === 403, 'Member migration access denied');
    $response = $http('/uploads/private.txt',$memberCookie);
    integrationAssert($response['status'] === 200 && $response['body'] === 'private attachment sentinel', 'Member download preserved');
    $response = $http('/ajax.php',$memberCookie,['method'=>'getindividual','data'=>json_encode(['id'=>2])]);
    integrationAssert(str_contains($response['body'],'PrivateLiving'), 'Member private record access preserved');
    $response = $http('/ajax.php',$memberCookie,['method'=>'update_individual','data'=>json_encode(['individual_id'=>1,'exclude_from_public'=>1])]);
    integrationAssert(json_decode($response['body'],true)['status'] === 'error', 'Member cannot bypass exclusion controls');
    $login = $http('/__test_login?id=3');
    preg_match('/Set-Cookie: ([^;\r\n]+)/i', $login['headers'], $matches);
    integrationAssert($http('/uploads/private.txt',$matches[1])['status'] === 403, 'Unapproved member denied private media');
    require __DIR__ . '/discussion_submission_cases.php';
    require __DIR__ . '/public_discussion_cases.php';
    require __DIR__ . '/discussion_filter_cases.php';
    echo "MariaDB migration, privacy, discussion submission, and HTTP integration tests passed.\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $pdo->exec("DROP DATABASE `$database`");
    // Only remove the unique test directory created above, deepest entries first.
    $root = realpath($temp);
    if ($root && basename($root) === $database && str_starts_with($database, 'nv_public_test_')) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($root);
    }
}
