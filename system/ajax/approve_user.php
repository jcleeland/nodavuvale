<?php

$response=[
    'status'=>'error',
    'message'=>'Invalid request'
];

if(!isset($data['user_id']) || !is_numeric($data['user_id'])) {
    $response['message']='Invalid user ID';
    echo json_encode($response);
    return $response;
}

// Check if the current user is an admin
if($auth->getUserRole() !== 'admin') {
    $response['message']='You do not have permission to approve users';
    echo json_encode($response);
    return $response;
}

try {
    if($data['unapprove']) {
        $sql = "UPDATE users SET approved = 0, role='unconfirmed' WHERE id = ?";
    } else {
        $sql = "UPDATE users SET approved = 1, role='member' WHERE id = ?";
    }
    $db->query($sql, [$data['user_id']]);
    $response['sql']=$sql.' '.$data['user_id'];
    $response['status']='success';
    $response['message']='User approved';
} catch (Exception $e) {
    $response['message']='Error approving user: '.$e->getMessage();
}