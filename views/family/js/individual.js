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
    

    // Function to open the "Edit" modal and populate it with the individual's data
    async function openEditModal(individualId) {
        try {
            // Fetch individual data
            const individualData = await getIndividualDataById(individualId);  // Wait for the data

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

});

function triggerFileUpload() {
    document.getElementById('fileUpload').click();
}

// Trigger the file upload dialog
function triggerFileUpload() {
    document.getElementById('fileUpload').click();
}

// Handle the file selection and upload
async function uploadKeyImage(individualId) {
    var fileInput = document.getElementById('fileUpload');
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
                if (response.status === 'success') {
                    //Update the individual's "photo" field to response.filepath
                    var individualUpdateData = {
                        individual_id: individualId,
                        photo: response.filepath
                    };
                    getAjax('update_individual', individualUpdateData)
                        .then(iResponse => {
                            if (iResponse.status === 'success') {
                                // Update the individual's key image reference in the UI
                                document.getElementById('keyImage').src = individualUpdateData.photo;
                            } else {
                                alert('Error: ' + iResponse.message);
                            }
                        })
                        .catch(error => {
                            alert('An error occurred while updating the individuals key image reference, however the photo has been uploaded to their collection: ' + error.message);
                        });
                } else {
                    alert('Error: ' + response.message);
                }
            })
            .catch(error => {
                alert('An error occurred while uploading the image: ' + error.message);
            });
    }
}
