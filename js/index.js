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

    if (data instanceof FormData) {
        data.append('method', method);
    } else {
        options.headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        options.body = JSON.stringify({
            method: method,
            data: data
        });
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

function showCustomPrompt(title, message, inputs, values, callback) {
    var customPrompt = document.getElementById('customPrompt');
    var customPromptTitle = document.getElementById('customPromptTitle');
    var customPromptMessage = document.getElementById('customPromptMessage');
    var customPromptInputs = document.getElementById('customPromptInputs');
    var customPromptCancel = document.getElementById('customPromptCancel');
    var customPromptOk = document.getElementById('customPromptOk');

    customPromptTitle.textContent = title;
    customPromptMessage.innerHTML = message;
    customPromptInputs.innerHTML = ''; // Clear previous inputs

    // Create input elements based on the inputs and values arrays
    inputs.forEach((input, index) => {
        var inputElement = document.createElement('input');
        inputElement.type = 'text';
        inputElement.id = 'customPromptInput' + index;
        inputElement.className = 'w-full p-2 border rounded mb-2';
        inputElement.placeholder = input;
        inputElement.value = values[index] || '';
        customPromptInputs.appendChild(inputElement);
    });

    customPrompt.classList.add('show');

    customPromptCancel.onclick = function() {
        customPrompt.classList.remove('show');
        callback(null);
    };

    customPromptOk.onclick = function() {
        customPrompt.classList.remove('show');
        var inputValues = inputs.map((_, index) => document.getElementById('customPromptInput' + index).value);
        callback(inputValues);
    };

    // Handle Enter key for submission
    customPromptInputs.onkeydown = function(event) {
        if (event.key === 'Enter') {
            customPromptOk.click();
        }
    };
}

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