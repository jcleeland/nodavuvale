document.addEventListener('DOMContentLoaded', function () {
    const reactionButtons = document.querySelectorAll('.reaction-btn');

    reactionButtons.forEach(button => {
        button.addEventListener('click', function () {
            const reaction = this.getAttribute('data-reaction');
            const discussionId = this.closest('.discussion-reactions')?.getAttribute('data-discussion-id');
            const commentId = this.closest('.comment-reactions')?.getAttribute('data-comment-id');
            const userId = document.getElementById('js_user_id').value;

            let data = {
                method: commentId ? 'react_to_comment' : 'react_to_discussion',
                data: {
                    reaction,
                    user_id: userId,
                    discussion_id: discussionId,
                    comment_id: commentId
                }
            };

            if (reaction === 'remove') {
                data.method = commentId ? 'remove_comment_reaction' : 'remove_discussion_reaction';
                delete data.data.reaction; // Remove the reaction from the data
            }

            console.log('Sending data:', data); // Log the data being sent

            // Make the AJAX request
            fetch('ajax.php', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(result => {
                console.log('Received result:', result); // Log the result received
                if (result.success) {
                    // Update the reaction summary
                    updateReactionSummary(discussionId, commentId);
                } else {
                    console.error(result.error);
                }
            })
            .catch(error => console.error('Error:', error)); // Catch and log any errors
        });
    });

    function updateReactionSummary(discussionId = null, commentId = null) {
        let data = {
            method: commentId ? 'get_comment_reactions' : 'get_discussion_reactions',
            data: {
                discussion_id: discussionId,
                comment_id: commentId
            }
        };

        console.log('Fetching reaction summary with data:', data); // Log the data being sent

        fetch('ajax.php', {
            method: 'POST',
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(result => {
            console.log('Received reaction summary:', result); // Log the result received
            if (result.success) {
                const selector = commentId ? `[data-comment-id="${commentId}"] .reaction-summary` : `[data-discussion-id="${discussionId}"] .reaction-summary`;
                console.log('Selector:', selector); // Log the selector
                const summaryElement = document.querySelector(selector);
                if (summaryElement) {
                    let summaryHTML = '';
                    if (result.reactions.like > 0) summaryHTML += `<span class="reaction-item">👍 <span class="reaction-count">${result.reactions.like}</span></span> `;
                    if (result.reactions.love > 0) summaryHTML += `<span class="reaction-item">❤️ <span class="reaction-count">${result.reactions.love}</span></span> `;
                    if (result.reactions.haha > 0) summaryHTML += `<span class="reaction-item">😂 <span class="reaction-count">${result.reactions.haha}</span></span> `;
                    if (result.reactions.wow > 0) summaryHTML += `<span class="reaction-item">😮 <span class="reaction-count">${result.reactions.wow}</span></span> `;
                    if (result.reactions.sad > 0) summaryHTML += `<span class="reaction-item">😢 <span class="reaction-count">${result.reactions.sad}</span></span> `;
                    if (result.reactions.angry > 0) summaryHTML += `<span class="reaction-item">😡 <span class="reaction-count">${result.reactions.angry}</span></span> `;
                    if (result.reactions.care > 0) summaryHTML += `<span class="reaction-item">🤗 <span class="reaction-count">${result.reactions.care}</span></span> `;
                    summaryElement.innerHTML = summaryHTML.trim(); // Remove trailing space
                } else {
                    console.error('Element not found for selector:', selector); // Log if element is not found
                }
            } else {
                console.log('Error: ' + result.error);
            }
        })
        .catch(error => console.error('Error:', error)); // Catch and log any errors
    }

    const discussionReactions = document.querySelectorAll('.discussion-reactions');
    const commentReactions = document.querySelectorAll('.comment-reactions');

    // Fetch reactions for discussions
    discussionReactions.forEach(discussion => {
        const discussionId = discussion.getAttribute('data-discussion-id');
        fetchReactions(discussionId, 'discussion');
    });

    // Fetch reactions for comments
    commentReactions.forEach(comment => {
        const commentId = comment.getAttribute('data-comment-id');
        fetchReactions(commentId, 'comment');
    });

    function fetchReactions(id, type) {
        if(type === 'discussion') {discussionId=id; commentId=null;}
        else if(type === 'comment') {discussionId=null; commentId=id;}
        let data = {
            method: commentId ? 'get_comment_reactions' : 'get_discussion_reactions',
            data: {
                discussion_id: discussionId,
                comment_id: commentId
            }
        };
        fetch('ajax.php', {
            method: 'POST',
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const selector = type === 'discussion' ? `[data-discussion-id="${id}"] .reaction-summary` : `[data-comment-id="${id}"] .reaction-summary`;
                    const summaryElement = document.querySelector(selector);
                    if (summaryElement) {
                        let summaryHTML = '';
                        if (result.reactions.like > 0) summaryHTML += `<span class="reaction-item" title="Like">👍 <span class="reaction-count">${result.reactions.like}</span></span> `;
                        if (result.reactions.love > 0) summaryHTML += `<span class="reaction-item" title="Love">❤️ <span class="reaction-count">${result.reactions.love}</span></span> `;
                        if (result.reactions.haha > 0) summaryHTML += `<span class="reaction-item" title="Haha">😂 <span class="reaction-count">${result.reactions.haha}</span></span> `;
                        if (result.reactions.wow > 0) summaryHTML += `<span class="reaction-item" title="Wow">😮 <span class="reaction-count">${result.reactions.wow}</span></span> `;
                        if (result.reactions.sad > 0) summaryHTML += `<span class="reaction-item" title="Sad">😢 <span class="reaction-count">${result.reactions.sad}</span></span> `;
                        if (result.reactions.angry > 0) summaryHTML += `<span class="reaction-item" title="Angry">😡 <span class="reaction-count">${result.reactions.angry}</span></span> `;
                        if (result.reactions.care > 0) summaryHTML += `<span class="reaction-item" title="Care">🤗 <span class="reaction-count">${result.reactions.care}</span></span> `;
                        summaryElement.innerHTML = summaryHTML.trim(); // Remove trailing space
                    } else {
                        console.error('Element not found for selector:', selector); // Log if element is not found
                    }
                } else {
                    console.error('Error fetching reactions:', result.error);
                }
            })
            .catch(error => console.error('Error:', error)); // Catch and log any errors
    }

    // Handle file uploads for discussions
    document.getElementById('add-discussion-files').addEventListener('change', function () {
        const files = this.files;
        const formData = new FormData();
        formData.append('discussion_id', document.getElementById('discussion_id').value);
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        fetch('upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Handle successful upload
                console.log('Files uploaded successfully');
            } else {
                console.error(result.error);
            }
        })
        .catch(error => console.error('Error:', error));
    });

});

function editDiscussion($discussionId) {

}

function editComment($commentId) {

}

function deleteDiscussion(discussionId) {
    if (confirm('Are you sure you want to delete this story? Doing so will also delete all the comments and reactions.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '?to=communications/discussions';
    
        var deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_discussion';
        deleteInput.value = 'true';
        form.appendChild(deleteInput);
    
        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'discussionId';
        idInput.value = discussionId; // Make sure $discussionId is defined and accessible
        form.appendChild(idInput);
    
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteComment(commentId) {
    if (confirm('Are you sure you want to delete this comment? Doing so will also delete all the reactions.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '?to=communications/discussions';
    
        var deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_comment';
        deleteInput.value = 'true';
        form.appendChild(deleteInput);
    
        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'commentId';
        idInput.value = commentId; // Make sure $commentId is defined and accessible
        form.appendChild(idInput);
    
        document.body.appendChild(form);
        form.submit();
    }
}
