document.addEventListener("DOMContentLoaded", function() {
    //console.log('Init tabs');
    initialiseTabs('.tab', '.tab-content', 'activeIndividualTabId');

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
    // If there are any discussions on the page, fetch their reactions
    if(discussionReactions.length > 0) {
        console.log('There are discussionReactions on the page!');
        console.log(discussionReactions);
        discussionReactions.forEach(discussion => {
            const discussionId = discussion.getAttribute('data-discussion-id');
            fetchReactions(discussionId, 'discussion');
        });
    }

    // Fetch reactions for comments
    commentReactions.forEach(comment => {
        const commentId = comment.getAttribute('data-comment-id');
        fetchReactions(commentId, 'comment');
    });

    function fetchReactions(id, type) {
        console.log('Starting the fetchReactions function!');
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
    // ------------------- Handling the "Edit" button and modal -------------------

    //Reset the form to clear the previous data
    document.getElementById('editForm').reset();

    // Get the modal and form elements for the "Edit" form
    const editModal = document.getElementById('editModal');
    const closeModalBtn = document.querySelector('.edit-close-btn');  // You may need to make sure this selector applies correctly
    const editIndividualIdInput = document.getElementById('edit-individual-id');
    const editFirstNameInput = document.getElementById('edit-first-names');
    const editLastNameInput = document.getElementById('edit-last-name');
    const editAkaInput = document.getElementById('edit-aka-names');   
    const editBirthPrefixInput = document.getElementById('edit-birth-prefix');
    const editBirthYearInput = document.getElementById('edit-birth-year');
    const editBirthMonthInput = document.getElementById('edit-birth-month');
    const editBirthDateInput = document.getElementById('edit-birth-date');
    const editDeathPrefixInput = document.getElementById('edit-death-prefix');
    const editDeathYearInput = document.getElementById('edit-death-year');
    const editDeathMonthInput = document.getElementById('edit-death-month');
    const editDeathDateInput = document.getElementById('edit-death-date');
    const editGenderInput = document.getElementById('edit-gender');
    const editIsDeceasedInput = document.getElementById('edit-is_deceased');

    

    // Function to open the "Edit" modal and populate it with the individual's data
    async function openEditModal(individualId) {
        try {
            // Fetch individual data
            const individualData = await getIndividualDataById(individualId);  // Wait for the data
            console.log(individualData);
            // Populate the form with the individual's data
            editIndividualIdInput.value = individualId;
            editFirstNameInput.value = individualData.first_names;
            editLastNameInput.value = individualData.last_name;
            editAkaInput.value = individualData.aka_names;
            editBirthPrefixInput.value = individualData.birth_prefix;
            editBirthYearInput.value = individualData.birth_year;
            editBirthMonthInput.value = individualData.birth_month;
            editBirthDateInput.value = individualData.birth_date;
            editDeathPrefixInput.value = individualData.death_prefix;
            editDeathYearInput.value = individualData.death_year;
            editDeathMonthInput.value = individualData.death_month;
            editDeathDateInput.value = individualData.death_date;
            editGenderInput.value = individualData.gender;
            editIsDeceasedInput.checked = individualData.is_deceased;

            document.getElementById('individual_name_display').innerHTML = individualData.first_names + ' ' + individualData.last_name;

            // Display the "Edit" modal
            editModal.style.display = 'block';
        } catch (error) {
            console.error('Error opening edit modal:', error);
        }
    }

    // Handle the "AKA" toggle button in the Edit form
    var toggleEditAkaButton = document.getElementById('edit-toggle-aka');
    var akaEditDiv = document.getElementById('edit-aka');
    console.log(toggleEditAkaButton);

    toggleEditAkaButton.addEventListener('click', function() {
        if (akaEditDiv.style.display === 'none' || akaEditDiv.style.display === '') {
            akaEditDiv.style.display = 'block';
        } else {
            akaEditDiv.style.display = 'none';
        }
    });     

    // Placeholder for fetching individual data (replace with actual data fetching logic)
    async function getIndividualDataById(id) {
        try {
            var output = await getAjax('getindividual', { id: id });
            //console.log(output.individual.first_names);
            return output.individual;
        } catch (error) {
            console.error('Error fetching individual data:', error);
        }
    }

    async function getSpouses(id) {
        try {
            var output = await getAjax('getspouses', { id: id });
            console.log(output);
            return output.parents;
        } catch (error) {
            console.error('Error fetching individual data:', error);
        }
    }

    // Loop through each node and add the event listener to the "Edit" button
    document.querySelectorAll('.edit-btn').forEach(function(editButton) {
        editButton.addEventListener('click', function(event) {
            event.stopPropagation();  // Prevent triggering any other click handlers
            const individualId = editButton.dataset.individualId;
            openEditModal(individualId);  // Open the "Edit" modal
        });
    });

    //Loops through each node and adds the event listener to the "Delete Relationship" buttons
    document.querySelectorAll('.delete-relationship-btn').forEach(function(deleteButton) {
        deleteButton.addEventListener('click', function(event) {
            event.stopPropagation();  // Prevent triggering any other click handlers
            const relationshipId = deleteButton.dataset.relationshipid;
            const cardId = deleteButton.dataset.individualcardid;
            const relationshipType = deleteButton.dataset.relationshiptype;
            deleteRelationship(relationshipId, cardId, relationshipType);  // Delete the relationship
        });
    });

    // Close modal logic for "Edit" modal
    closeModalBtn.addEventListener('click', function() {
        editModal.style.display = 'none';
    });

    var toggleAkaButton = document.getElementById('toggle-aka');
    var akaDiv = document.getElementById('aka');

    toggleAkaButton.addEventListener('click', function() {
        if (akaDiv.style.display === 'none' || akaDiv.style.display === '') {
            akaDiv.style.display = 'block';
        } else {
            akaDiv.style.display = 'none';
        }
    });        

    //Add a listener to the #new-individual-type select so that when it changes we can show/hide the additional fields div
    document.getElementById('new-individual-type').addEventListener('change', function() {
        var selectedType = this.value;
        if(selectedType === 'existing') {
            document.getElementById('existing-individuals').style.display = '';
            document.getElementById('relationships').style.display = '';
            document.getElementById('additional-fields').style.display = 'none';
            document.getElementById('submit_add_relationship_btn').style.display='';
    
            document.getElementById('relationship-form-action').value = 'link_relationship';
            document.getElementById('first_names').removeAttribute('required');
            document.getElementById('last_name').removeAttribute('required');
            document.getElementById('additional-fields').style.display = 'none';   
        } else if(selectedType === 'new') {
            document.getElementById('existing-individuals').style.display = 'none';
            document.getElementById('relationships').style.display = '';
            document.getElementById('additional-fields').style.display = '';
            document.getElementById('submit_add_relationship_btn').style.display='';
    
            document.getElementById('relationship-form-action').value = 'add_relationship';
            document.getElementById('first_names').setAttribute('required', '');
            document.getElementById('last_name').setAttribute('required', '');
            document.getElementById('additional-fields').style.display = ''; 
        } else {
            document.getElementById('existing-individuals').style.display = 'none';
            document.getElementById('additional-fields').style.display = 'none';    
            document.getElementById('submit_add_relationship_btn').style.display='none';
        }
    });      

    //Add a listener to the "relationship" select so that when it changes we can show/hide the "second-parent" select
    document.getElementById('relationship').addEventListener('change', function() {
        var selectedRelationship = this.value;
        if(selectedRelationship === 'child') {
            var thisId=document.getElementById('related-individual').value;
            getSpouses(thisId).then(spouses => {
                //console.log('Found spouses', spouses);
                var select = document.getElementById('second-parent');
                select.innerHTML = '';
                var option = document.createElement('option');
                option.value = '';
                option.text = 'None or not known';
                select.add(option);
                spouses.forEach(spouse => {
                    //console.log('Processing sppouse', spouse);
                    var option = document.createElement('option');
                    option.value = spouse.parent_id;
                    option.text = spouse.spouse_first_names + ' ' + spouse.spouse_last_name;
                    //console.log('Option built:', option);
                    select.add(option);
                });
            });            
            document.getElementById('choose-second-parent').style.display = 'block';
        } else {
            document.getElementById('choose-second-parent').style.display = 'none';
        }
    }
    );    


});

function deleteDiscussion($discussionId) {
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
        idInput.value = $discussionId; // Make sure $discussionId is defined and accessible
        form.appendChild(idInput);
    
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteComment($commentId) {
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
        idInput.value = $commentId; // Make sure $commentId is defined and accessible
        form.appendChild(idInput);
    
        document.body.appendChild(form);
        form.submit();
    }
}

// Trigger the file upload dialog
function triggerKeyPhotoUpload() {
    document.getElementById('keyPhotoUpload').click();
}


function triggerPhotoUpload(individualId) {
    document.getElementById('photoUpload').click();
}

function triggerEditItemDescription(id) {
    console.log('Triggering edit item description for: ' + id);
    var currentDescription=document.getElementById(id).textContent;

    var item_id=id.split('_')[1];
    //Check and see if there's a div called "hiddenStory_"+id
    var hiddenStoryDiv=document.getElementById('hiddenStory_'+item_id);
    console.log('Checking for hidden story div');
    if(hiddenStoryDiv) {
        console.log('Found hidden story div');
        var currentDescription = hiddenStoryDiv.textContent;

        //Use the showCustomPrompt function to allow the user to edit the story
        showCustomPrompt('Edit Story', 'Edit the story here:', ['textarea_Story'], [currentDescription], async function(inputValues) {
            if (inputValues !== null) {
                var newStory = inputValues[0];
                getAjax('update_item', {itemId: item_id, itemDescription: newStory})
                    .then(response => {
                        if(response.status === 'success') {
                            document.getElementById(id).textContent=newStory;
                        } else {
                            alert('Error: ' + response.message);
                        }
                    })
                    .catch(error => {
                        alert('An error occurred while updating the item description: ' + error.message);
                    });
            }
        });
        return;
    }

    var newItemDescription=prompt("Enter your description here:", currentDescription);

    if(newItemDescription===null) {
        //User pressed cancel - abort
        return;
    }
    //the actual id we want to use is after the underscore in the text of the id passed to the function
    
    getAjax('update_item', {itemId: item_id, itemDescription: newItemDescription})
        .then(response => {
            if(response.status === 'success') {
                document.getElementById(id).textContent=newItemDescription;
            } else {
                alert('Error: ' + response.message);
            }
        })
        .catch(error => {
            alert('An error occurred while updating the item description: ' + error.message);
        });
}

function triggerEditFileDescription(id) {
    console.log('Triggering edit file description for: ' + id);
    var currentDescription=document.getElementById(id).textContent;
    var newFileDescription=prompt("Enter your description here:", currentDescription);

    if(newFileDescription===null) {
        //User pressed cancel - abort
        return;
    }
    //the actual id we want to use is after the underscore in the text of the id passed to the function
    var file_id=id.split('_')[1];
    getAjax('update_file', {fileId: file_id, fileDescription: newFileDescription})
        .then(response => {
            if(response.status === 'success') {
                document.getElementById(id).textContent=newFileDescription;
            } else {
                alert('Error: ' + response.message);
            }
        })
        .catch(error => {
            alert('An error occurred while updating the file description: ' + error.message);
        });


}

async function openStoryModal(individualId) {
    const storyModal = document.getElementById('storyModal');
    const storyIndividualIdInput = document.getElementById('story-individual_id');
   
    try {
        // Fetch individual data
        const individualData = await getIndividualDataById(individualId);  // Wait for the data
        console.log(individualData);
        // Populate the form with the individual's data
        storyIndividualIdInput.value = individualId;

        tinymce.init({
            selector: 'textarea#content',
            plugins: 'advlist autolink lists link image charmap preview anchor pagebreak',
            menubar: 'edit insert table format tools help',
            toolbar_mode: 'floating',
            promotion: false,
            license_key: 'gpl',
            setup: function (editor) {
                editor.on('change', function() {
                    tinymce.triggerSave();
                })
            }
        });   

        storyModal.style.display = 'block';
    } catch (error) {
        console.error('Error opening story modal:', error);
    }
}

async function uploadPhoto(individualId) {
    var fileInput = document.getElementById('photoUpload');
    var file = fileInput.files[0]; // Get the selected file
    console.log('Starting the upload photo process');
    if (file) {
        // Prepare the data for uploading
        var fileName = file.name;
        var formData = new FormData();
        formData.append('file', file);  // Append the selected file
        formData.append('method', 'add_file_item');  // Method for your ajax.php
        
        var events = []; //This isn't an event, just a file upload
        var event_group_name = null;   //This isn't an event group either, just a file upload
        var fileDescriptionDefault = "Photo of " + document.getElementById('individual_brief_name').value;

        // Show the custom prompt
        showCustomPrompt(
            'Add Photo Description',
            'Please enter a description for this photo:<br /><span class="text-sm">' + fileName+ '</span>',
            ['Description'],
            [fileDescriptionDefault],
            function(inputValues) {
                if (inputValues !== null) {
                    var fileDescription = inputValues[0];

                    formData.append('data', JSON.stringify({
                        individual_id: individualId,
                        events: events,
                        event_group_name: event_group_name,
                        file_description: fileDescription,  // Description for the file
                    }));

                    // Perform the AJAX call using getAjax
                    getAjax('add_file_item', formData)
                        .then(response => {
                            // Convert response from JSON to object
                            console.log(response);
                            console.log(response.status);
                            if (response.status === 'success') {
                                // Reload the page
                                //location.reload();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        })
                        .catch(error => {
                            alert('An error occurred while uploading the image: ' + error.message);
                        });
                }
            }
        );
    }    
}

// Handle the keyImage file selection and upload
async function uploadKeyImage(individualId) {
    var fileInput = document.getElementById('keyPhotoUpload');
    var file = fileInput.files[0];  // Get the selected file

    let fileLinkId=null; //This will be used to store the file_link_id of the original file that was the key image for the individual
    //A key image is a special type of event/item
    // There can only be one of these per individual
    // so, if there is already one, we need to delete that "item" from the "items" table, along
    // with its item_link. We should also delete the link between the original photo & the item_id

    try {
        const response = await getAjax('get_item', {individual_id: individualId, event_type: 'Key Image'});
        console.log(response);
        if (response.status === 'success') {
            if (response.items.length > 0) {
                // Gather the item_id and item_link_id
                var itemId = response.items[0].item_id;
                var itemLinkId = response.items[0].item_link_id;
                var fileId = response.items[0].file_id;
                fileLinkId = response.items[0].file_link_id;

                console.log('Theres already a key image for this individual - its item_id is: ' + itemId + ' and its fileId is: ' + fileId +' and its file_link_id is: ' + fileLinkId);
                // All we need to do is: delete the item_id from the file_links record. That frees the original file up to be just a photo for the individual
                // After we've uploaded the new photo, we'll simply create a new file_links record for the new file_id and add this itemId to it's file_links.item_id                    
                
                const updateResponse = await getAjax('update_file_links', {file_link_id: fileLinkId, updates: {item_id: 'null'}});
                    if (response.status === 'success') {
                        console.log('Original file has been freed up to be a photo for the individual');
                    } else {
                        console.log('Error: ' + response.message);
                        return; //Exit the function if there was an error
                    }
                }
        }
    } catch (error) {
            alert('An error occurred while checking for an existing key image: ' + error.message);
            return; //Exit the function if there was an error
    }

    

    if (file) {
        // Prepare the data for uploading
        var formData = new FormData();
        formData.append('file', file);  // Append the selected file
        formData.append('method', 'add_file_item');  // Method for your ajax.php
        
        if(fileLinkId){
            //make the events array empty
            var events = [];
        } else {
            var events = [
                {event_type: 'Key Image',  event_detail: 'Key image for individual'},
            ];
        }
        var event_group_name=null;
        formData.append('data', JSON.stringify({
            individual_id: individualId,
            events: events,
            event_group_name: event_group_name,
            file_description: 'Image for individual'  // Description for the file
        }));

        // Perform the AJAX call using getAjax
        getAjax('add_file_item', formData)
            .then(response => {
                //convert response from json to object
                console.log(response);
                console.log(response.status);
                if(response.filepath) {
                    console.log('Add picture to page');
                    document.getElementById('keyImage').src = response.filepath;
                }
            })
            .catch(error => {
                alert('An error occurred while uploading the image: ' + error.message);
            });
    }
}

function doAction(action, individualId, actionId, event) {
    if(event) {
        console.log('Event got passed');
        var eventelement = event.target.closest('div');      
        var eventbutton = event.target.closest('button');  
    }
    console.log('Doing action:', action, 'for individual ID:', individualId, ' and action ID:', actionId, ' for event:', event);

    switch(action) {
        case 'delete_item':
            if (confirm('Are you sure you want to delete this item?')) {
                if(eventbutton) {
                    var groupId = eventbutton.getAttribute('data-group-id');
                    var groupType = eventbutton.getAttribute('data-group-item-type');
                    var groupName = eventbutton.getAttribute('data-group-event-name');
                } else {
                    var groupId = null;
                    var groupType = null;
                    var groupName = null
                }
                getAjax('delete_item', {individualId: individualId, itemId: actionId})
                    .then(response => {
                        if (response.status === 'success') {
                            // Reload the page
                            //alert('Item has been deleted');
                            document.getElementById('item_id_'+actionId).remove();
                            if(response.itemIdentifier) {
                                document.getElementById('item_group_id_'+response.itemIdentifier).remove();
                            }
                            if(response.groupItemType) {
                                //Create a new "add" button for the group item
                                document.getElementById('item_buttons_group_'+response.groupItemIdentifier).insertAdjacentHTML('beforeend', '<div data-group-event-name="'+groupName+'" data-group-item-type="'+groupType+'" data-group-id="'+groupId+'" onclick="doAction(\'add_sub_item\', \''+individualId+'\', \''+response.groupItemType+'\', event);" class="cursor-pointer text-xxs border rounded bg-cream text-brown p-0.5 m-1 relative"><button class="absolute text-burnt-orange nv-text-opacity-50 text-bold rounded-full py-0 px-1 m-0 -right-2 -top-2 text-xxxs" title="Add '+response.groupItemType+'" ><i class="fas fa-plus"></i></button>'+response.groupItemType+'</div>');
                            }
                            //location.reload();
                        } else {
                            alert('Error item has not been deleted: ' + response.message);
                        }
                    })
                    .catch(error => {
                        alert('An error occurred while deleting the item: ' + error.message);
                    });
            }
            break;
        case 'delete_item_group':
            if (confirm('Are you sure you want to delete this entire group of items?')) {
                getAjax('delete_item', {individualId: individualId, itemIdentifier: actionId})
                    .then(response => {
                        if (response.status === 'success') {
                            // Reload the page
                            //alert('Item group has been deleted');
                            console.log('Removing group called item_group_'+actionId);
                            document.getElementById('item_group_id_'+actionId).remove();
                            //location.reload();
                        } else {
                            alert('Error item group has not been deleted: ' + response.message);
                        }
                    })
                    .catch(error => {
                        console.log('An error occurred while deleting the item group: ' + error.message);
                        //alert('An error occurred while deleting the item group: ' + error.message);
                    });
            }
            break;
        case 'delete_photo':
            if(confirm('Are you sure you want to delete this photo?')) {
                getAjax('delete_file', {individualId: individualId, fileId: actionId})
                    .then(response => {
                        if (response.status === 'success') {
                            // Reload the page
                            alert('Photo has been deleted');
                            //Remove div containing photo
                            document.getElementById('file_id_'+actionId).remove();
                            //location.reload();
                        } else {
                            alert('Error - photo has not been deleted: ' + response.message);
                        }
                    }
                )}
            break;
        case 'add_sub_item':
            console.log('Adding a sub item');
            var groupId = eventelement.getAttribute('data-group-id');
            var groupType = eventelement.getAttribute('data-group-item-type');
            var groupName = eventelement.getAttribute('data-group-event-name');
        
            console.log('Group Id: ',groupId);
            // Open the modal to add a new item
            // openModal('add_item', individualId, actionId);
            
            // Now to decide what needs to be in the custom prop.
            // If the groupType is 'file', then we need to add a file field
            // If the groupType is 'date', then we need to add a date field
            // If the groupType is 'story' then we need to add a textarea
            // If the groupType is 'individual' then we need to add the special dropdown for selecting an individual
            // If the group is anything else, we need a text input

            switch(groupType) {
                case 'file':
                    console.log('Initiating settings for adding a file and description');
                    inputs = ['file_'+actionId, 'Description'];
                    values = ['', actionId+' file'];
                    break;
                case 'date':
                    inputs = ['date_'+actionId];
                    values = [''];
                    break;
                case 'textarea':
                    inputs = ['textarea_'+actionId];
                    values = [''];
                    break;
                case 'individual':
                    inputs = ['individual_'+actionId];
                    values = [''];
                    break;
                case 'url':
                    inputs = ['url_'+actionId];
                    values = [''];
                    break;
                default:
                    inputs = ['text_'+actionId];
                    values = [''];
                    break;
            }


            showCustomPrompt('Add ' + actionId, 'Add ' + actionId + ' to this event', inputs, values, async function(inputValues) {
                if (inputValues !== null) {
                    
                    var formData = new FormData();
                    var event_group_name = groupName;

                    console.log('Input Values returned are:');
                    console.log(inputValues);
                    //return;
                    if(groupType === 'file') {
                        var file = inputValues[0];
                        var fileDescription = inputValues[1];
                        //Append the file & other data to the formData object
                        if (file instanceof File) {
                            formData.append('file', file);
                        } else {
                            console.error('The selected file is not a valid File object.');
                        }
                        formData.append('method', 'add_file_item');
                        formData.append('data', JSON.stringify({
                            individual_id: individualId,
                            events: [{event_type: actionId, event_detail: fileDescription, item_identifier: groupId}],
                            event_group_name: event_group_name,
                            file_description: fileDescription
                        }));
                    } else {
                        var itemDetails=inputValues[0];
                        formData.append('method', 'add_file_item');
                        formData.append('data', JSON.stringify({
                            individual_id: individualId,
                            events: [{event_type: actionId, event_detail: itemDetails, item_identifier: groupId}],
                            event_group_name: event_group_name
                        }));

                    }
                    


                    //Debugging: Log the formData object
                    for (var pair of formData.entries()) {
                        console.log(pair[0]+ ', ' + pair[1]); 
                    }
        
                    try {
                        console.log(formData);
                        const response = await getAjax('add_file_item', formData);
                        if (response.status === 'success') {
                            // Reload the page
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    } catch (error) {
                        alert('An error occurred while adding the item: ' + error.message);
                    }
                }
            });
        
        break;     
        default:
            break;
    }
}

function openEventModal(action, individualId, eventId) {
    console.log('Opened modal with action:', action, 'for individual ID:', individualId, ' and event ID:', eventId);
    
    // Get the modal and form elements for the "Add" form
    var modal = document.getElementById('eventModal');
    var closeButton = document.querySelector('.close-event-btn');
    var formActionInput = document.getElementById('event-action');
    var eventIndividualIdInput = document.getElementById('event-individual_id');
    var eventEventType = '';
    //check if there is an item with the id 'event-type', and if so, change eventType to the value of that item
    if(document.getElementById('event-type')) {
        eventEventType = document.getElementById('event-type').value;
    }


    // Close the "Add" modal when the user clicks the close button
    closeButton.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    // Set the form data based on the action
    formActionInput.value = action;  // Set the action (add_parent, add_spouse, etc.)
    eventIndividualIdInput.value = individualId;  // Set the individual ID
    eventEventType.value = eventEventType;  // Set the event ID

    // Display the "Add" modal
    modal.style.display = 'block';
}

function updateEventContents(eventType) {
    console.log('Updating event contents for event type:', eventType);

    //iterate through the items with a class .event-field and, if they also have the class "eventType" show them, otherwise hide them
    var eventFields = document.querySelectorAll('.event-field');
    eventFields.forEach(function(field) {
        //Show all the classes for the field
        console.log(field.classList);

        if(field.classList.contains(eventType)) {
            field.style.display = 'block';
        } else {
            field.style.display = 'none';
        }
    });
}

function openModal(action, individualId, individualGender) {
    console.log('Opened modal with action:', action, 'for individual ID:', individualId, ' and gender', individualGender);


    document.getElementById('add-relationship-form').action = '?to=family/individual&individual_id=' + individualId;
    // Get the modal and form elements for the "Add" form
    var modal = document.getElementById('popupForm');
    var closeButton = document.querySelector('.close-btn');
    var formActionInput = document.getElementById('relationship-form-action');
    var formActionGender = document.getElementById('gender');
    var formActionRelationship = document.getElementById('relationship');
    var relatedIndividualInput = document.getElementById('related-individual');  
    var primaryIndividualName = document.getElementById('individual_brief_name').value;
    // extract the first word from primaryIndividualName as their FirstName
    var primaryIndividualName = primaryIndividualName.split(' ')[0];

    console.log(primaryIndividualName);  


    // Close the "Add" modal when the user clicks the close button
    closeButton.addEventListener('click', function() {
        modal.style.display = 'none';
    });    
    // Close the "Add" modal when the user clicks outside of the modal content
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });    


    //console.log(individualGender);
    //Clear the form to default values
    document.getElementById('first_names').value = '';
    document.getElementById('last_name').value = '';
    document.getElementById('aka_names').value = '';
    document.getElementById('birth_prefix').value = '';
    document.getElementById('birth_year').value = '';
    document.getElementById('birth_month').value = '';
    document.getElementById('birth_date').value = '';
    document.getElementById('death_prefix').value = '';
    document.getElementById('death_year').value = '';
    document.getElementById('death_month').value = '';
    document.getElementById('death_date').value = '';
    document.getElementById('gender').value = '';
    // Set the form data based on the action
    formActionInput.value = 'relationship-'+action;  // Set the action (add_parent, add_spouse, etc.)
    relatedIndividualInput.value = individualId;  // Set the related individual ID
    //Clear the other parent select of options
    var select = document.getElementById('second-parent');
    select.innerHTML = '';

    //Insert the primaryIndividualName variable into any span with the class "adding_relationship_to_name"
    var addingRelationshipToName = document.getElementsByClassName('adding_relationship_to_name');
    for (var i = 0; i < addingRelationshipToName.length; i++) {
        addingRelationshipToName[i].innerHTML = primaryIndividualName;
    }
    //Do the same for the primaryIndividualFirstName on any span with the class "adding_relationship_to_firstname"
    var addingRelationshipToFirstName = document.getElementsByClassName('adding_relationship_to_firstname');
    for (var i = 0; i < addingRelationshipToFirstName.length; i++) {
        addingRelationshipToFirstName[i].innerHTML = primaryIndividualName;
    }


    //document.getElementById('modal-title').innerHTML = 'Connect ' + primaryIndividualName + ' to another person';
    //document.getElementById('existing-individuals').style.display = 'block';
    document.getElementById('relationships').style.display = 'block';
    document.getElementById('choose-second-parent').style.display = 'none';
    console.log(action);
    switch(action) {
        case 'add_individual':
            formActionGender.value='';
            formActionRelationship.value='';
            document.getElementById('modal-title').innerHTML = 'Add New Individual';
            document.getElementById('existing-individuals').style.display = 'none';
            document.getElementById('relationships').style.display = 'none';
            break;
        case 'add_parent':
            formActionRelationship.value='parent';
            break;
        case 'add_father':
            formActionRelationship.value='parent';
            formActionGender.value='male';
            break;
        case 'add_mother':
            formActionGender.value='female';
            formActionRelationship.value='parent';
            break;
        case 'add_child':
            formActionRelationship.value='child';
            console.log('Getting spouses');
            getSpouses(individualId).then(spouses => {
                console.log(spouses);
                var select = document.getElementById('second-parent');
                select.innerHTML = '';
                var option = document.createElement('option');
                option.value = '';
                option.text = 'None or not known';
                select.add(option);
                spouses.forEach(spouse => {
                    var option = document.createElement('option');
                    option.value = spouse.parent_id;
                    option.text = spouse.spouse_first_names + ' ' + spouse.spouse_last_name;
                    select.add(option);
                });
            });
            document.getElementById('choose-second-parent').style.display = 'block';  
            break;
        case 'add_son':
            formActionRelationship.value='child';
            formActionGender.value='male';
            console.log('Getting spouses');
            //retrieve spouses using getSpouses(individualId) and then populate the "second-parent" select
            getSpouses(individualId).then(spouses => {
                //console.log(spouses);
                var select = document.getElementById('second-parent');
                select.innerHTML = '';
                var option = document.createElement('option');
                option.value = '';
                option.text = 'None or not known';
                select.add(option);
                spouses.forEach(spouse => {
                    var option = document.createElement('option');
                    option.value = spouse.parent_id;
                    option.text = spouse.spouse_first_names + ' ' + spouse.spouse_last_name;
                    select.add(option);
                });
            });
            document.getElementById('choose-second-parent').style.display = 'block';                
            break;
        case 'add_daughter':
            formActionGender.value='female';
            formActionRelationship.value='child';
            //retrieve spouses using getSpouses(individualId) and then populate the "second-parent" select
            getSpouses(individualId).then(spouses => {
                //console.log(spouses);
                var select = document.getElementById('second-parent');
                select.innerHTML = '';
                var option = document.createElement('option');
                option.value = '';
                option.text = 'None or not known';
                select.add(option);
                spouses.forEach(spouse => {
                    var option = document.createElement('option');
                    option.value = spouse.parent_id;
                    option.text = spouse.parent_first_names + ' ' + spouse.parent_last_name;
                    select.add(option);
                });
            });
            document.getElementById('choose-second-parent').style.display = 'block';                
            break;
        case 'add_spouse':
            if(individualGender=='female') formActionGender.value='male';
            if(individualGender=='male') formActionGender.value='female';
            formActionRelationship.value='spouse';
            break;
        default:
            formActionGender.value='other';
            break;                
    }
    modal.style.display = 'block';  // Show the modal
}

function deleteDiscussion(discussionId) {
    if (confirm('Are you sure you want to delete this story? Doing so will also delete all the comments')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.search;
    
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
    if (confirm('Are you sure you want to delete this comment?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '?to=family/individual&individual_id=' + individualId;

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

function deleteRelationship(relationshipId, individualCardId, relationshipType) {
    if (confirm('Are you sure you want to delete this relationship? This will not change any of the individual records, just the connection between the two.')) {
        // Perform the deletion via AJAX or redirect to a URL with the necessary parameters

        getAjax('delete_relationship', {relationshipId: relationshipId, relationshipType: relationshipType})
            .then(response => {
                if (response.status === 'success') {
                    // Handle successful deletion (e.g., remove the relationship from the DOM)
                    document.getElementById(individualCardId).remove();
                } else {
                    // Handle error
                    alert('Failed to delete relationship. '+response.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the relationship.');
            });
    };
    
}


function editDiscussion(discussion_id) {
    console.log('Editing discussion with ID:', discussion_id);
    //Get the discussion title and discussion text from ajax
    getAjax('get_discussion', {discussion_id: discussion_id})
        .then(response => {
            console.log(response.discussion);
            if (response.success) {
                var discussion = response.discussion;
                var title = discussion.title;
                var text = discussion.content;
                //Use the showCustomPrompt function to allow the user to edit the discussion
                showCustomPrompt('Edit Discussion', 'Edit the discussion here:', ['text_Title', 'textarea_Content'], [title, text], async function(inputValues) {
                    if (inputValues !== null) {
                        var newTitle = inputValues[0];
                        var newText = inputValues[1];
                        getAjax('update_discussion', {discussion_id: discussion_id, title: newTitle, content: newText})
                            .then(response => {
                                if(response.status === 'success') {
                                    document.getElementById('discussion_title_'+discussion_id).textContent=newTitle;
                                    //format newText so it replaces newlines (/n or /r or combination) with <br />
                                    newText = newText.replace(/(?:\r\n|\r|\n)/g, '<br />');
                                    document.getElementById('discussion_text_'+discussion_id).innerHTML=newText;
                                } else {
                                    alert('Error: ' + response.message);
                                }
                            })
                            .catch(error => {
                                alert('An error occurred while updating the discussion: ' + error.message);
                            });
                    }
                });
            } else {
                console.error('Error fetching discussion:', response.error);
            }
        }
    )
}

function editComment(comment_id) {
    console.log('Editing comment with ID:', comment_id);

}
