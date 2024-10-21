<?php
/**
 * @var Database $db
 * @var Auth $auth
 */

// Check if the user is an admin
$user_id=$_SESSION['user_id'];
$is_admin = $auth->getUserRole() === 'admin';

// Fetch discussions from the database
$discussions = $db->fetchAll("SELECT discussions.*, users.first_name, users.last_name, users.avatar
    FROM discussions 
    INNER JOIN users ON discussions.user_id=users.id 
    WHERE discussions.is_individual != 1
    ORDER BY is_sticky DESC, created_at DESC");

// Function to fetch comments for a discussion
function getCommentsForDiscussion($discussion_id) {
    global $db;
    return $db->fetchAll("
        SELECT c.*, u.first_name, u.last_name, u.avatar 
        FROM discussion_comments c 
        INNER JOIN users u ON c.user_id = u.id 
        WHERE c.discussion_id = ? 
        ORDER BY c.created_at ASC
    ", [$discussion_id]);
}

// Handle deletion of discussions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_discussion'])) {
    $discussion_id = $_POST['discussion_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;

    // Check if the user is the author or an admin
    $discussion = $db->fetchOne("SELECT user_id FROM discussions WHERE id = ?", [$discussion_id]);
    if ($discussion && ($discussion['user_id'] == $user_id || $is_admin)) {
        // Double-check before deletion
        if ($db->fetchOne("SELECT id FROM discussions WHERE id = ? AND user_id = ?", [$discussion_id, $discussion['user_id']])) {
            $db->query("DELETE FROM discussions WHERE id = ?", [$discussion_id]);
        }
    }
    header('Location: ?to=communications/discussions');
    exit;
}


// Handle deletion of stickiness
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sticky'])) {
    $discussion_id = $_POST['discussion_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    // Check if the user is the author or an admin
    $discussion = $db->fetchOne("SELECT user_id FROM discussions WHERE id = ?", [$discussion_id]);
    if ($discussion && ($discussion['user_id'] == $user_id || $is_admin)) {
        $db->query("UPDATE discussions SET is_sticky = 0 WHERE id = ?", [$discussion_id]);
    }
    header('Location: ?to=communications/discussions');
    exit;
}

// Handle adding of stickiness
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_sticky'])) {
    $discussion_id = $_POST['discussion_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    // Check if the user is the author or an admin
    $discussion = $db->fetchOne("SELECT user_id FROM discussions WHERE id = ?", [$discussion_id]);
    if ($discussion && ($discussion['user_id'] == $user_id || $is_admin)) {
        $db->query("UPDATE discussions SET is_sticky = 1 WHERE id = ?", [$discussion_id]);
    }
    header('Location: ?to=communications/discussions');
    exit;
}


// Handle deletion of comments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $comment_id = $_POST['comment_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;

    // Check if the user is the author or an admin
    $comment = $db->fetchOne("SELECT user_id FROM discussion_comments WHERE id = ?", [$comment_id]);
    if ($comment && ($comment['user_id'] == $user_id || $is_admin)) {
        // Double-check before deletion
        if ($db->fetchOne("SELECT id FROM discussion_comments WHERE id = ? AND user_id = ?", [$comment_id, $comment['user_id']])) {
            $db->query("DELETE FROM discussion_comments WHERE id = ?", [$comment_id]);
        }
    }
    header('Location: ?to=communications/discussions');
    exit;
}

// Handle posting of new comments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'], $_POST['discussion_id'], $_POST['user_id'])) {
    $comment = trim($_POST['comment']);
    $discussion_id = (int)$_POST['discussion_id'];
    $user_id = (int)$_POST['user_id'];

    // Validate comment
    if (!empty($comment) && $discussion_id > 0 && $user_id > 0) {
        // Insert the new comment into the database
        $db->insert("INSERT INTO discussion_comments (discussion_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())", [$discussion_id, $user_id, $comment]);
        
        // Optionally redirect to avoid form resubmission issues
        header("Location: index.php?to=communications/discussions");
        exit;
    }
}

