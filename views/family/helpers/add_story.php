<?php
//Process the form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_individual_story'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $individual_id = $_POST['individual_id'];
    $user_id = $_POST['user_id'];
    $sql = "INSERT INTO discussions (title, content, individual_id, user_id) VALUES (?, ?, ?, ?)";
    $db->insert($sql, [$title, $content, $individual_id, $user_id]);
    
}
?>
    <div id="storyModal" class="modal">
        <div class="modal-content">
            <div id="modal-header" class="modal-header">
                <span class="close-story-btn">&times;</span>
                <h2 id="modal-title">Add A Story<span id='adding_relationship_to'></span></h2>
            </div>
            <div class="modal-body">
                <?php echo $web->getAvatarHTML($user_id, "md", "avatar-float-left object-cover"); ?>
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
