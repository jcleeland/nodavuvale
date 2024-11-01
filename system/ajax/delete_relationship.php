<?php
/**
 * This deletes an entry from the relationships table
 */

$response=array();
if(!isset($data['relationshipId'])) {
    $response['status']='error';
    $response['message']='No relationship ID provided';
    return $response;
}
if(!isset($data['relationshipType'])) {
    $response['status']='error';
    $response['message']='No relationship type provided';
    return $response;
}

//There isn't actually a "parent" relationship type - only a child.
// so when someone is deleting a "parent" relationship from a child, they're actually deleting a "child" relationship from the parent
// and therefore we need to reverse the search
if($data['relationshipType'] == 'parent') {

}

//There needs to be a little bit of checking here, because relationships can be two ways
// - so we need to check if there is a reciprocal relationship

//First, get the relationship
$sql = "SELECT relationships.*, individual1.first_names as object_first_names, individual1.last_name as object_last_name,
        individual2.first_names as subject_first_names, individual2.last_name as subject_last_name 
        FROM relationships 
        INNER JOIN individuals as individual1 ON relationships.individual_id_1 = individual1.id 
        INNER JOIN individuals as individual2 ON relationships.individual_id_2 = individual2.id
        WHERE relationships.id = ?";
$relationship = $db->fetchOne($sql, [$data['relationshipId']]);

if(empty($relationship)) {
    $response['status']='error';
    $response['message']='Relationship not found';
    return $response;
}

if($relationship['relationship_type'] != $data['relationshipType']) {
    $response['status']='error';
    $response['message']='This relationship is an implied relationship, showing here because '.$relationship['subject_first_names'].' '.$relationship['subject_last_name'].' is related to '.$relationship['object_first_names'].' '.$relationship['object_last_name'].' is a '.$relationship['relationship_type'] .' of '.$relationship['subject_first_names'].' '.$relationship['subject_last_name'].'. To remove this implied relationship you\'ll need to delete that other relationship';
    return $response;
}

//We've found the relationship, and it matches the type we are deleting

$sql = "DELETE FROM relationships WHERE id = ?";

try {
    //Now, if it is a spouse relationship, we need to check for a reciprocal relationship of the same type
    // - if there are two "spouse" relationships between the same people, then it's OK to delete them both
    if($data['relationshipType'] == 'spouse') {
        $rsql = "SELECT * FROM relationships WHERE individual_id_1 = ? AND individual_id_2 = ? AND relationship_type = ?";
        //print_r([$relationship['individual_id_2'], $relationship['individual_id_1'], 'spouse']);
        $reciprocalRelationship = $db->fetchOne($rsql, [$relationship['individual_id_2'], $relationship['individual_id_1'], 'spouse']);
        if(!empty($reciprocalRelationship)) {
            //echo "Deleting reciprocal relationship";
            //echo $reciprocalRelationship['id'];
            $db->delete($sql, [$reciprocalRelationship['id']]);
        }
    }    
    $db->delete($sql, [$data['relationshipId']]);
    $response['sql']=$sql.' '.$data['relationshipId'];
    $response['status']='success';
    $response['message']='Relationship has been deleted';
} catch (Exception $e) {
    $response['status']='error';
    $response['message']='Failed to delete relationship'.$e;
}
