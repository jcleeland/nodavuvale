<?php
// system/nodavuvale_auth.php
// Create an instance of PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


class Auth {
    private $db;

    // Define constants for status messages to standardize responses
    const LOGIN_SUCCESS = 'success';
    const LOGIN_INVALID = 'invalid';
    const LOGIN_UNAPPROVED = 'unapproved';

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function login($email, $password) {
        // Fetch the user from the database by email
        $user = $this->db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

        if ($user) {
            // Check if the user is approved
            if ($user['approved'] == 0) {
                return self::LOGIN_UNAPPROVED;  // User is not approved yet
            }

            // Verify the password
            if (password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // Store user ID and details in session after successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['approved'] = $user['approved'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['individuals_id'] = $user['individuals_id'];
                $_SESSION['last_login'] = $user['last_login'];
                $_SESSION['last_view'] = $user['last_view'];

                // Update the "last_login" timestamp in the database
                $this->db->query("UPDATE users SET last_login = NOW(), last_view = NOW() WHERE id = ?", [$user['id']]);

                return self::LOGIN_SUCCESS;  // Login successful
            }
        }

        return self::LOGIN_INVALID;  // Invalid email or password
    }

    // Retrieve the current logged-in user's role
    public function getUserRole() {
        if ($this->isLoggedIn()) {
            $user_id = $_SESSION['user_id'];

            // Fetch the user's role from the database
            $user = $this->db->fetchOne("SELECT role FROM users WHERE id = ?", [$user_id]);

            if ($user) {
                return $user['role'];  // Return the role (e.g., 'admin', 'member', 'unconfirmed')
            }
        }
        
        return null;  // Return null if not logged in or user not found
    }   
    
    public function getAvatarPath() {
        if($this->isLoggedIn()) {
            $user_id = $_SESSION['user_id'];
            $user = $this->db->fetchOne("SELECT avatar FROM users WHERE id = ?", [$user_id]);
            if($user) {
                if($user['avatar'] == null) {
                    return "images/default_avatar.webp";
                } else {
                    return $user['avatar'];
                }
            }
        }
    }

    public function logout() {
        Web::startSession();
        // Regenerate the session ID after logout for security
        session_regenerate_id(true);
        session_destroy();
    }

    public function isLoggedIn() {
        Web::startSession();
        return isset($_SESSION['user_id']) && isset($_SESSION['approved']) && $_SESSION['approved'] > 0;
    }

    public function register($first_name, $last_name, $email, $password, $relative_name, $relationship, $role, $approved) {
        // Check if the email already exists
        $existing_user = $this->db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        if ($existing_user) {
            return false;  // User already exists
        }

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        //echo "Inserting "."INSERT INTO users (email, password, relative_name, relationship, role, approved) VALUES (?, ?, ?, ?, 'unconfirmed', 0)";
        // Insert the new user into the database with 'unconfirmed' role and 'approved' = 0
        try {
            $this->db->query(
                "INSERT INTO users (first_name, last_name, email, password, relative_name, relationship, role, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$first_name, $last_name, $email, $hashed_password, $relative_name, $relationship, $role, $approved]
            );
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }

        //If the save worked, then send an email to the user
        if($this->db->lastInsertId() == 0) {
            return false;
        }

        //Now send an email to the admin
        $to = "jason@cleeland.org";
        $subject = "New NodaVuvale User Registration ($first_name $last_name)";
        $message = "<p>A new user has registered for your NodaVuvale Site!</p><p><b>Details:</b><br /> $first_name $last_name ($email).</p> <p>Please log in to the admin panel to approve or deny their registration.</p>";
        
        //Send SMTP email, not using the PHP mail function
        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->SMTPDebug = SMTP::DEBUG_OFF;                      // Enable verbose debug output
            $mail->isSMTP();                                            // Send using SMTP
            $mail->Host       = $this->db->getSiteSettings()['email_server'];  // Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = $this->db->getSiteSettings()['email_username'];                     // SMTP username
            $mail->Password   = $this->db->getSiteSettings()['email_password'];                               // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
            $mail->Port       = $this->db->getSiteSettings()['email_port'];                                    // TCP port to connect to

            //Send the email
            $mail->setFrom('jason@cleeland.org');
            $mail->addAddress($to);     // Add a recipient
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->send();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
            


        return true;  // Registration successful
    }

    public function approveAccess($user_id) {
        //First make sure that the person doing this is an admin
        if($this->getUserRole() != 'admin') {
            return false;
        }
        // Update the user's role to 'member' and set 'approved' to 1
        $this->db->query("UPDATE users SET role = 'member', approved = 1 WHERE id = ?", [$user_id]);
    }
}
