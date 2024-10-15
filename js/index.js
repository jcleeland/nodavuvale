/**
 * Calls the getAjax function to get the data from the server.
 * All ajax calls are routed through /ajax.php and must include a method and then a keyed array of data
 *
 * @param {*} method 
 * @param {*} data 
 * @returns 
 */
async function getAjax(method, data) {
    const options = {
        method: 'POST',
        body: data
    };

    if(!(data instanceof FormData)) {
        options.headers = {
            'Content-Type': 'application/json'
        };
        options.body = JSON.stringify({
            method: method,
            data: data
        });
    } else {
        data.append('method', method);
    }

    const response = await fetch('ajax.php', options);
    if (!response.ok) {
        throw new Error('Network response was not ok');
    }
    return await response.json();
}

function toggleVisibility(divId, buttonId) {
    var div = document.getElementById(divId);
    var button = document.getElementById(buttonId);
    if (div.classList.contains('hidden')) {
        div.classList.remove('hidden');
        button.textContent = 'Hide';
    } else {
        div.classList.add('hidden');
        button.textContent = 'Show';
    }
}

async function uploadFileAndItem(individualId, eventType, eventDetail, fileDescription, file, successCallback, errorCallback) {
    // Create a FormData object to handle file and other variables
    var formData = new FormData();
    
    // Prepare the POST data
    var data = {
        individual_id: individualId,
        event_type: eventType,
        event_detail: eventDetail,
        file_description: fileDescription
    };

    // Append the file separately to the FormData
    formData.append('file', file);

    try {
        // Use the getAjax function to send the data
        const response = await getAjax('add_file_item', data);

        // Check if the response is successful
        if (response.status === 'success') {
            // Call the success callback function
            if (typeof successCallback === 'function') {
                successCallback(response);
            } else {
                alert(response.message);
            }
        } else {
            // Call the error callback function
            if (typeof errorCallback === 'function') {
                errorCallback(response);
            } else {
                alert(response.message);
            }
        }
    } catch (error) {
        // Generic error handler for failed AJAX call
        if (typeof errorCallback === 'function') {
            errorCallback({status: 'error', message: 'An error occurred while uploading the file.'});
        } else {
            alert('An error occurred while uploading the file.');
        }
    }
}
