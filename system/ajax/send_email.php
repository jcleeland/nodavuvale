<?php
/** 
 * system/ajax/send_email.php
 * 
 * A script to send an email to a user
 * 
 */

 /*
    @uses Database class
    @uses Auth class
    @uses Web class
    @uses Utils class
*/

// Create an instance of PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

 $response=[
    'status'=>'error',
    'message'=>'Invalid request'
];
// Check that an email address, recipient name, subject and message were provided

if(!isset($data['email']) || !isset($data['name']) || !isset($data['subject']) || !isset($data['message'])) {
    $response['message']='Invalid request';
    echo json_encode($response);
    return $response;
}

// If the user is not an admin, check for the user_id and get the name and email address for the sender 
//  from the database
if($auth->getUserRole() !== 'admin') {
    if(!isset($data['user_id']) || !is_numeric($data['user_id'])) {
        $response['message']='Invalid user ID';
        echo json_encode($response);
        return $response;
    }
    $user = $db->fetchOne("SELECT first_name, last_name, email FROM users WHERE id = ?", [$data['user_id']]);
    if(!$user) {
        $response['message']='User not found';
        echo json_encode($response);
        return $response;
    }
    $data['name'] = $user['first_name'].' '.$user['last_name'];
    $data['email'] = $user['email'];
}

// Get the email server details from the site_settings table (rows with "name"=email_server, email_port, email_username and email_password)
$settings = $db->fetchAll("SELECT name, value FROM site_settings WHERE name IN ('email_server', 'email_port', 'email_username', 'email_password')");
$smtp_server = '';
$smtp_port = '';
$smtp_username = '';
$smtp_password = '';
foreach($settings as $setting) {
    if($setting['name'] == 'email_server') {
        $smtp_server = $setting['value'];
    } elseif($setting['name'] == 'email_port') {
        $smtp_port = $setting['value'];
    } elseif($setting['name'] == 'email_username') {
        $smtp_username = $setting['value'];
    } elseif($setting['name'] == 'email_password') {
        $smtp_password = $setting['value'];
    }
}

// If any of the settings are missing, return an error
if($smtp_server == '' || $smtp_port == '' || $smtp_username == '' || $smtp_password == '') {
    $response['message']='Email server settings are missing';
    echo json_encode($response);
    return $response;
}

// Send the email
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp_server;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_username;
    $mail->Password = $smtp_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtp_port;
    $mail->setFrom($smtp_username, 'NodavuVale');
    $mail->addAddress($data['email'], $data['name']);
    $mail->isHTML(true);
    $mail->Subject = $data['subject'];
    $mail->Body = $data['message'];
    $mail->send();
    $response['status']='success';
    $response['message']='Email sent';
} catch (Exception $e) {
    $response['message']='Error sending email: '.$mail->ErrorInfo;
}