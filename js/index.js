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

/**
 * Displays a custom prompt dialog with a title, message, and multiple input fields.
 *
 * @param {string} title - The title of the custom prompt dialog.
 * @param {string} message - The message or description to display in the custom prompt dialog.
 * @param {Array<string>} inputs - An array of placeholder texts for the input fields.
 * @param {Array<string>} values - An array of default values for the input fields.
 * @param {function} callback - A callback function to handle the input values. The callback receives an array of input values if the user clicks "OK", or null if the user clicks "Cancel".
 *
 * @example
 * // Example usage:
 * showCustomPrompt(
 *     'Enter Details',
 *     'Please fill in the following details:',
 *     ['Name', 'Email', 'Phone'],
 *     ['John Doe', 'john@example.com', ''],
 *     function(inputValues) {
 *         if (inputValues) {
 *             console.log('User input:', inputValues);
 *         } else {
 *             console.log('User cancelled the prompt.');
 *         }
 *     }
 * );
 */
function showCustomPrompt(title, message, inputs, values, callback) {
    var customPrompt = document.getElementById('customPrompt');
    var customPromptTitle = document.getElementById('customPromptTitle');
    var customPromptMessage = document.getElementById('customPromptMessage');
    var customPromptInputs = document.getElementById('customPromptInputs');
    var customPromptCancel = document.getElementById('customPromptCancel');
    var customPromptClose = document.getElementById('customPromptClose');
    var customPromptOk = document.getElementById('customPromptOk');

    customPromptTitle.textContent = title;
    customPromptMessage.innerHTML = message;
    customPromptInputs.innerHTML = ''; // Clear previous inputs

    // Create input elements based on the inputs and values arrays
    inputs.forEach((input, index) => {
        var inputElement = document.createElement('input');
        //Split the "input" string at the underscore, use the first 
        // part in a switch() { and the second part as the name of the input
        switch (input.split('_')[0]) {
            case 'textarea':
                var inputElement = document.createElement('textarea');
                inputElement.rows=8;
                break;
            case 'date':
                var inputElement = document.createElement('input');
                inputElement.type = 'text';
                inputElement.placeholder='YYYY-MM-DD';
                inputElement.pattern='\\d{4}(-\\d{2})?(-\\d{2})?'
                break;
            case 'file':
                var inputElement = document.createElement('input');
                inputElement.type = 'file';
                break;
            case 'individual':
                var inputElement = document.createElement('select');
                inputElement.id = 'customPromptInput' + index;
                inputElement.className = 'w-full p-2 border rounded mb-2';
                inputElement.value = values[index] || '';
                customPromptInputs.appendChild(inputElement);
                if(typeof individuals !== 'undefined' && individuals !== null) {
                    var startoption = document.createElement('option');
                    startoption.value='';
                    startoption.text='Select Individual..';

                    inputElement.appendChild(startoption);
                    individuals.forEach(function(individual) {
                        var option = document.createElement('option');
                        option.value = individual.id;
                        option.text = individual.name;
                        inputElement.appendChild(option);
                    });
                }
                return;
            default:
                var inputElement = document.createElement('input');
                inputElement.type = input.split('_')[0];
                break;
        }
        if(!inputElement.placeholder) inputElement.placeholder = input.split('_')[1];
        inputElement.id = 'customPromptInput' + index;
        inputElement.className = 'w-full p-2 border rounded mb-2';
        inputElement.value = values[index] || '';
        customPromptInputs.appendChild(inputElement);
    });

    customPrompt.classList.add('show');

    customPromptClose.onclick = function() {
        customPrompt.classList.remove('show');
        callback(null);
    };

    customPromptCancel.onclick = function() {
        customPrompt.classList.remove('show');
        callback(null);
    };

    customPromptOk.onclick = function() {
        customPrompt.classList.remove('show');
        var inputValues = inputs.map((input, index) => {
            var inputElement = document.getElementById('customPromptInput' + index);
            if (input.toLowerCase().includes('file')) {
                return inputElement.files[0]; // Return the File object
            }
            return inputElement.value;
        });
        callback(inputValues);
    };

    // Handle Enter key for submission
    customPromptInputs.onkeydown = function(event) {
        if (event.target.tagName === 'TEXTAREA' && event.key === 'Enter') {
            event.stopPropagation(); // Prevent the event from bubbling up
        } else if (event.key === 'Enter') {
            customPromptOk.click();
        }
    };
}

function showStory(title, textId) {
    var text=document.getElementById(textId).textContent;
    showCustomPrompt(title, text, [], [], function(inputValues) {
        //Do nothing
    });
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
        //console.log('Output from getSpouses:', output);
        return output.parents;
    } catch (error) {
        console.error('Error fetching individual data:', error);
    }
}

function initialiseTabs(tabSelector, contentSelector, storageKey) {
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll(tabSelector);
        if (tabs.length === 0) return;

        // Retrieve the active tab from localStorage
        console.log('Storage key');
        console.log(storageKey);
        console.log(localStorage.getItem(storageKey));
        const activeTabId = localStorage.getItem(storageKey);
        if (activeTabId) {
            // Remove active class from all tabs and tab contents
            document.querySelectorAll(tabSelector).forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll(contentSelector).forEach(content => content.classList.remove('active'));

            // Add active class to the stored tab and its content
            document.querySelector(`${tabSelector}[data-tab="${activeTabId}"]`).classList.add('active');
            document.getElementById(activeTabId).classList.add('active');
        }

        // Add click event listeners to tabs
        document.querySelectorAll(tabSelector).forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and tab contents
                document.querySelectorAll(tabSelector).forEach(t => t.classList.remove('active'));
                document.querySelectorAll(contentSelector).forEach(tc => tc.classList.remove('active'));

                // Add active class to the clicked tab and its content
                this.classList.add('active');
                document.getElementById(this.getAttribute('data-tab')).classList.add('active');

                // Store the active tab ID in localStorage
                localStorage.setItem(storageKey, this.getAttribute('data-tab'));
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Add event listener to the "Find Individual" look-ahead input
    // Check to see if it exists before adding the event listener
    if(document.getElementById('findindividual_lookup') !== null) {
        document.getElementById('findindividual_lookup').addEventListener('input', function() {
            var input = this.value.toLowerCase();
            var select = document.getElementById('findindividual_connect_to');
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
    }

    if(document.getElementById('findindividual_connect_to') !== null) {
        document.getElementById('findindividual_connect_to').addEventListener('change', function() {
            var selectedValue = this.value;
            if (selectedValue) {
                document.getElementById('findindividual_lookup').value = this.options[this.selectedIndex].text;
                this.style.display = 'none';
            }
        }); 
    }

});

