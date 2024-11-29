document.addEventListener('DOMContentLoaded', function () {
    const reactionButtons = document.querySelectorAll('.reaction-btn');

    flatpickr('#event_date', {
        enableTime: true,
        dateFormat: "Y-m-d H:i:S",
        time_24hr: true
    });

    tinymce.init({
        selector: 'textarea#content, textarea#discussion_edit_content',
        plugins: 'advlist autolink code lists link image charmap preview anchor pagebreak',
        menubar: false,
        toolbar_mode: 'sliding',
        toolbar1: 'bold italic | fontfamily fontsize | forecolor backcolor | alignleft aligncenter alignright alignjustify ', 
        toolbar2: 'undo redo | bulllist numlist outdent indent | link image | code removeformat | preview',
        promotion: false,
        license_key: 'gpl',
        images_upload_url: 'tinymce_image_upload.php',
        automatic_uploads: true,
        file_picker_types: 'image',
        file_picker_callback: function (cb, value, meta) {
            var input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.onchange = function () {
                var file = this.files[0];
                var formData = new FormData();
                formData.append('file', file);

                fetch('tinymce_image_upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.location) {
                        cb(result.location, { title: file.name });
                    } else {
                        console.error('Upload failed:', result.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            };
            input.click();
        },
        setup: function (editor) {
            editor.on('change', function() {
                tinymce.triggerSave();
            })
        }
    });    

    // Ensure TinyMCE content is synchronized before form submission
    document.querySelector('#newDiscussionForm').addEventListener('submit', function() {
        tinymce.triggerSave();
    });

    document.querySelector('#editDiscussionForm').addEventListener('submit', function() {
        console.log('Triggering tinyMCE update');
        tinymce.triggerSave();
    });    

    document.getElementById('showdiscussionform').addEventListener('click', function() {
        //Hide this input
        this.classList.toggle('hidden');
        
        var elements = document.getElementsByClassName('new-discussion-form');
        for (var i = 0; i < elements.length; i++) {
            //Toggle the 'hidden' class
            elements[i].classList.toggle('hidden');
        }
        console.log('Shew new-discussion-form');
    });

    document.getElementById('hidediscussionform').addEventListener('click', function() {
        //Hide this input
        document.getElementById('showdiscussionform').classList.toggle('hidden');
        
        var elements = document.getElementsByClassName('new-discussion-form');
        for (var i = 0; i < elements.length; i++) {
            //Toggle the 'hidden' class
            elements[i].classList.toggle('hidden');
        }
        console.log('Hide new-discussion-form');
    });

    document.getElementById('is_event').addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('event_date_section').classList.remove('hidden');
        } else {
            document.getElementById('event_date_section').classList.add('hidden');
        }
    });

    document.getElementById('discussion_edit_is_event').addEventListener('change', function() {
        console.log('Is Event checked or unchecked');
        if (this.checked) {
            console.log('Checked');
            document.getElementById('discussion_edit_event_date_section').classList.remove('hidden');
        } else {
            document.getElementById('discussion_edit_event_date_section').classList.add('hidden');
        }
    });

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
    
    // Handle file uploads for existing discussions
    var discussionForms = document.querySelectorAll('form[id^="discussion-form-"]');
    
    discussionForms.forEach(function(form) {
        var fileInput = form.querySelector('input[type="file"]');
        //var label = form.querySelector('label[for="' + fileInput.id + '"]');
        
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                setTimeout(function() {
                    form.submit();
                }, 100);
            }
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
            .catch(error => console.error('Error:;', error)); // Catch and log any errors
    }


});

function editDiscussion(discussionId) {
    const editDiscussionForm=document.getElementById('edit-discussion-modal')
    //Get the current values of the discussion from the ajax "get_discussion" function
    var data = {
        method: 'get_discussion',
        data: {
            discussion_id: discussionId
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
        console.log(result);
        if (result.status=="success") {
            editDiscussionForm.style.display='block';
            //Set the values of the form to the values of the discussion
            document.getElementById('discussion_edit_discussion_id').value = discussionId;
            document.getElementById('discussion_edit_title').value = result.discussion.title;
            tinymce.get('discussion_edit_content').setContent(result.discussion.content); // Set content in TinyMCE editor
            document.getElementById('discussion_edit_is_event').checked = result.discussion.is_event;
            document.getElementById('discussion_edit_is_sticky').checked = result.discussion.is_sticky;
            document.getElementById('discussion_edit_is_news').checked = result.discussion.is_news;
            document.getElementById('discussion_edit_event_date').value = result.discussion.event_date;
            document.getElementById('discussion_edit_event_location').value = result.discussion.event_location;
            if(result.discussion.is_event) {
                document.getElementById('discussion_edit_event_date_section').classList.remove('hidden');
            } else {
                document.getElementById('discussion_edit_event_date_section').classList.add('hidden');
            }
            
            flatpickr("#discussion_edit_event_date", {
                enableTime: true,
                dateFormat: "Y-m-d H:i:S",
                time_24hr: true
            });
        } else {
        }
    });
    
}

function editComment($commentId) {

}

