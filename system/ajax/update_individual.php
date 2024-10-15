<?php
$response=array();
if(!isset($data['individual_id'])) {
    $response['status']='error';
    $response['message']='No individual ID provided';
}

$sqldata = array();
//iterate through the data array for the keys and values
foreach($data as $key => $value) {
    $data[$key] = trim($value);
}
$individual_id = $data['individual_id'];
//Generate the SQL query to update the individual's details
$sql = "UPDATE individuals SET ";
$set = [];
foreach($data as $key => $value) {
    if($key !== 'individual_id') {
        $set[] = "$key = ?";
        $sqldata[] = $value;
    }
}
$sql .= implode(', ', $set);
$sql .= " WHERE id = ?";
$sqldata[]=$individual_id;
//Execute the query
$response['sql']=$sql;
$response['data']=$sqldata;
try {
    $db->update($sql, array_values($sqldata));
    $response['status']='success';
    $response['message']='Individual details updated successfully';
} catch (Exception $e) {
    $response['status']='error';
    $response['message']='Error updating individual details';
}