document.addEventListener("DOMContentLoaded", function() {
    // ------------------- Handling the "Edit" button and modal -------------------

    //Reset the form to clear the previous data
    document.getElementById('editForm').reset();

    // Get the modal and form elements for the "Edit" form
    const editModal = document.getElementById('editModal');
    const closeModalBtn = document.querySelector('.edit-close-btn');  // You may need to make sure this selector applies correctly
    const editForm = document.getElementById('editForm');
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

    // Placeholder for fetching individual data (replace with actual data fetching logic)
    async function getIndividualDataById(id) {
        try {
            var output = await getAjax('getindividual', { id: id });
            console.log(output.individual.first_names);
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

    //When someone selects id='choice-existing-individual', show the id='existing-individuals' div
    document.getElementById('choice-existing-individual').addEventListener('click', function() {
        document.getElementById('existing-individuals').style.display = 'block';
        document.getElementById('relationships').style.display = 'block';
        document.getElementById('additional-fields').style.display = 'none';
        document.getElementById('choice-new-individual').checked = false;
    });

    //When someone selects id='choice-new-individual', hide the id='existing-individuals' div and show the id='additional-fields' div
    document.getElementById('choice-new-individual').addEventListener('click', function() {
        document.getElementById('existing-individuals').style.display = 'none';
        document.getElementById('relationships').style.display = 'block';
        document.getElementById('additional-fields').style.display = 'block';
        document.getElementById('choice-existing-individual').checked = false;
    });    

    document.getElementById('lookup').addEventListener('input', function() {
        var input = this.value.toLowerCase();
        var select = document.getElementById('connect_to');
        var options = select.options;
        var hasMatch = false;

        for (var i = 0; i < options.length; i++) {
            var option = options[i];
            var text = option.text.toLowerCase();
            if (text.includes(input)) {
                option.style.display = '';
                hasMatch = true;
            } else {
                option.style.display = 'none';
            }
        }

        select.style.display = hasMatch ? '' : 'none';
    });

    document.getElementById('connect_to').addEventListener('change', function() {
        var selectedValue = this.value;
        if (selectedValue) {
            document.getElementById('form-action').value = 'link_relationship';
            document.getElementById('first_names').removeAttribute('required');
            document.getElementById('last_name').removeAttribute('required');
            document.getElementById('lookup').value = this.options[this.selectedIndex].text;
            this.style.display = 'none';
            document.getElementById('additional-fields').style.display = 'none';
        }
    }); 

});

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
    var newItemDescription=prompt("Enter your description here:", currentDescription);

    if(newItemDescription===null) {
        //User pressed cancel - abort
        return;
    }
    //the actual id we want to use is after the underscore in the text of the id passed to the function
    var item_id=id.split('_')[1];
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
        
        var events = [];
        var event_group_name = null;
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
                                location.reload();
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

// Handle the file selection and upload
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
            })
            .catch(error => {
                alert('An error occurred while uploading the image: ' + error.message);
            });
    }
}

function doAction(action, individualId, actionId) {
    console.log('Doing action:', action, 'for individual ID:', individualId, ' and action ID:', actionId);
    switch(action) {
        case 'delete_item':
            if (confirm('Are you sure you want to delete this item?')) {
                getAjax('delete_item', {individualId: individualId, itemId: actionId})
                    .then(response => {
                        if (response.status === 'success') {
                            // Reload the page
                            alert('Item has been deleted');
                            document.getElementById('item_id_'+actionId).remove();
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
        default:
            break;
    }
}

function openModal(action, individualId, individualGender) {
    console.log('Opened modal with action:', action, 'for individual ID:', individualId, ' and gender', individualGender);


    document.getElementById('add-relationship-form').action = '?to=family/individual&individual_id=' + individualId;
    // Get the modal and form elements for the "Add" form
    var modal = document.getElementById('popupForm');
    var closeButton = document.querySelector('.close-btn');
    var formActionInput = document.getElementById('form-action');
    var formActionGender = document.getElementById('gender');
    var formActionRelationship = document.getElementById('relationship');
    var relatedIndividualInput = document.getElementById('related-individual');    


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
    formActionInput.value = action;  // Set the action (add_parent, add_spouse, etc.)
    relatedIndividualInput.value = individualId;  // Set the related individual ID
    //Clear the other parent select of options
    var select = document.getElementById('second-parent');
    select.innerHTML = '';

    document.getElementById('modal-title').innerHTML = 'Add New Relationship';
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
        case 'add_daughter':
            formActionGender.value='female';
            formActionRelationship.value='child';
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
            //retrieve spouses using getSpouses(individualId) and then populate the "second-parent" select
            break;
        default:
            formActionGender.value='other';
            break;                
    }
    modal.style.display = 'block';  // Show the modal
}