function deleteDiscussionFile(fileId) {
    if(confirm("Are you sure you want to delete this file?")) {
        //call ajax to delete file, then remove it from the DOM
        var data = {
            method: 'delete_discussion_file',
            data: {
                fileId: fileId
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
            if (result.status=="success") {
                document.getElementById('gallery_discussion_file_id_' + fileId).remove();
                document.getElementById('discussion_file_id_' + fileId).remove();
            } else {
                console.error('Error deleting file:', result.error);
            }
        })
        .catch(error => console.error('Error:', error)); // Catch and log any errors
    }
}

function makeSticky(discussionId) {
    if(confirm("Are you sure you want to stick this discussion to the top of the list?")) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '?to=communications/discussions';
    
        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'discussion_id';
        idInput.value = discussionId; // Make sure $discussionId is defined and accessible
        form.appendChild(idInput);

        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'make_sticky';
        form.appendChild(actionInput);
    
        document.body.appendChild(form);
        form.submit();
    }
}

function unStick(discussionId) {
    if(confirm("Are you sure you want to unstick this discussion?")) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '?to=communications/discussions';
        
        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'discussion_id';
        idInput.value = discussionId; // Make sure $discussionId is defined and accessible
        form.appendChild(idInput);
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'delete_sticky';
        form.appendChild(actionInput);
    
        document.body.appendChild(form);
        form.submit();
    }
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

function showGalleryModal(discussionId) {
    console.log('Showing gallery modal for discussion:', discussionId);
    const galleryModal = document.getElementById('gallery-modal');
    const galleryModalContent = document.getElementById('gallery-modal-content');
    galleryModalContent.innerHTML = ''; // Clear the content
    const files = document.querySelectorAll(`#discussion_id_${discussionId} .file-gallery-item img`);
    files.forEach((file) => {
        //Get the parent div and add it to the modal - note that the parent div is two parents up
        const div = file.parentElement.cloneNode(true);
        //show the id of the div in the console
        //console.log(div.id);
        //Change the id of the new div by adding "gallery_" to the beginning of the id
        div.id = 'gallery_' + div.id;
        //replace the h-24 and w-20
        div.classList.remove('h-24');
        div.classList.remove('w-20');
        div.classList.add('h-96');
        div.classList.add('w-80');
        
        //Remove the hidden class from the deleteImage buttons
        const deleteImageButtons = div.querySelectorAll('.delete-image-button');
        deleteImageButtons.forEach((button) => {
            button.classList.remove('hidden');
        });
        //Replace the h-16 and w-16 in the img tag with h-60 and w-60
        const img = div.querySelector('img');
        img.classList.remove('h-16');
        img.classList.remove('w-16');
        img.classList.add('h-72');
        img.classList.add('w-72');
        //extract the "data-file-path" value from each image
        const filePath = img.getAttribute('data-file-path');
        //Wrap each img in an anchor tag that links to the filePath
        const anchor = document.createElement('a');
        console.log(anchor);
        anchor.href = filePath;
        anchor.target = '_blank';
        anchor.appendChild(img);
        div.appendChild(anchor);
        

        //Replace the text-xxs in the span tag with text-sm
        const span = div.querySelector('span');
        span.classList.remove('h-8');
        span.classList.remove('text-xxs');
        span.classList.add('text-sm');
        span.classList.remove('p-1');
        span.classList.add('p-2');
        span.id='gallery_file_description_'+span.id.split('_')[3]; 

        galleryModalContent.appendChild(div);
    });
    galleryModal.style.display='block';
}

function editDiscussionFileDescription(fileId) {

    const galleryModal = document.getElementById('gallery-modal');
    if (galleryModal.style.display === 'block') {
        //Set the 'customModal' div's z-index to 1000000 to ensure it is on top of the gallery modal
        originalCustomPromptZindex = document.getElementById('customPrompt').style.zIndex;
        document.getElementById('customPrompt').style.zIndex = 1000000;
    }
    console.log('Editing file description for file:', fileId);
    var currentDescription=document.getElementById('discussion_file_description_'+fileId).textContent;

    //Use the showCustomPrompt function to allow the user to edit the story
    showCustomPrompt('Edit Description', 'Edit the description here:', ['fileDescription_textarea'], [currentDescription], async function(inputValues) {
        if (inputValues !== null) {
            var newFileDescription = inputValues[0];
            getAjax('update_discussion_file_description', {fileId: fileId, fileDescription: newFileDescription})
                .then(response => {
                    if(response.success) {
                        document.getElementById('discussion_file_description_'+fileId).textContent = newFileDescription;
                        //If there is a "gallery_discussion_file_description_'+fileId' element, update it as well
                        if (document.getElementById('gallery_file_description_'+fileId)) {
                            document.getElementById('gallery_file_description_'+fileId).textContent = newFileDescription;
                        }
                    } else {
                        alert('Error: ' + response.message);
                    }
                })
                .catch(error => {
                    alert('An error occurred while updating the item description: ' + error.message);
                });
        }
        if (galleryModal.style.display === 'block') {
            //Reset the 'customModal' div's z-index to its original value
            document.getElementById('customPrompt').style.zIndex = originalCustomPromptZindex;
        }
    });
    return;    
}
