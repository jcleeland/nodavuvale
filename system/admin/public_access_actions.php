<?php
require_once __DIR__ . '/MigrationRunner.php';
$migrationStates = [];
$migrationNotice = 0;
$migrationError = null;
$adminFeatureRoute = in_array($page, ['admin/migrations', 'admin/public_access'], true)
    || ($page === 'admin/index' && in_array($section, ['migrations', 'public_access'], true));
if ($adminFeatureRoute && $auth->getUserRole() !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}
if ($auth->getUserRole() === 'admin') {
    $migrationRunner = new MigrationRunner($db->connection(), dirname(__DIR__) . '/migrations');
    try {
        $migrationStates = $migrationRunner->status();
        $migrationNotice = count(array_filter($migrationStates, static fn($row) => $row['status'] !== 'applied'));
    } catch (Throwable $error) {
        $migrationError = 'Unable to inspect database migrations. Check the server log.';
        error_log($error->getMessage());
    }
}
if ($adminFeatureRoute) { header('Cache-Control: private, no-store, max-age=0'); }
if ($adminFeatureRoute && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!RequestSecurity::validToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('The form expired. Reload the page and try again.');
    }
    $action = $_POST['action'] ?? '';
    $destination = $action === 'run_migrations' ? 'migrations' : 'public_access';
    try {
        if ($action === 'run_migrations') {
            $completed = $migrationRunner->runPending((int) $_SESSION['user_id']);
            $message = count($completed) . ' migration(s) completed.';
        } elseif (in_array($action, ['public_settings', 'public_exclusion'], true)) {
            if (!$publicAccess->ready()) { throw new RuntimeException('Run the pending database migration first.'); }
            require_once __DIR__ . '/PublicAccessAdmin.php';
            $manager = new PublicAccessAdmin($db->connection());
            if ($action === 'public_settings') {
                $manager->saveSettings(['enabled' => isset($_POST['enabled']) ? 1 : 0,
                    'threshold_years' => $_POST['threshold_years'] ?? '', 'timezone' => $_POST['timezone'] ?? ''], (int) $_SESSION['user_id']);
                $message = 'Public access settings saved.';
            } else {
                $id = filter_var($_POST['individual_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$id) { throw new InvalidArgumentException('Select an individual.'); }
                $manager->exclude($id, isset($_POST['excluded']), (int) $_SESSION['user_id']);
                $message = 'Individual public access updated.';
            }
        } else { throw new InvalidArgumentException('Unknown administration action.'); }
        $_SESSION['public_admin_message'] = $message;
    } catch (Throwable $error) {
        error_log('Public access administration: ' . $error->getMessage());
        $_SESSION['public_admin_message'] = $error instanceof PDOException
            ? 'Database operation failed. Check the server log; no success was recorded.' : $error->getMessage();
    }
    header('Location: index.php?to=admin/' . $destination, true, 303);
    exit;
}
