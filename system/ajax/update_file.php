<?php
/** 
 * This ajax file will update a file entry
 *  - there's really only one thing to update - and that's file_description
 *  - otherwise it's all about deleting
 */
$response=array();
if(!isset($data['fileId']) || !isset($data['fileDescription'])) {
    $response['status']='error';
    $response['message']='No file ID or file Description provided';
    return;
}

$fileId = (int) $data['fileId'];
$fileDescription = (string) $data['fileDescription'];
$mediaDatePayload = $data['media_date'] ?? null;
[$mediaDateValue, $mediaDatePrecision, $mediaDateApprox] = Utils::prepareFlexibleDateFromArray(is_array($mediaDatePayload) ? $mediaDatePayload : null);

$updateSql = "UPDATE files SET file_description = ?, media_date = ?, media_date_precision = ?, media_date_is_approximate = ? WHERE id = ?";
try {
    $db->update($updateSql, [$fileDescription, $mediaDateValue, $mediaDatePrecision, $mediaDateApprox, $fileId]);
    if (isset($data['linkId'])) {
        $linkId = (int) $data['linkId'];
        $db->update(
            "UPDATE file_links SET link_date = ?, link_date_precision = ?, link_date_is_approximate = ? WHERE id = ?",
            [$mediaDateValue, $mediaDatePrecision, $mediaDateApprox, $linkId]
        );
    }
    $response['status'] = 'success';
    $response['message'] = 'File details updated successfully';
    $response['media_year'] = '';
    $response['media_month'] = '';
    $response['media_day'] = '';
    $response['media_is_approximate'] = $mediaDateApprox;
    if ($mediaDateValue !== null) {
        $parts = explode('-', $mediaDateValue);
        if (count($parts) === 3) {
            $response['media_year'] = $parts[0];
            if ($mediaDatePrecision === 'month' || $mediaDatePrecision === 'day') {
                $response['media_month'] = ltrim($parts[1], '0');
            }
            if ($mediaDatePrecision === 'day') {
                $response['media_day'] = ltrim($parts[2], '0');
            }
        }
    }
} catch (Exception $e) {
    $response['status']='error';
    $response['message']='Error updating file details';
}
