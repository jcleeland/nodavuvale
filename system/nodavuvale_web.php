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
     * Returns the HTML & javascript for the individual lookup
     */
    public static function showFindIndividualLookAhead($individuals=array(), $fieldName="findindividual_lookup", $submitFieldName="findindividual_connect_to", $label="Connect to Existing Individual") {    
        if(empty($individuals)) {
            return "NO PEOPLE!";
        }
        $lookahead="";
        $lookahead .= '<label for="'.$fieldName.'" class="block text-gray-700">'.$label.'</label>'."\n";
        $lookahead .= '<input type="text" name="'.$fieldName.'" class="findindividual_lookup w-full px-4 py-2 border rounded-lg" placeholder="Type to search...">'."\n";
        $lookahead .= '<select name="'.$submitFieldName.'" class="findindividual_connect_to w-full px-4 py-2 border rounded-lg mt-2" size="5" style="display: none;">'."\n";
        $lookahead .= '     <option value="">Select someone...</option>'."\n";
        foreach ($individuals as $indi):
            $lookahead .= '     <option value="'.$indi['id'].'">'.$indi['first_names'].' '.$indi['last_name'].'</option>'."\n";
        endforeach;
        $lookahead .= '</select>'."\n";
        return($lookahead);
    }
    /**
     * Returns avatar HTML for the user selected
     */
    public function getAvatarHTML($user_id, $size="md", $classextra="avatar-float-left") {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);
        $user_name=$user['first_name']." ".$user['last_name'];
        $user_id=$user['id'];
        $user_avatar=!empty($user['avatar']) ? $user['avatar'] : "images/default_avatar.webp";
        $basehtml="<img src='".$user_avatar."' alt='".$user_name."' class='cursor-pointer avatar-img-".$size." ".$classextra."' title='".$user_name."' onClick='window.location=\"?to=family/users&user_id=".$user_id."\"'>";
        return $basehtml;
    }
    
    /** time ago */
    public static function timeSince($timestamp) {
        //$created_at = new DateTime($timestamp, new DateTimeZone('Australia/Sydney')); // Set the timezone to Australia/Sydney
        //$now = new DateTime('now', new DateTimeZone('Australia/Sydney')); // this is getting Australian Eastern Standard Time (AEST)
        $created_at = new DateTime($timestamp);
        $now = new DateTime('now');
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

    /**
     * Truncate text
     * 
     * @param string $text
     * @param int $wordLimit
     * @param string $readMoreMessage
     * @param string $textDivId
     * @return string
     * 
     */
    public function truncateText($text, $wordLimit = 100, $readMoreMessage='Read more', $textDivId = 'truncatedTextDiv', $method='popup') {
        //$text=htmlspecialchars($text);
        $words = explode(' ', $text);
        $text=addslashes($text);
        if (count($words) > $wordLimit) {
            $newWords = array_slice($words, 0, $wordLimit);
            $output = implode(' ', $newWords);
            if($method=="popup") {
                $output .= '<span title="'.htmlspecialchars($readMoreMessage).'" class="bold cursor-pointer text-blue" onClick="showStory(\'Story\', \''.$textDivId.'\')"> &hellip; </span>';
            } elseif ($method=="expand") {
                $output .= ' <span title="'.htmlspecialchars($readMoreMessage).'" class="bold cursor-pointer text-gray-800 text-sm bg-ocean-blue-800 nv-bg-opacity-20 rounded px-1" onClick="expandStory(\''.$textDivId.'\')">more &hellip; </span>';
            }
            return $output;
        }
        return $text;
    }
    

    public function individual_card($individual, $showrelationshipoption=false, $relationshiptype=null) {
        $individual['pref_name']=explode(" ", $individual['first_names'])[0];
        $keyimage= !empty($individual['keyimagepath']) ? $individual['keyimagepath'] : 'images/default_avatar.webp' ;
        $extraoption='';
        if($showrelationshipoption) {
            $extraoption='
                <button class="delete-relationship-btn absolute text-burnt-orange right-1 bottom-1 px-0 bg-burnt-orange-800 nv-bg-opacity-20 rounded-full text-xxs" data-individualcardid="individualcard_'.$individual['id'].'" data-relationshiptype="'.$relationshiptype.'" data-relationshipid="'. $individual['relationshipId'] .'" title="Remove this relationship with '. $individual['pref_name'] .' from the tree">&#10006;</button>';
        }
        $card='
        <div id="individualcard_'.$individual['id'].'" class="individual-item text-center p-1 shadow-lg rounded-lg relative gender_'.$individual['gender'] .'">
                <div class="relative z-2">
                    <h4 class="text-xl font-bold text-shadow-'. $individual['gender'] .' overflow-hidden whitespace-nowrap" title="'. $individual['pref_name'] . ' ' . $individual['last_name'] .'"><a href="?to=family/individual&individual_id='. $individual['id'] .'">'. $individual['pref_name'] . ' ' . $individual['last_name'] .'</a></h4>
                </div>';
        $card .= '
                <img class="keyimage-img-md keyimage-washed-out bg-opacity-20 border mt-0.5 float-left object-cover" src="'.$keyimage.'" title="'. $individual['first_names'] .' '. $individual['last_name'] .'">
                <p class="mt-2 text-sm text-gray-600">'. $individual['birth_prefix'] .' '. $individual['birth_year'].' - '.$individual['death_prefix'].' '. $individual['death_year'].'</p>';
        $card .= $extraoption;
        $card .= '
                <button class="edit-btn absolute right-1 top-1 px-1 bg-gray-800 bg-opacity-20 rounded-full" data-individual-id="'. $individual['id'] .'" title="Edit '. $individual['pref_name'] .'">&#9998;</button>';
        $card .= '
            </div>';
        
        return $card;
    }
    
    public function handleFileUpload($files, $discussion_id = null, $comment_id = null) {
        $uploadDir = 'uploads/';
        $uploadedFiles = [];

        foreach ($files['name'] as $key => $name) {
            $tmpName = $files['tmp_name'][$key];
            $filePath = $uploadDir . basename($name);

            if (move_uploaded_file($tmpName, $filePath)) {
                $uploadedFiles[] = $filePath;

                // Save file information to the database
                $this->db->query(
                    "INSERT INTO files (file_path, discussion_id, comment_id) VALUES (?, ?, ?)",
                    [$filePath, $discussion_id, $comment_id]
                );
            }
        }

        return $uploadedFiles;
    }

    public function handleDiscussionFileUpload($files, $discussion_id) {
        $uploadDir = 'uploads/discussions/';
        $uploadedFiles = [];

        foreach ($files['name'] as $key => $name) {
            $tmpName = $files['tmp_name'][$key];
            $filePath = $uploadDir . basename($name);

            if (move_uploaded_file($tmpName, $filePath)) {
                $uploadedFiles[] = $filePath;

                // Save file information to the database
                $this->db->query(
                    "INSERT INTO discussion_files (discussion_id, file_path, file_type) VALUES (?, ?, ?)",
                    [$discussion_id, $filePath, mime_content_type($filePath)]
                );
            }
        }

        return $uploadedFiles;
    }

    public function getFontAwesomeIcon($filename) {
        // Get the file extension
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        //Select an appropriate fontawesome icon based on the file extension
        switch ($ext) {
            case 'pdf':
                $icon = 'fa-file-pdf';
                break;
            case 'doc':
            case 'docx':
                $icon = 'fa-file-word';
                break;
            case 'xls':
            case 'xlsx':
                $icon = 'fa-file-excel';
                break;
            case 'ppt':
            case 'pptx':
                $icon = 'fa-file-powerpoint';
                break;
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
                $icon = 'fa-file-image';
                break;
            case 'zip':
            case 'rar':
                $icon = 'fa-file-archive';
                break;
            case 'txt':
                $icon = 'fa-file-alt';
                break;
            default:
                $icon = 'fa-file';
                break;
        }
        return $icon;
    }
    
}