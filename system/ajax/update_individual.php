<?php
$response=array();
// Privacy overrides must only be changed through the CSRF-protected admin action.
$allowedFields = ['individual_id', 'first_names', 'aka_names', 'last_name', 'birth_prefix',
    'birth_year', 'birth_month', 'birth_date', 'death_prefix', 'death_year', 'death_month',
    'death_date', 'gender', 'is_deceased'];
if (!is_array($data) || array_diff(array_keys($data), $allowedFields)) {
    $response = ['status' => 'error', 'message' => 'Unsupported individual field'];
    return;
}
if(!isset($data['individual_id'])) {
    $response['status']='error';
    $response['message']='No individual ID provided';
    return;
}

$sqldata = array();
//iterate through the data array for the keys and values
foreach($data as $key => $value) {
    if (!is_scalar($value) && $value !== null) {
        $response = ['status' => 'error', 'message' => 'Invalid individual field'];
        return;
    }
    $data[$key] = trim((string) $value);
    if (in_array($key, ['birth_prefix', 'birth_year', 'birth_month', 'birth_date',
        'death_prefix', 'death_year', 'death_month', 'death_date'], true) && $data[$key] === '') {
        $data[$key] = null;
    }
}
if (count($data) < 2) {
    $response = ['status' => 'error', 'message' => 'No changes provided'];
    return;
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
try {
    if ($db->update($sql, array_values($sqldata)) === false) {
        throw new RuntimeException('Update failed');
    }
    $response['status']='success';
    $response['message']='Individual details updated successfully';
} catch (Exception $e) {
    $response['status']='error';
    $response['message']='Error updating individual details';
}
