<?php

require_once dirname(__DIR__) . '/system/admin/DatabaseCleanupService.php';

final class DatabaseCleanupNoQueryStub
{
}

function cleanupTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new DatabaseCleanupService(
    new DatabaseCleanupNoQueryStub(),
    dirname(__DIR__),
    sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nodavuvale-cleanup-test-archive'
);
$secret = str_repeat('a', 64);
$now = 2_000_000_000;
$action = 'cleanup_delete_orphan_item';
$target = '375';
$token = $service->issueActionToken($action, $target, $secret, $now);

cleanupTestAssert(
    $service->validateActionToken($token, $action, $target, $secret, $now),
    'A fresh matching scan token should validate.'
);
cleanupTestAssert(
    !$service->validateActionToken($token, $action, '376', $secret, $now),
    'A scan token must be bound to its exact target.'
);
cleanupTestAssert(
    !$service->validateActionToken($token, 'cleanup_delete_dangling_item_link', $target, $secret, $now),
    'A scan token must be bound to its exact action.'
);
cleanupTestAssert(
    !$service->validateActionToken($token, $action, $target, str_repeat('b', 64), $now),
    'A scan token signed with another session secret must fail.'
);
cleanupTestAssert(
    !$service->validateActionToken($token, $action, $target, $secret, $now + DatabaseCleanupService::SCAN_TOKEN_TTL + 1),
    'An expired scan token must fail.'
);

$tampered = substr($token, 0, -1) . (substr($token, -1) === '0' ? '1' : '0');
cleanupTestAssert(
    !$service->validateActionToken($tampered, $action, $target, $secret, $now),
    'A tampered scan token must fail.'
);

$emptyBulkRejected = false;
try {
    $service->performActions([], $secret, 1);
} catch (RuntimeException $exception) {
    $emptyBulkRejected = str_contains($exception->getMessage(), 'Select at least one');
}
cleanupTestAssert($emptyBulkRejected, 'Bulk cleanup must reject an empty selection before querying or changing data.');

$invalidBulkRejected = false;
try {
    $service->performActions([[
        'action' => $action,
        'target' => $target,
        'scan_token' => 'invalid',
    ]], $secret, 1);
} catch (RuntimeException $exception) {
    $invalidBulkRejected = str_contains($exception->getMessage(), 'expired or is invalid');
}
cleanupTestAssert($invalidBulkRejected, 'Bulk cleanup must reject an invalid row token before querying or changing data.');

$reviewRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nodavuvale-cleanup-review-' . bin2hex(random_bytes(6));
$reviewDirectory = $reviewRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'review';
$reviewFile = $reviewDirectory . DIRECTORY_SEPARATOR . 'sample file.txt';

try {
    cleanupTestAssert(mkdir($reviewDirectory, 0777, true), 'The review test directory should be created.');
    cleanupTestAssert(file_put_contents($reviewFile, 'review contents') !== false, 'The review test file should be created.');

    $reviewService = new DatabaseCleanupService(
        new DatabaseCleanupNoQueryStub(),
        $reviewRoot,
        $reviewRoot . DIRECTORY_SEPARATOR . 'archive'
    );
    $reviewPath = 'uploads/review/sample file.txt';
    $reviewToken = $reviewService->issueFileReviewToken($reviewPath, $secret);

    cleanupTestAssert($reviewToken !== null, 'An existing file below uploads should receive a review token.');
    cleanupTestAssert(
        $reviewService->resolveFileReviewPath($reviewPath, (string) $reviewToken, $secret) === realpath($reviewFile),
        'A signed review request should resolve to the intended upload.'
    );
    cleanupTestAssert(
        $reviewService->resolveFileReviewPath('uploads/review/another.txt', (string) $reviewToken, $secret) === null,
        'A review token must be bound to its exact path.'
    );
    cleanupTestAssert(
        $reviewService->resolveFileReviewPath($reviewPath, (string) $reviewToken, str_repeat('b', 64)) === null,
        'A review token signed by another session must fail.'
    );
    cleanupTestAssert(
        $reviewService->issueFileReviewToken('../system/config.php', $secret) === null,
        'A path outside uploads must never receive a review token.'
    );
} finally {
    if (is_file($reviewFile)) {
        unlink($reviewFile);
    }
    if (is_dir($reviewDirectory)) {
        rmdir($reviewDirectory);
    }
    $reviewUploads = $reviewRoot . DIRECTORY_SEPARATOR . 'uploads';
    if (is_dir($reviewUploads)) {
        rmdir($reviewUploads);
    }
    if (is_dir($reviewRoot)) {
        rmdir($reviewRoot);
    }
}

echo "DatabaseCleanupService token, bulk validation, and file review tests passed.\n";
