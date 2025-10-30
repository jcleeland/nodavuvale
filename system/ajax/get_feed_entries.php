<?php

$response = [
    'success' => false,
    'status'  => 'error',
    'message' => '',
    'data'    => null,
];

if (!$auth->isLoggedIn()) {
    $response['message'] = 'User not logged in';
    return;
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
if ($currentUserId <= 0) {
    $response['message'] = 'Invalid session';
    return;
}

$offset = isset($data['offset']) ? (int) $data['offset'] : 0;
$limit = isset($data['limit']) ? (int) $data['limit'] : 10;
$since = isset($data['since']) && $data['since'] !== '' ? (string) $data['since'] : null;

// Keep pagination values within a sensible range to avoid runaway queries.
$offset = max(0, $offset);
$limit = max(1, min($limit, 50));

try {
    $feedService = new FeedService($db, $auth, $web);
    $payload = $feedService->getFeedSlice($currentUserId, $offset, $limit, $since);

    $response['success'] = true;
    $response['status'] = 'success';
    $response['data'] = [
        'entries'         => $payload['entries'],
        'summary'         => $payload['summary'],
        'total'           => $payload['total'],
        'hasMore'         => $payload['has_more'],
        'nextOffset'      => $payload['next_offset'],
        'lastView'        => $payload['last_view'],
        'emoji'           => $payload['emoji'],
        'currentUserId'   => $payload['current_user_id'],
        'isAdmin'         => $payload['is_admin'],
        'requestedOffset' => $offset,
        'requestedLimit'  => $limit,
    ];
} catch (Throwable $e) {
    $response['message'] = 'Failed to load feed entries';
    $response['error'] = $e->getMessage();
}
