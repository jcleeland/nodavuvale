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