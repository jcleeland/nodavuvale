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

// Check if the current user matches the user_id
if($_SESSION['user_id'] !== $data['user_id'] && $auth->getUserRole() !== 'admin') {
    $response['message']='You do not have permission to modify other users';
    echo json_encode($response);
    return $response;
}

if($auth->getUserRole() !== 'admin') {
    $validFields=['individuals_id', 'first_name','last_name','email','relative_name','relationship'];
} else {
    $validFields=['individuals_id', 'first_name','last_name','email','relative_name','relationship','role','approved'];
}
foreach($data as $key=>$value) {
    if(!in_array($key,$validFields) && $key != 'user_id') {
        $response['message']='Invalid field: '.$key;
        echo json_encode($response);
        return $response;
    }
}
//Add all the fields, except for user_id to a parameters array
$parameters=[];
foreach($data as $key=>$value) {
    if($key != 'user_id') {
        $parameters[$key]=$value;
    }
}

try {
    $sql = "UPDATE users SET ";
    foreach($parameters as $key=>$value) {
        $sql .= $key.' = ?, ';
    }
    $sql = rtrim($sql, ', ');
    $sql .= " WHERE id = ?";
    //Add the user_id to the end of the parameters array
    $parameters['user_id']=$data['user_id'];
    $db->query($sql, array_values($parameters));
    $response['sql']=$sql.' '.implode(", ", $parameters);
    $response['status']='success';
    $response['message']='User approved';
} catch (Exception $e) {
    $response['message']='Error approving user: '.$e->getMessage();
}