// Handle posting of new discussions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_discussion'], $_POST['user_id'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $user_id = (int)$_POST['user_id'];
    $is_sticky = isset($_POST['is_sticky']) ? 1 : 0;
    $is_news = isset($_POST['is_news']) ? 1 : 0;

    // Validate discussion
    if (!empty($title) && !empty($content) && $user_id > 0) {
        // Insert the new discussion into the database
        $db->insert("INSERT INTO discussions (user_id, title, content, is_sticky, is_news, created_at) VALUES (?, ?, ?, ?, ?, NOW())", [$user_id, $title, $content, $is_sticky, $is_news]);
        
        // Optionally redirect to avoid form resubmission issues
        header("Location: index.php?to=communications/discussions");
        exit;
    }
}

?>

<!-- Hero Section -->
<section class="hero text-white py-20">
    <div class="container hero-content">
        <h2 class="text-4xl font-bold">News & Discussions</h2>
        <p class="mt-4 text-lg">Join the conversation, share news, and stay connected.</p>
    </div>
</section>

<!-- New Discussion Section -->
<section class="container mx-auto py-12 px-4 sm:px-6 lg:px-8 mb-0">
    <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="discussion-content">
            <?php $my_avatar_path=$auth->getAvatarPath() ?>
            <?php echo $web->getAvatarHTML($user_id, "md", "avatar-float-left"); ?>
            <div class='discussion-content'>
                <form method="POST" class="mt-4">
                    <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>"> <!-- Assuming user is logged in -->
                    <div id="additional-fields" style="display: none;">
                        <input type="text" name="title" class="w-full border rounded-lg p-2 mb-2" placeholder="Title" required>
                        <div class="grid grid-cols-2 gap-8 text-sm text-gray-500">
                            <div class="p-2">
                                <input type="checkbox" id="is_sticky" name="is_sticky" class="mr-2">
                                <label for="is_sticky">Pin to top</label>
                            </div>
                            <div class="p-2">
                                <input type="checkbox" id="is_news" name="is_news" class="mr-2">
                                <label for="is_news">News item</label>
                            </div>
                        </div>
                        <button type="submit" name="new_discussion" class="bg-warm-red text-white py-2 px-4 rounded-lg hover:bg-burnt-orange float-right">
                            &#9992; <!-- Unicode for paper plane -->
                        </button>
                    </div>
                    <textarea id="content" name="content" rows="3" class="w-full border rounded-lg p-2 mb-2" placeholder="Share your thoughts..." required></textarea>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
    document.getElementById('content').addEventListener('input', function() {
        var additionalFields = document.getElementById('additional-fields');
        if (this.value.trim() !== '') {
            additionalFields.style.display = 'block';
        } else {
            additionalFields.style.display = 'none';
        }
    });
</script>

