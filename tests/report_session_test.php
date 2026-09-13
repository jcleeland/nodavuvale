<?php

$reportPath = dirname(__DIR__) . '/reports/family_story.php';
$tokens = token_get_all(file_get_contents($reportPath));
if ($tokens[0][0] !== T_OPEN_TAG) {
    throw new RuntimeException('The family story report must not emit output before its opening PHP tag.');
}

ob_start();
try {
    require_once dirname(__DIR__) . '/vendor/simplepdf/SimplePDF.php';
    require_once dirname(__DIR__) . '/system/nodavuvale_web.php';
} finally {
    $output = ob_get_clean();
}
if ($output !== '') {
    throw new RuntimeException('Loading report libraries must not emit output before session startup.');
}

set_error_handler(function ($severity, $message) {
    throw new RuntimeException($message);
});
try {
    Web::startSession();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('Report session startup failed.');
    }
    // Authentication may call startSession again after the report starts it.
    Web::startSession();
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    restore_error_handler();
}

echo "Report session tests passed.\n";
