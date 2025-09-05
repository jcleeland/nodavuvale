function addUser() {
    var inputs=['text_First Name', 'text_Last Name', 'text_Email', 'password_Password', 'select_Role', 'select_Approved', 'individual_Tree ID'];
    showCustomPrompt('Create User', 'Add a new user to your site', inputs, ['', '', '', '', ['unconfirmed','member','admin'], ['0','1'], null], async function(inputValues) {
        if (inputValues !== null) {

            
            var formData = new FormData();

            console.log('Input Values returned are:');
            console.log(inputValues);
            //return;
            
                var first_name=inputValues[0];
                var last_name=inputValues[1];
                var email=inputValues[2];
                var password=inputValues[3];
                var role=inputValues[4];
                var approved=inputValues[5];
                var individuals_id=inputValues[6];
                formData.append('method', 'add_user');
                formData.append('data', JSON.stringify({
                    first_name: first_name,
                    last_name: last_name,
                    email: email,
                    password: password,
                    role: role,
                    approved: approved,
                    individuals_id: individuals_id
                }));

            


            //Debugging: Log the formData object
            for (var pair of formData.entries()) {
                console.log(pair[0]+ ', ' + pair[1]); 
            }

            try {
                const response = await getAjax('add_user', formData);
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

function deleteUser(userId) {
    if(confirm('Are you sure you want to delete this user?')) {
        if(confirm('Seriously, deleting a user isn\'t something you should do lightly. \r\n\r\n Are you REALLY REALLY sure you want to delete this user?')) {
            var formData = new FormData();
            formData.append('method', 'delete_user');
            formData.append('data', JSON.stringify({
                user_id: userId
            }));

            //Debugging: Log the formData object
            for (var pair of formData.entries()) {
                console.log(pair[0]+ ', ' + pair[1]); 
            }

            getAjax('delete_user', formData).then(response => {
                if (response.status === 'success') {
                    // Reload the page
                    alert('User has been deleted');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            }).catch(error => {
                alert('An error occurred while deleting the user: ' + error.message);
            });
        }
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
        //see if the select with the name "individuals_id" exists
        //Look for an html hidden input with the id "individuals_id" and update its value to be user.individuals_id
        const hiddenInput = document.getElementById('individuals_id');
        if (hiddenInput) {
            hiddenInput.value = user.individuals_id;
        }

        console.log('User select is now: ' + hiddenInput.value);
    
        // Dispatch a change event to trigger the event listener
        // - Find a div with the class "dropdown-item" and the data-value attribute equal to user.individuals_id and "click" it
        const dropdownItem = document.querySelector(`.dropdown-item[data-value="${user.individuals_id}"]`);
        if (dropdownItem) {
            dropdownItem.click();
        }

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

function passwordReset(userId) {
    
    if(confirm("This will reset the users password to a random password and send them an email with the new password. Are you sure you want to continue?")) {
        //Send the email by ajax & 'password_reset' method
        var formData = new FormData();
        formData.append('method', 'password_reset');
        formData.append('data', JSON.stringify({
            user_id: userId,
        }));

        //Debugging: Log the formData object
        for (var pair of formData.entries()) {
            console.log(pair[0]+ ', ' + pair[1]); 
        }

        getAjax('password_reset', formData).then(response => {
            if (response.status === 'success') {
                // Reload the page
                alert('Password reset email has been sent');
            } else {
                alert('Error: ' + response.message);
            }
        }).catch(error => {
            alert('An error occurred while sending the password reset email: ' + error.message);
        });
    }
}

function emailUserLoginDetails(userId) {
    
    if(confirm("This will send the user an email with their login details. If they don't already have a password, it will also generate a new one of these. Are you sure you want to continue?")) {
        //Send the email by ajax & 'email_login_details' method
        var formData = new FormData();
        formData.append('method', 'welcome_email');
        formData.append('data', JSON.stringify({
            user_id: userId,
            action: 'welcome',
        }));

        //Debugging: Log the formData object
        for (var pair of formData.entries()) {
            console.log(pair[0]+ ', ' + pair[1]); 
        }

        getAjax('welcome_email', formData).then(response => {
            if (response.status === 'success') {
                // Reload the page
                alert('Login details email has been sent');
            } else {
                alert('Error: ' + response.message);
            }
        }).catch(error => {
            alert('An error occurred while sending the login details email: ' + error.message);
        });
    }
}