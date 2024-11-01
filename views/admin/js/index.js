function addUser() {
    var inputs=['text_First Name', 'text_Last Name', 'text_Email', 'password_Password', 'text_Role', 'text_Approved', 'individual_Tree ID'];
    showCustomPrompt('Create User', 'Add a new user to your site', inputs, ['', '', '', '', 'member', '0', null], async function(inputValues) {
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
                const response = await getAjax('add_file_item', formData);
                if (response.status === 'success') {
                    // Reload the page
                    //alert('Item has been added');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            } catch (error) {
                alert('An error occurred while adding the item: ' + error.message);
            }
        }
    });


}

async function approveUser($id, unapprove=false) {
    var formData = new FormData();
    formData.append('method', 'approve_user');
    formData.append('data', JSON.stringify({
        user_id: $id,
        unapprove: unapprove
    }));

    //Debugging: Log the formData object
    for (var pair of formData.entries()) {
        console.log(pair[0]+ ', ' + pair[1]); 
    }

    try {
        const response = await getAjax('approve_user', formData);
        if (response.status === 'success') {
            // Reload the page
            if(unapprove) {
                alert('User has been unapproved');
            } else {            
                alert('User has been approved');
            }
            location.reload();
        } else {
            alert('Error: ' + response.message);
        }
    } catch (error) {
        alert('An error occurred while adding the item: ' + error.message);
    }
}

function editUser(userId) {
    // Fetch user data and populate the form
    const user = window.usersData.find(user => user.id === userId);
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editFirstName').value = user.first_name;
    document.getElementById('editLastName').value = user.last_name;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editRole').value = user.role;
    document.getElementById('editApproved').value = user.approved;
    if(user.individuals_id) {
        const selectElement = document.querySelector('select[name="individuals_id"]');
        selectElement.value = user.individuals_id;
        console.log('User select is now: ' + selectElement.value);
    
        // Dispatch a change event to trigger the event listener
        const event = new Event('change', { bubbles: true });
        selectElement.dispatchEvent(event);
    } else {
        const inputElement=document.querySelector('input[name="editIndividualsId"]');
        inputElement.value = user.first_name;
        const event = new Event('change', { bubbles: true });
        inputElement.dispatchEvent(event);
    }
    //find the select with the name "individuals_id" (not id) and set the value to the user.individuals_id
    


    // Show the modal
    document.getElementById('editUserModal').classList.remove('hidden');
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
}

function updateUser() {
    var userId = document.getElementById('editUserId').value;
    var firstName = document.getElementById('editFirstName').value;
    var lastName = document.getElementById('editLastName').value;
    var email = document.getElementById('editEmail').value;
    var role = document.getElementById('editRole').value;
    var approved = document.getElementById('editApproved').value;
    var individualsId = document.querySelector('select[name="individuals_id"]').value;

    var formData = new FormData();
    formData.append('method', 'update_user');
    formData.append('data', JSON.stringify({
        user_id: userId,
        first_name: firstName,
        last_name: lastName,
        email: email,
        role: role,
        approved: approved,
        individuals_id: individualsId
    }));

    //Debugging: Log the formData object
    for (var pair of formData.entries()) {
        console.log(pair[0]+ ', ' + pair[1]); 
    }

    getAjax('update_user', formData).then(response => {
        if (response.status === 'success') {
            // Reload the page
            alert('User has been updated');
            location.reload();
        } else {
            alert('Error: ' + response.message);
        }
    }).catch(error => {
        alert('An error occurred while updating the user: ' + error.message);
    });
}