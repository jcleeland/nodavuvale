<?php

$response=[
    'status'=>'error',
    'message'=>'Invalid request'
];

if($auth->getUserRole() !== 'admin') {
    $response['message']='You do not have permission to delete users';
    echo json_encode($response);
    return $response;
}

if(!isset($data['user_id']) || !is_numeric($data['user_id'])) {
    $response['message']='Invalid user ID';
    echo json_encode($response);
    return $response;
}

try {
    // We 
    $db->query("UPDATE users SET role='deleted', approved=0 WHERE id = ?", [$data['user_id']]);
    $response['status']='success';
    $response['message']='User deleted';
} catch (Exception $e) {
    $response['message']='Error deleting user: '.$e->getMessage();
}
