<?php
// Create an instance of PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../EmailTemplate.php';

//Get website url location from $_SERVER
// so that a link can be included in the email. 
$site_url = $_SERVER['HTTP_HOST'];

//Find out if the current URL is http: or https:

if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $site_url = 'https://'.$site_url;
} else {
    $site_url = 'http://'.$site_url;
}
//See if the root for this site is in a subdirectory
if(isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/') {
    $site_url .= dirname($_SERVER['REQUEST_URI']);
}
$site_url .= '/?to=login';

$response=[
    'status'=>'success',
    'message'=>'Mail sent'
];

//Check if current user is an admin, or if the request to update the user contains an email address to find
if($auth->getUserRole() !== 'admin' && !isset($data['email'])) {
    $response['message']='You do not have permission to reset passwords';
    echo json_encode($response);
    return $response;
}

//If the user is an admin & is logged in, make sure there is a user_id
if($auth->getUserRole() === 'admin' && !isset($data['user_id'])) {
    $response['message']='Invalid user ID';
    echo json_encode($response);
    return $response;
}

//If the user is not an admin, find the user_id from the email address
if($auth->getUserRole() !== 'admin') {
    $user = $db->fetchOne("SELECT id, first_name, last_name, email FROM users WHERE email = ?", [$data['email']]);
    if(!$user) {
        $response['message']='User not found';
        echo json_encode($response);
        return $response;
    }
    $data['user_id'] = $user['id'];
} else {
    $user = $db->fetchOne("SELECT first_name, last_name, email FROM users WHERE id = ?", [$data['user_id']]);
    if(!$user) {
        $response['message']='User not found';
        echo json_encode($response);
        return $response;
    }
}

$site_settings=$db->getSiteSettings();
$site_name=$site_settings['site_name'];
$smtp_server=$site_settings['email_server'];
$smtp_port=$site_settings['email_port'];
$smtp_username=$site_settings['email_username'];
$smtp_password=$site_settings['email_password'];

$renderedEmail = EmailTemplate::render($site_settings, 'login_details', [
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'email' => $user['email'],
    'site_name' => $site_name,
    'login_url' => $site_url,
]);
$emailMessage = $renderedEmail['body'];
$emailSubject = $renderedEmail['subject'];

//Send an email to the user 
if($response['status'] === 'success') {
    $user = $db->fetchOne("SELECT first_name, last_name, email FROM users WHERE id = ?", [$data['user_id']]);
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp_server;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_username;
    $mail->Password = $smtp_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtp_port;
    $mail->setFrom($smtp_username, $site_name.' Admin');
    $mail->addAddress($user['email'], $user['first_name'].' '.$user['last_name']);
    if($site_settings['bcc_allemails']==1 && isset($site_settings['notifications_email'])) {
        $mail->addBCC($site_settings['notifications_email']);
    }
    $mail->isHTML(true);
    $mail->Subject = $emailSubject;
    $mail->Body = $emailMessage;
    $mail->send();
}