<!-- Discussions Section -->
<section class="container mx-auto py-0 pb-6 px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8">
        <?php if (!empty($discussions)): ?>
            <!-- Display all discussions -->
            <?php foreach ($discussions as $discussion): ?>
                <?php $avatar_path=isset($discussion['avatar']) ? $discussion['avatar'] : 'images/default_avatar.webp'; ?>
                <div class="bg-white shadow-lg rounded-lg p-6 mb-6 relative">
                    <?php if ($discussion['is_sticky']): ?>
                        <form method="POST" class="absolute top-0 right-0 mt-1 mr-2 text-lg text-deep-green">
                            <input type="hidden" name="discussion_id" value="<?= $discussion['id'] ?>">
                            <button type="submit" name="delete_sticky" title="This item is pinned to the top">
                                <i class="fa fa-thumbtack "></i>
                            </button>
                        </form>
                    <?php elseif ($is_admin) : ?>
                        <form method="POST" class="absolute top-0 right-0 mt-1 mr-2 text-lg text-deep-green-disabled hover:text-deep-green">
                            <input type="hidden" name="discussion_id" value="<?= $discussion['id'] ?>">
                            <button type="submit" name="make_sticky" title="Pin this item to the top">
                                <i class="fa fa-thumbtack "></i>
                            </button>
                        </form>                        
                    <?php endif; ?>
                    <?php if ($is_admin || $_SESSION['user_id'] == $discussion['user_id']): ?>
                        <form method="POST" class="absolute top-0 right-10 mt-1 mr-2 text-lg text-brown-500 hover:text-brown-700">
                            <input type="hidden" name="discussion_id" value="<?= $discussion['id'] ?>">
                            <button type="submit" name="delete_discussion" title="Delete this item">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                    <?php endif; ?>   
                    <div class="discussion-item"> 
                        <img src="<?= htmlspecialchars($avatar_path) ?>" alt="User Avatar" class="avatar-img-md avatar-float-left" title="<?= $discussion['first_name'] ?> <?= $discussion['last_name'] ?>">                
                        <div class='discussion-content'>
                            <div class="text-sm text-gray-500">
                                <b><?= $discussion['first_name'] ?> <?= $discussion['last_name'] ?></b><br />
                                <span title="<?= date('F j, Y, g:i a', strtotime($discussion['created_at'])) ?>"><?= $web->timeSince($discussion['created_at']); ?></span>
                            </div>
                            <h3 class="text-2xl font-bold"><?= htmlspecialchars($discussion['title']) ?></h3>
                            <p class="mt-2"><?= htmlspecialchars($discussion['content']) ?></p>
                            <div class="discussion-reactions" data-discussion-id="<?= $discussion['id'] ?>">
                                <svg alt="Like" class="like-image" viewBox="0 0 32 32" xml:space="preserve" width="18px" height="18px" fill="#000000">
                                    <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                    <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                    <g id="SVGRepo_iconCarrier">
                                        <style type="text/css">
                                            .st0{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:10;}
                                            .st1{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
                                            .st2{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:5.2066,0;}
                                        </style>
                                        <path class="st0" d="M11,24V14H5v12h6v-2.4l0,0c1.5,1.6,4.1,2.4,6.2,2.4h6.5c1.1,0,2.1-0.8,2.3-2l1.5-8.6c0.3-1.5-0.9-2.4-2.3-2.4H20V6.4C20,5.1,18.7,4,17.4,4h0C16.1,4,15,5.1,15,6.4v0c0,1.6-0.5,3.1-1.4,4.4L11,13.8"></path>
                                    </g>
                                </svg>
                                <div class="reaction-buttons" title="Reactions">
                                    <button class="reaction-btn" data-reaction="like" title="Like">👍</button>
                                    <button class="reaction-btn" data-reaction="love" title="Love">❤️</button>
                                    <button class="reaction-btn" data-reaction="haha" title="Haha">😂</button>
                                    <button class="reaction-btn" data-reaction="wow" title="Wow">😮</button>
                                    <button class="reaction-btn" data-reaction="sad" title="Sad">😢</button>
                                    <button class="reaction-btn" data-reaction="angry" title="Angry">😡</button>
                                    <button class="reaction-btn" data-reaction="care" title="Care">🤗</button>
                                    <button class="reaction-btn" data-reaction="remove" title="Remove">❌</button>
                                </div>
                                <div class="reaction-summary-container">
                                    <div class="reaction-summary">
                                        <!-- This will be filled dynamically with AJAX -->
                                    </div>
                                    
                                </div>
                            </div>
                            <?php if ($is_admin || $_SESSION['user_id'] == $discussion['user_id']): ?>
                                <form method="POST" class="mt-4 text-right text-xs" onSubmit="return confirm('Are you sure you want to delete this entire dicussion?')">
                                    <input type="hidden" name="discussion_id" value="<?= $discussion['id'] ?>">
                                    <button type="submit" name="delete_discussion" class="text-red-500 hover:text-red-700">Delete</button>
                                </form>
                            <?php endif; ?>
                            <div class="comments mt-4">
                                <!-- Fetch and display comments -->
                                <?php $comments = getCommentsForDiscussion($discussion['id']); ?>
                                <?php if (!empty($comments)): ?>
                                    <h4 class="font-semibold">Comments:</h4>
                                    <?php foreach ($comments as $comment): ?>
                                        <div class="bg-gray-100 p-4 rounded-lg mt-2">
                                            <img src="<?= isset($comment['avatar']) ? $comment['avatar'] : 'images/default_avatar.webp' ?>" alt="User Avatar" class="avatar-img-sm avatar-float-left" title="<?= $comment['first_name'] ?> <?= $comment['last_name'] ?>">
                                            <div class="comment-content">
                                                <div class="text-sm text-gray-500">
                                                    <b><?= htmlspecialchars($comment['first_name']) ?> <?= $comment['last_name'] ?></b><br />
                                                    <span title="<?= date('F j, Y, g:i a', strtotime($comment['created_at'])) ?>"><?= $web->timeSince($comment['created_at']); ?></span>
                                                </div>
                                                <p><?= htmlspecialchars($comment['comment']) ?></p>
                                                <div class="comment-reactions" data-comment-id="<?= $comment['id'] ?>">
                                                    <svg alt="Remove Reaction" class="remove-reaction-image" viewBox="0 0 32 32" xml:space="preserve" width="18px" height="18px" fill="#000000">
                                                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                                        <g id="SVGRepo_iconCarrier">
                                                            <style type="text/css">
                                                                .st0{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:10;}
                                                                .st1{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
                                                                .st2{fill:none;stroke:#000000;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:5.2066,0;}
                                                            </style>
                                                            <path class="st0" d="M11,24V14H5v12h6v-2.4l0,0c1.5,1.6,4.1,2.4,6.2,2.4h6.5c1.1,0,2.1-0.8,2.3-2l1.5-8.6c0.3-1.5-0.9-2.4-2.3-2.4H20V6.4C20,5.1,18.7,4,17.4,4h0C16.1,4,15,5.1,15,6.4v0c0,1.6-0.5,3.1-1.4,4.4L11,13.8"></path>
                                                        </g>
                                                    </svg>
                                                    <div class="reaction-buttons" title="Reactions">
                                                        <button class="reaction-btn" data-reaction="like" title="Like">👍</button>
                                                        <button class="reaction-btn" data-reaction="love" title="Love">❤️</button>
                                                        <button class="reaction-btn" data-reaction="haha" title="Haha">😂</button>
                                                        <button class="reaction-btn" data-reaction="wow" title="Wow">😮</button>
                                                        <button class="reaction-btn" data-reaction="sad" title="Sad">😢</button>
                                                        <button class="reaction-btn" data-reaction="angry" title="Angry">😡</button>
                                                        <button class="reaction-btn" data-reaction="care" title="Care">🤗</button>
                                                        <button class="reaction-btn" data-reaction="remove" title="Remove">❌</button>
                                                    </div>
                                                    <div class="reaction-summary-container">
                                                        <div class="reaction-summary">
                                                            <!-- This will be filled dynamically with AJAX -->
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if ($is_admin || $_SESSION['user_id'] == $comment['user_id']): ?>
                                                <form method="POST" class="mt-2 text-right" onSubmit="return confirm('Are you sure you want to delete this comment?')">
                                                    <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                                    <button type="submit" name="delete_comment" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <!-- Add a new comment -->
                            <form method="POST" class="comments mt-4 relative">
                                <textarea name="comment" rows="3" class="w-full border rounded-lg p-2" placeholder="Add a comment..." required></textarea>
                                <input type="hidden" name="discussion_id" value="<?= $discussion['id'] ?>">
                                <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>"> <!-- Assuming user is logged in -->
                                
                                <button type="submit" title="Post comment" class="submit-button mt-2 bg-deep-green text-white py-1 px-2 rounded-lg hover:bg-burnt-orange">
                                    <i class="fa fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No discussions available at the moment. Be the first to start a conversation!</p>
        <?php endif; ?>
    </div>
</section>
