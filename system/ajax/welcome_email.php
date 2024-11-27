<?php
// Create an instance of PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

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

$site_name=$db->getSiteSettings()['site_name'];
$smtp_server=$db->getSiteSettings()['email_server'];
$smtp_port=$db->getSiteSettings()['email_port'];
$smtp_username=$db->getSiteSettings()['email_username'];
$smtp_password=$db->getSiteSettings()['email_password'];

$welcomeEmailMessage='Hi '.$user['first_name'].',
<p>Welcome! Your account on the <a href="'.$site_url.'">'.$site_name.'</a> website has been activated.</p>
<p>This is a site which has been set up for descendants of the family of Soli Losana Nataleira to record and view their family history.</p>
<p>It also aims to keep us in touch with each other, with family in Nataleira and Navasau, and to learn more about each other across our full family diaspora.</p>
<p>The site has been developed and is managed by me, <a href="mailto:jason@cleeland.org">Jason Cleeland</a>, <i>(Soli->Mary Macdonald->William Macdonald->Alexander Macdonald->Jan Macdonald->Jason)</i>.</p>
<p>It is hosted on my local home server in Melbourne, Australia. It\'s not as fast as it will eventually be when I move it to a more permanent web location.</p>
<p>It\'s a work in progress, so please be patient and make sure you let me know of anything that you want or which doesn\'t work the way you\'d like it to.</p>
<hr />
<p><b>Your '.$site_name.' Account</b></p>
<p>Login using the following details:</p>
<p><b>Email/Login:</b> '.$user['email'].'</p>
<p><b>Password:</b> The password you created when you registered.</p>
<p>Login here: '.$site_url.'</p>
<hr />
<p><i>Note: If you do not know what this is and didn\'t want an account on <i>'.$site_name.'</i>, please contact the site administrator.</i></p>
<p>Lolomas,</p>
<b><i>Jason ('.$site_name.' Admin)</i></b><br />
jason@cleeland.org';
$welcomeEmailSubject='Welcome to '.$site_name.'. This is your account information.';

$emailMessage=$welcomeEmailMessage;
$emailSubject=$welcomeEmailSubject;

$site_settings=$db->getSiteSettings();

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