<?php
// system/Web.php

class Web {
    private $db;    // Database instance

    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    public static function redirect($url) {
        header("Location: $url");
        exit();
    }

    public static function startSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function checkLogin() {
        self::startSession();
        if (!isset($_SESSION['user_id'])) {
            self::redirect('index.php?page=login');
        }
    }

    public static function getRootId() {
        // Set the root id for the tree
        // If none has been set, default to 1
        $rootId=1;

        // If the user has a set preferred root id, use that instead
        if(isset($_SESSION['preferred_root_id'])) {
            $rootId = $_SESSION['preferred_root_id'];
        }
        // If the user has requested a different root id, use that instead
        if(isset($_GET['root_id'])) {
            $rootId = $_GET['root_id'];
        }
        if(isset($_POST['root_id'])) {
            $rootId = $_POST['root_id'];
        }
        return $rootId;
    }
    
    /**
     * Returns avatar HTML for the user selected
     */
    public function getAvatarHTML($user_id, $size="md", $classextra="avatar-float-left") {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);
        $user_name=$user['first_name']." ".$user['last_name'];
        $user_id=$user['id'];
        $user_avatar=!empty($user['avatar']) ? $user['avatar'] : "images/default_avatar.webp";
        $basehtml="<img src='".$user_avatar."' alt='".$user_name."' class='avatar-img-".$size." ".$classextra."' title='".$user_name."'>";
        return $basehtml;
    }
    
    /** time ago */
    public static function timeSince($timestamp) {
        $created_at = new DateTime($timestamp, new DateTimeZone('Australia/Sydney')); // Set the timezone to Australia/Sydney
        $now = new DateTime('now', new DateTimeZone('Australia/Sydney')); // this is getting Australian Eastern Standard Time (AEST)
        $interval = $created_at->diff($now);
        if ($interval->y > 0) {
            $time_ago = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
        } elseif ($interval->m > 0) {
            $time_ago = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
        } elseif ($interval->d > 0) {
            $time_ago = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
        } elseif ($interval->h > 0) {
            $time_ago = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
        } elseif ($interval->i > 0) {
            $time_ago = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
        } else {
            $time_ago = 'just now';
        }
        return $time_ago;
    }

    public function truncateText($text, $wordLimit = 100) {
        $words = explode(' ', $text);
        if (count($words) > $wordLimit) {
            return implode(' ', array_slice($words, 0, $wordLimit)) . '...';
        }
        return $text;
    }
    

    public function individual_card($individual) {
        $individual['pref_name']=explode(" ", $individual['first_names'])[0];
        $keyimage= !empty($individual['keyimagepath']) ? $individual['keyimagepath'] : 'images/default_avatar.webp' ;
        $card='<div class="individual-item text-center p-1 shadow-lg rounded-lg relative gender_'.$individual['gender'] .'">
                <div class="relative z-2">
                    <h4 class="text-xl font-bold text-shadow-'. $individual['gender'] .'"><a href="?to=family/individual&individual_id='. $individual['id'] .'">'. $individual['pref_name'] . ' ' . $individual['last_name'] .'</a></h4>
                </div>
                <img class="keyimage-img-md keyimage-washed-out bg-opacity-20 border mt-0.5 float-left object-cover" src="'.$keyimage.'" title="'. $individual['first_names'] .' '. $individual['last_name'] .'">
                <p class="mt-2 text-sm text-gray-600">'. $individual['birth_prefix'] .' '. $individual['birth_year'].' - '.$individual['death_prefix'].' '. $individual['death_year'].'</p>
                <button class="edit-btn absolute right-1 top-1 px-1 bg-gray-800 bg-opacity-20 rounded-full" data-individual-id="'. $individual['id'] .'" title="Edit '. $individual['pref_name'] .'">&#9998;</button>
        </div>';
        return $card;
    }
    
}