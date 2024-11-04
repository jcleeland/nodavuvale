<?php
$response=[
    'status'=>'error',
    'message'=>'Invalid request'
];
if(!isset($data['email']) && (!isset($data['first_name']) || !isset($data['last_name']))) {
    $response['message']='You must supply at least an email & a first or last name';
    echo json_encode($response);
    return $response;
}

//Check that admin
if($auth->getUserRole() !== 'admin') {
    $response['message']='You do not have permission to add users';
    echo json_encode($response);
    return $response;
}

//Check if the user already exists
$user = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$data['email']]);
if($user) {
    $response['message']='User already exists';
    echo json_encode($response);
    return $response;
}

//If there is a password, hash it
if(isset($data['password'])) {
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
} else {
    $randomString = bin2hex(random_bytes(8));
    $hashed_password = password_hash($randomString, PASSWORD_DEFAULT);
}

$data['first_name'] = $data['first_name'] ?? 'New';
$data['last_name'] = $data['last_name'] ?? 'User';
$data['role'] = $data['role'] ?? 'unconfirmed';
$data['approved'] = $data['approved'] ?? 0;
$data['password'] = $data['password'] ?? $hashed_password;


//Create a new user
try {
    $db->query("INSERT INTO users (email, first_name, last_name, role, approved) VALUES (?, ?, ?, ?, ?)", 
        [$data['email'], $data['first_name'], $data['last_name'], $data['role'], $data['approved']]);
    $response['status']='success';
    $response['message']='User added';
} catch (Exception $e) {
    $response['message']='Error adding user: '.$e->getMessage();
}