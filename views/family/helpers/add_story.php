<?php
//Process adding a story
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_individual_story'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $individual_id = $_POST['individual_id'];
    $user_id = $_POST['user_id'];
    try{
        $sql = "INSERT INTO discussions (title, content, individual_id, user_id) VALUES (?, ?, ?, ?)";
        $db->insert($sql, [$title, $content, $individual_id, $user_id]);

        // Redirect to the same page to avoid form resubmission
        ?>
        <script>
            window.location = '?to=family/individual&individual_id=<?= $individual_id ?>';
        </script>
        <?php
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}
//Deleting a story
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_discussion'])) {
    $discussionId= $_POST['discussionId'];
    //Only allow a delete by user who posted, or an admin
    // Get the user id of the current uuser
    $currentUserId = $_SESSION['user_id'];
   try{
        //IF this person is not an admin, make sure that the user_id of the discussion matches the current user
        if($auth->getUserRole() !== 'admin') {
            $sql = "DELETE FROM discussions WHERE id = ? AND user_id = ?";
            $params= [$discussionId, $currentUserId];
        } else {
            $sql = "DELETE FROM discussions WHERE id = ?";
            $params= [$discussionId];
        }
        $db->delete($sql, $params);

        // Redirect to the same page to avoid form resubmission
        ?>
        <script>
            window.location = '?to=family/individual&individual_id=<?= $individual_id ?>';
        </script>
        <?php
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

//Posting a comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_comment'])) {
    $content = $_POST['comment'];
    $discussion_id = $_POST['discussion_id'];
    $user_id = $_POST['user_id'];
    try{
        $sql = "INSERT INTO discussion_comments (comment, discussion_id, user_id) VALUES (?, ?, ?)";
        $db->insert($sql, [$content, $discussion_id, $user_id]);

        // Redirect to the same page to avoid form resubmission
        ?>
        <script>
            window.location = '?to=family/individual&individual_id=<?= $individual_id ?>';
        </script>
        <?php
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

//Deleting a comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment']) && $_POST['delete_comment'] === 'true') {
    $commentId = isset($_POST['commentId']) ? (int) $_POST['commentId'] : 0;
    $currentUserId = $_SESSION['user_id'] ?? 0;

    if ($commentId > 0 && $currentUserId) {
        $comment = $db->fetchOne("SELECT user_id FROM discussion_comments WHERE id = ?", [$commentId]);
        if ($comment && ($comment['user_id'] == $currentUserId || $auth->getUserRole() === 'admin')) {
            try {
                $db->beginTransaction();
                $db->query("DELETE FROM comment_reactions WHERE comment_id = ?", [$commentId]);
                $db->query("DELETE FROM discussion_comments WHERE id = ?", [$commentId]);
                $db->commit();
            } catch (Exception $e) {
                if (method_exists($db, 'rollBack')) {
                    $db->rollBack();
                }
                error_log($e->getMessage());
            }
        }
    }
    ?>
    <script>
        window.location = '?to=family/individual&individual_id=<?= $individual_id ?>';
    </script>
    <?php
}

?>
    <div id="storyModal" class="modal">
        <div class="modal-content w-4/5 sm:w-3/5 min-w-15 max-w-20 max-h-screen my-5 overflow-y-auto">
            <div id="modal-header" class="modal-header">
                <span class="close-story-btn" onClick='document.getElementById("storyModal").style.display="none";'>&times;</span>
                <h2 id="modal-title">Add A Story<span id='adding_relationship_to'></span></h2>
            </div>
            <div class="modal-body">
                <?php echo $web->getAvatarHTML($user_id, "md", "avatar-float-left object-cover mr-1 hidden sm:block"); ?>
                <div class='discussion-content'>
                    <form method="POST" class="mt-4">
                        <input type="hidden" name="individual_id" value="<?= $individual_id ?>" id="story-individual_id">
                        <input type="hidden" name="user_id" value="<?= $user_id ?>"> <!-- Assuming user is logged in -->
                        <input type="text" name="title" class="w-full border rounded-lg p-2 mb-2" placeholder="Title" required>
                        <textarea required id="content" name="content" rows="3" class="w-full border rounded-lg p-2 mb-2" placeholder="Write your story..." required></textarea>
                        <button type="submit" name="new_individual_story" class="bg-deep-green text-white py-2 px-4 rounded-lg hover:bg-burnt-orange float-right" title="Submit story">
                            <i class="fa fa-paper-plane"></i>
                        </button>                    
                    </form>
                </div>
            </div>
        </div>
    </div>
