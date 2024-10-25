<?php
/**
 * get an individual's details
 */
$response = [];

if(!isset($data['id'])) {
    $response['error'] = 'No individual ID provided';
} else {
    $individual_id = $data['id'];
    $sql = "SELECT DISTINCT
                CASE 
                    WHEN r.individual_id_1 = ? THEN r.individual_id_2 
                    ELSE r.individual_id_1 
                END AS parent_id,
                individual_spouse.first_names AS spouse_first_names,
                individual_spouse.last_name AS spouse_last_name
            FROM
                relationships AS r
            INNER JOIN individuals AS individual_spouse ON 
                (CASE 
                    WHEN r.individual_id_1 = ? THEN r.individual_id_2 
                    ELSE r.individual_id_1 
                END) = individual_spouse.id
            WHERE 
                (r.individual_id_1 = ? OR r.individual_id_2 = ?)
                AND r.relationship_type = 'spouse';
            ";
    //echo $sql;
    
    //First, find explicit spouses from the relationships table
    $explicit = $db->fetchAll($sql, array($individual_id, $individual_id, $individual_id, $individual_id));

    //print_r($explicit);
    //Now find any children of this individual, and see if they have a second parent
    // in which case we can infer that the second parent is a spouse
    $sql = "SELECT DISTINCT 
                individual_3.id AS parent_id,
                individual_3.first_names AS parent_first_names,
                individual_3.last_name AS parent_last_name
            FROM
                relationships AS r1
            INNER JOIN individuals AS individual_2 ON r1.individual_id_2 = individual_2.id
            INNER JOIN relationships AS r2 ON r2.individual_id_2 = r1.individual_id_2
            INNER JOIN individuals AS individual_3 ON r2.individual_id_1 = individual_3.id
            WHERE 
                r1.individual_id_1 = ?  -- First parent is individual 2
                AND r1.relationship_type = 'child'
                AND r2.individual_id_1 <> ?  -- Find other parents that are not individual 2
                AND r2.relationship_type = 'child';
            ";
    $implicit = $db->fetchAll($sql, array($individual_id, $individual_id));

    //Now compare the two arrays and remove any duplicates
    foreach($explicit as $key=>$value) {
        foreach($implicit as $key2=>$value2) {
            if($value['parent_id'] == $value2['parent_id']) {
                unset($implicit[$key2]);
            }
        }
    }

    //print_r($implicit);
 
    $response['parents'] = array_merge($explicit, $implicit);
}

