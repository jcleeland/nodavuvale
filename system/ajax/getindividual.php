<?php
/**
 * get an individual's details
 */
$response = [];

if(!isset($data['id'])) {
    $response['error'] = 'No individual ID provided';
} else {
    $individual_id = $data['id'];
    $individual = $db->fetchOne("SELECT * FROM individuals WHERE id = ?", [$individual_id]);
    if($individual) {
        $response['individual'] = $individual;
    } else {
        $response['error'] = 'Individual not found';
    }
}

