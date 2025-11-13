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
 * @param {Array<string>} inputs - An array of placeholder texts for the input fields. These should be in the format [inputfieldname_inputfieldtype], for example "filedescription_textarea"
 * @param {Array<string>} values - An array of default values for the input fields.
 * @param {function} callback - A callback function to handle the input values. The callback receives an array of input values if the user clicks "OK", or null if the user clicks "Cancel".
 *
 * @example
 * // Example usage:
 * showCustomPrompt(
 *     'Enter Details',
 *     'Please fill in the following details:',
 *     ['Name', 'Email', 'Phone', 'textarea_Essay'],
 *     ['John Doe', 'john@example.com', '', 'Once a jolly swagman...'],
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
    console.log('Showing custom prompt with inputs:', inputs, 'and values:', values);
    var customPrompt = document.getElementById('customPrompt');
    var customPromptTitle = document.getElementById('customPromptTitle');
    var customPromptMessage = document.getElementById('customPromptMessage');
    var customPromptInputs = document.getElementById('customPromptInputs');
    var customPromptCancel = document.getElementById('customPromptCancel');
    var customPromptClose = document.getElementById('customPromptClose');
    var customPromptOk = document.getElementById('customPromptOk');

    function cleanupEditors() {
        if (typeof tinymce !== 'undefined' && tinymce.editors) {
            tinymce.editors
                .filter(function (editor) {
                    return editor.id && editor.id.indexOf('customPromptInput') === 0;
                })
                .forEach(function (editor) {
                    editor.remove();
                });
        }
    }

    customPromptTitle.textContent = title;
    customPromptMessage.innerHTML = message;
    customPromptInputs.innerHTML = ''; // Clear previous inputs

    var isLanguage=false;
    // Create input elements based on the inputs and values arrays
    inputs.forEach((input, index) => {
        var inputElement = document.createElement('input');
        //Split the "input" string at the underscore, use the first 
        // part in a switch() { and the second part as the name of the input
        switch (input.split('_')[0]) {
            case 'textarea':
                var inputElement = document.createElement('textarea');
                inputElement.rows=10;
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
            case 'language':
                isLanguage=true;
                // Create a look-ahead style input that will display a list of languages as you type, and when you select them, add them to a hidden
                // element that will be submitted with the form
                var languages = ['Abkhaz', 'Afar', 'Afrikaans', 'Akan', 'Albanian', 'Amharic', 'Arabic', 'Aragonese', 'Armenian', 'Assamese', 'Avaric', 'Avestan', 'Aymara', 'Azerbaijani', 'Bambara', 'Bashkir', 'Basque', 'Belarusian', 'Bengali', 'Bihari', 'Bislama', 'Bosnian', 'Breton', 'Bulgarian', 'Burmese', 'Catalan', 'Chamorro', 'Chechen', 'Chichewa', 'Chinese', 'Chuvash', 'Cornish', 'Corsican', 'Cree', 'Croatian', 'Czech', 'Danish', 'Divehi', 'Dutch', 'Dzongkha', 'English', 'Esperanto', 'Estonian', 'Ewe', 'Faroese', 'Finnish', 'French', 'Fula', 'Galician', 'Georgian', 'German', 'Greek', 'Guaraní', 'Gujarati', 'Haitian', 'Hausa', 'Hebrew', 'Herero', 'Hindi', 'Hiri Motu', 'Hungarian', 'Icelandic', 'Ido', 'Igbo', 'Indonesian', 'Interlingua', 'Interlingue', 'Inuktitut', 'Inupiaq', 'Irish', 'Italian', 'Japanese', 'Javanese', 'Kalaallisut', 'Kannada', 'Kanuri', 'Kashmiri', 'Kazakh', 'Khmer', 'Kikuyu', 'Kinyarwanda', 'Kirghiz', 'Komi', 'Kongo', 'Korean', 'Kurdish', 'Kwanyama', 'Lao', 'Latin', 'Latvian', 'Limburgish', 'Lingala', 'Lithuanian', 'Luba-Katanga', 'Luxembourgish', 'Macedonian', 'Malagasy', 'Malay', 'Malayalam', 'Maltese', 'Manx', 'Maori', 'Marathi', 'Marshalle', 'Moldovan', 'Mongolian', 'Nauru', 'Navajo', 'Ndonga', 'Nepali', 'North Ndebele', 'Northern Sami', 'Norwegian', 'Norwegian Bokmål', 'Norwegian Nynorsk', 'Nuosu', 'Occitan', 'Ojibwe', 'Old Church Slavonic', 'Oriya', 'Oromo', 'Ossetian', 'Pali', 'Pashto', 'Persian', 'Polish', 'Portuguese', 'Punjabi', 'Quechua', 'Romanian', 'Romansh', 'Russian', 'Samoan', 'Sango', 'Sanskrit', 'Sardinian', 'Scottish Gaelic', 'Serbian', 'Shona', 'Sindhi', 'Sinhala', 'Slovak', 'Slovene', 'Somali', 'Southern Ndebele', 'Southern Sotho', 'Spanish', 'Sundanese', 'Swahili', 'Swati', 'Swedish', 'Tagalog', 'Tahitian', 'Tajik', 'Tamil', 'Tatar', 'Telugu', 'Thai', 'Tibetan', 'Tigrinya', 'Tonga', 'Tsonga', 'Tswana', 'Turkish', 'Turkmen', 'Twi', 'Uighur', 'Ukrainian', 'Urdu', 'Uzbek', 'Venda', 'Vietnamese', 'Volapük', 'Walloon', 'Welsh', 'Western Frisian', 'Wolof', 'Xhosa', 'Yiddish', 'Yoruba', 'Zhuang', 'Zulu'];
                // add the most common south pacific and maori languages
                languages.push('Tongan', 'Samoan', 'Cook Island Maori', 'Niuean', 'Tokelauan', 'Tuvaluan', 'Kiribati', 'Fijian', 'Rotuman', 'Tahitian');
                // remove duplicates
                languages = [...new Set(languages)];
                languages.sort();

                var selectedLanguages = document.createElement('div');
                selectedLanguages.id = 'selectedLanguages';
                selectedLanguages.className = 'w-full flex justify-around items-center p-2 bg-cream border h-12 rounded-lg mb-2'; 
                customPromptInputs.appendChild(selectedLanguages);
                //If default values have been passed, then split the value into an array and display the languages in the selectedLanguages div
                if(values[index]) {
                    var selectedLanguages = values[index].split(',');
                    var selectedLanguagesDiv = document.getElementById('selectedLanguages');
                    selectedLanguages.forEach(language => {
                        var selectedLanguage = document.createElement('div');
                        selectedLanguage.textContent = language;
                        selectedLanguage.className = 'inline-block bg-gray-200 rounded p-2 m-2 cursor-not-allowed bg-brown text-white';
                        selectedLanguage.title = 'Click to remove';
                        selectedLanguagesDiv.appendChild(selectedLanguage);
                        //Add a click event to remove the language from the selectedLanguages div
                        selectedLanguage.addEventListener('click', function() {
                            // Remove the language from the hidden input value
                            var selectedLanguages = inputElement.value.split(',');
                            var index = selectedLanguages.indexOf(language);
                            if (index > -1) {
                                selectedLanguages.splice(index, 1);
                            }
                            inputElement.value = selectedLanguages.join(',');

                            // Remove the selected language from the selectedLanguages div
                            selectedLanguage.remove();
                        });
                    });
                } else {
                    selectedLanguages.textContent = 'No languages selected';
                }

                var workingElement = document.createElement('input');
                workingElement.type = 'text';
                workingElement.id = 'language_selector';
                workingElement.placeholder = 'Find a language';
                workingElement.className = 'w-full p-2 border rounded mb-2';
                //workingElement.value = values[index] || '';
                customPromptInputs.appendChild(workingElement);

                // Create a hidden input to store the selected languages
                var inputElement = document.createElement('input');
                inputElement.type = 'hidden';
        inputElement.id = 'customPromptInput' + index;
        inputElement.name = 'customPromptInput' + index;
        inputElement.placeholder = input || '';
        if (typeof values[index] !== 'undefined' && values[index] !== null) {
            inputElement.value = values[index];
        } else {
            inputElement.value = '';
        }
        customPromptInputs.appendChild(inputElement);

                // Create a list of languages to display as options
                var languageList = document.createElement('div');
                languageList.className = 'language-list hidden p-2 border rounded bg-white shadow-md absolute w-full cursor-pointer';
                
                //Create a div above the input to show selected languages

                languages.forEach(language => {
                    var languageOption = document.createElement('div');
                    languageOption.textContent = language;
                    languageOption.addEventListener('click', function() {
                        // Add the selected language to the hidden input value
                        var selectedLanguages = inputElement.value.split(',');
                        selectedLanguages.push(language);
                        inputElement.value = selectedLanguages.join(',');

                        //Add the selected language to the selectedLanguages div
                        var selectedLanguage = document.createElement('div');
                        selectedLanguage.textContent = language;
                        selectedLanguage.className = 'inline-block bg-gray-200 rounded p-2 m-2 cursor-not-allowed bg-brown text-white';
                        selectedLanguage.title = 'Click to remove';
                        var languageDiv=document.getElementById('selectedLanguages');
                        //If the text "No languages selected" is present, remove it
                        if(languageDiv.textContent === 'No languages selected') {
                            languageDiv.textContent = '';
                        }
                        languageDiv.appendChild(selectedLanguage);

                        //Add a click event to remove the language from the selectedLanguages div
                        selectedLanguage.addEventListener('click', function() {
                            // Remove the language from the hidden input value
                            var selectedLanguages = inputElement.value.split(',');
                            var index = selectedLanguages.indexOf(language);
                            if (index > -1) {
                                selectedLanguages.splice(index, 1);
                            }
                            inputElement.value = selectedLanguages.join(',');

                            // Remove the selected language from the selectedLanguages div
                            selectedLanguage.remove();
                        });

                        // Clear the input field
                        workingElement.value = '';

                        // Hide the language list
                        languageList.classList.add('hidden');
                        //Focus on the input element
                        workingElement.focus();

                        // Prevent the default click event
                        return false;


                    });
                    languageList.appendChild(languageOption);
                });

                // when the input has any characters, 
                // show the language list, filtered by the input value
                workingElement.addEventListener('input', function() {
                    //remove the "hidden" class from the language list
                    languageList.classList.remove('hidden');

                    var input = this.value.toLowerCase();
                    var options = languageList.children;
                    for (var i = 0; i < options.length; i++) {
                        var option = options[i];
                        var text = option.textContent.toLowerCase();
                        if (text.includes(input)) {
                            option.style.display = '';
                        } else {
                            option.style.display = 'none';
                        }
                    }
                });
                

                // Hide the language list when the input is blurred
                workingElement.addEventListener('blur', function() {
                    setTimeout(() => {
                        languageList.classList.add('hidden');
                    }, 200);
                });

                customPromptInputs.appendChild(languageList);


                return;

            case 'individual':
                var inputContainer = document.createElement('div');
                inputContainer.className = 'w-full';
            
                // Create input field for name search
                var inputElement = document.createElement('input');
                inputElement.type = 'text';
                inputElement.id = 'customPromptInput' + index + '_name';
                inputElement.className = 'w-full p-2 border rounded mb-2';
                inputElement.placeholder = 'Find another individual..';
                
                // Create hidden input to store the selected individual ID
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.id = 'customPromptInput' + index;
                hiddenInput.name = 'customPromptInput' + index;
            
                // Create a div to show suggestions
                var suggestionsContainer = document.createElement('div');
                suggestionsContainer.id = 'customPromptInput' + index + '_suggestions';
                suggestionsContainer.className = 'autocomplete-suggestions absolute bg-white shadow-md border rounded w-full z-50';
            
                inputContainer.appendChild(inputElement);
                inputContainer.appendChild(hiddenInput);
                inputContainer.appendChild(suggestionsContainer);
                customPromptInputs.appendChild(inputContainer);
            
                // If a value exists, populate the input field
                if (values[index]) {
                    var selectedIndividual = individuals.find(ind => ind.id == values[index]);
                    if (selectedIndividual) {
                        inputElement.value = selectedIndividual.name;
                        hiddenInput.value = selectedIndividual.id;
                    }
                }
            
                inputElement.addEventListener('input', function () {
                    const searchValue = inputElement.value.toLowerCase();
                    suggestionsContainer.innerHTML = ''; // Clear previous suggestions
                
                    if (searchValue.length === 0) {
                        return;
                    }
                
                    // Filter individuals based on input
                    const filteredIndividuals = individuals.filter(ind => ind.name.toLowerCase().includes(searchValue));
                    filteredIndividuals.forEach(ind => {
                        const suggestion = document.createElement('div');
                        suggestion.className = 'autocomplete-suggestion p-2 flex items-center gap-2 hover:bg-gray-200 cursor-pointer';
                        
                        // Create a temporary div to parse and safely inject HTML
                        const tempElement = document.createElement('div');
                        tempElement.innerHTML = ind.name; // This might contain an <img> tag
                
                        // Extract the HTML content
                        const htmlContent = tempElement.innerHTML;
                
                        // Set the HTML content correctly inside the suggestion box
                        suggestion.innerHTML = htmlContent;
                
                        suggestion.onclick = function () {
                            selectSuggestion(ind);
                        };
                
                        suggestionsContainer.appendChild(suggestion);
                    });
                });
                
                function selectSuggestion(individual) {
                    const tempElement = document.createElement('div');
                    tempElement.innerHTML = individual.name; // Parse and extract text safely
                
                    const textContent = tempElement.textContent || tempElement.innerText || ''; // Get only text content
                
                    inputElement.value = textContent; // Store only the text, not the HTML
                    hiddenInput.value = individual.id;
                    suggestionsContainer.innerHTML = ''; // Hide suggestions
                }
                
            
                return;
                
            case 'individual2':
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
            case 'select':
                var inputElement = document.createElement('select');
                inputElement.id = 'customPromptInput' + index;
                inputElement.className = 'w-full p-2 border rounded mb-2';

                var selectConfig = values[index];
                var optionItems = [];
                var defaultValue = '';

                if (Array.isArray(selectConfig)) {
                    optionItems = selectConfig;
                } else if (selectConfig && typeof selectConfig === 'object') {
                    if (Array.isArray(selectConfig.options)) {
                        optionItems = selectConfig.options;
                    }
                    if (typeof selectConfig.defaultValue !== 'undefined' && selectConfig.defaultValue !== null) {
                        defaultValue = String(selectConfig.defaultValue);
                    } else if (typeof selectConfig.value !== 'undefined' && selectConfig.value !== null) {
                        defaultValue = String(selectConfig.value);
                    }
                } else if (typeof selectConfig === 'string' || typeof selectConfig === 'number') {
                    defaultValue = String(selectConfig);
                }

                var placeholderOption = document.createElement('option');
                placeholderOption.value = '';
                placeholderOption.text = 'Select Option..';
                inputElement.appendChild(placeholderOption);

                optionItems.forEach(function(option) {
                    var optionElement = document.createElement('option');
                    if (option && typeof option === 'object') {
                        var optionValue = typeof option.value !== 'undefined' ? option.value : (option.label || '');
                        optionElement.value = optionValue;
                        optionElement.text = option.label || optionValue;
                    } else {
                        optionElement.value = option;
                        optionElement.text = option;
                    }
                    inputElement.appendChild(optionElement);
                });

                if (defaultValue !== '') {
                    inputElement.value = defaultValue;
                }

                customPromptInputs.appendChild(inputElement);
                return;
            case 'url':
                var inputElement = document.createElement('input');
                inputElement.type = 'text';
                inputElement.placeholder = 'URL';
                inputElement.patter='https?://.+';
                break;
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

        //If there is a "selectedlanguages" div, and there is a value, then split the value into an array and display the languages in the selectedLanguages div
        if(isLanguage && values[index]) {
            var selectedLanguages = values[index].split(',');
            var selectedLanguagesDiv = document.getElementById('selectedLanguages');
            selectedLanguages.forEach(language => {
                var selectedLanguage = document.createElement('div');
                selectedLanguage.textContent = language;
                selectedLanguage.className = 'inline-block bg-gray-200 rounded p-1 m-1';
                selectedLanguagesDiv.appendChild(selectedLanguage);
            });
            
        }
        

        //Iterate through any textareas in the customPromptInputs and gather their to use when initialising tinymce:
        var textareas = customPromptInputs.getElementsByTagName('textarea');
        for (var i = 0; i < textareas.length; i++) {
            var textarea = textareas[i];
            var textareaId = textarea.id;
            //console.log('Initialising tinyMCE on ' + textareaId);
            tinymce.init({
                selector: '#' + textareaId,
                plugins: 'advlist autolink code lists link image charmap preview anchor pagebreak',
                menubar: false,
                toolbar_mode: 'sliding',
                toolbar1: 'undo redo | styleselect | fontfamily fontsize | bold italic underline | forecolor backcolor | alignleft aligncenter alignright alignjustify ', 
                toolbar2: 'bulllist numlist outdent indent | link image | code removeformat | preview',
                promotion: false,
                license_key: 'gpl',
                setup: function (editor) {
                    editor.on('change', function () {
                        tinymce.triggerSave();
                    });
                }
            });
        }
    });

    customPrompt.classList.add('show');

    var promptHandled = false;

    function gatherPromptValues() {
        console.log('Inputs:');
        console.log(inputs);
        return inputs.map((input, index) => {
            var inputElement = document.getElementById('customPromptInput' + index);
            console.log('Inspecting input element:', input);
            //if the last 4 characters of the input string are 'file' then return the file object
            if (input.split('_')[0].toLowerCase() === 'file') {
                //console.log(input.toLowerCase().slice(-4));
                return inputElement.files[0]; // Return the File object
            }
            return inputElement.value;
        });
    }

    function closePrompt(result) {
        customPrompt.classList.remove('show');
        cleanupEditors();
        callback(result);
    }

    function handlePromptOk(event) {
        if (promptHandled) {
            if (event && event.type === 'touchstart' && event.cancelable) {
                event.preventDefault();
            }
            return;
        }
        promptHandled = true;
        if (event && event.type === 'touchstart' && event.cancelable) {
            event.preventDefault();
        }
        if (typeof tinymce !== 'undefined' && tinymce.editors && tinymce.editors.length > 0) {
            tinymce.triggerSave();
        }
        var inputValues = gatherPromptValues();
        console.log('Returning input values:', inputValues);
        closePrompt(inputValues);
    }

    function handlePromptCancel(event) {
        if (promptHandled && event && event.type === 'touchstart') {
            event.preventDefault();
            return;
        }
        promptHandled = true;
        if (event && event.type === 'touchstart' && event.cancelable) {
            event.preventDefault();
        }
        closePrompt(null);
    }

    customPromptClose.onclick = handlePromptCancel;
    customPromptCancel.onclick = handlePromptCancel;
    customPromptOk.onclick = handlePromptOk;

    customPromptClose.ontouchstart = handlePromptCancel;
    customPromptCancel.ontouchstart = handlePromptCancel;
    customPromptOk.ontouchstart = handlePromptOk;

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

function expandStory(textId) {
    //console.log(textId);
    document.getElementById(textId).classList.toggle('hidden');
    document.getElementById('full'+textId).classList.toggle('hidden');
}

function shrinkStory(textId) {
    //console.log(textId);
    document.getElementById(textId).classList.toggle('hidden');
    document.getElementById('full'+textId).classList.toggle('hidden');
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
    const tabs = document.querySelectorAll(tabSelector);
    if (tabs.length === 0) return;

    // Retrieve the active tab from localStorage
    //console.log('Storage key');
    //console.log(storageKey);
    //console.log(localStorage.getItem(storageKey));
    const activeTabId = localStorage.getItem(storageKey);
    //Check that the chosen activeTabId is a valid tab
    if (activeTabId) {
        
        // Remove active class from all tabs and tab contents
        document.querySelectorAll(tabSelector).forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll(contentSelector).forEach(content => content.classList.remove('active'));

        // Add active class to the stored tab and its content
        tempTabName=activeTabId;
        if(document.querySelector(`${tabSelector}[data-tab="${activeTabId}"]`) === null) {
            console.log('Its null');
            tempTabName = tabs[0].getAttribute('data-tab');
        } 
        document.querySelector(`${tabSelector}[data-tab="${tempTabName}"]`).classList.add('active');
        document.getElementById(tempTabName).classList.add('active');
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
}

function showTab(tabId) {
    tempTabName=tabId;
    tabSelector='.tab';
    contentSelector='.tab-content';

    const tabs = document.querySelectorAll(tabSelector);
    document.querySelectorAll(tabSelector).forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll(contentSelector).forEach(content => content.classList.remove('active'));

    // Add active class to the stored tab and its content
    if(document.querySelector(`${tabSelector}[data-tab="${tempTabName}"]`) === null) {
        console.log('Its null');
        tempTabName = tabs[0].getAttribute('data-tab');
    } else {
        document.querySelector(`${tabSelector}[data-tab="${tempTabName}"]`).classList.add('active');
        document.getElementById(tempTabName).classList.add('active');
    }

}

/**
 * Check for the "findindividual_lookup" and "findindividual_connect_to" elements
 */
document.addEventListener('DOMContentLoaded', function() {
    // Store the parent element id for each of the "findindividual_lookup" elements in an array
    var parentElements = [];

    // Get all the elements with the class "findindividual_lookup" and if they haven't already got an event listener, add an event listener to each
    var elements = document.getElementsByClassName('findindividual_lookup');
    for (var i = 0; i < elements.length; i++) {
        if (elements[i].getAttribute('data-listener') !== 'true') {
            // Save the parent element id in parentElements
            parentElements.push(elements[i].parentElement.id);
            // console.log('Added parent element to array ' + elements[i].parentElement.id);

            elements[i].setAttribute('data-listener', 'true');
            elements[i].addEventListener('input', function() {
                var input = this.value.toLowerCase();
                var dropdown = this.nextElementSibling;
                var items = dropdown.getElementsByClassName('dropdown-item');
                var hasMatch = false;

                for (var j = 0; j < items.length; j++) {
                    var item = items[j];
                    var text = item.textContent.toLowerCase();
                    if (text.includes(input)) {
                        item.style.display = '';
                        hasMatch = true;
                    } else {
                        item.style.display = 'none';
                    }
                }

                dropdown.style.display = hasMatch ? 'block' : 'none';
            });
        }
    }

    // Now go through all the parentElements, and add an event listener to the dropdown items with the class 'dropdown-item'.
    for (var i = 0; i < parentElements.length; i++) {
        // console.log('Looking at parent element: ' + parentElements[i]);
        if (document.getElementById(parentElements[i]) !== null) {
            // console.log('Found parent element: ' + parentElements[i]);
            var parentElement = document.getElementById(parentElements[i]);
            var dropdown = parentElement.getElementsByClassName('findindividual_connect_to')[0];
            var lookupInput = parentElement.getElementsByClassName('findindividual_lookup')[0];
            var hiddenInput = parentElement.querySelector('input[type="hidden"]');

            dropdown.addEventListener('click', function(event) {
                var target = event.target.closest('.dropdown-item');
                if (target) {
                    var value = target.getAttribute('data-value');
                    var text = target.textContent || target.innerText;
                    lookupInput.value = text;
                    hiddenInput.value = value;
                    dropdown.style.display = 'none';
                }
            });

            document.addEventListener('click', function(event) {
                if (!lookupInput.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.style.display = 'none';
                }
            });
        }
    }
});

function userResetPassword() {
    var proposedEmailAddress=document.getElementById('requested_user_email').value;

    if(proposedEmailAddress.length === 0) {
        alert('Please enter an email address');
        return;
    }

    //Send the email by ajax & 'password_reset' method
    var formData = new FormData();
    formData.append('method', 'password_reset');
    formData.append('data', JSON.stringify({
        email: proposedEmailAddress,
    }));

    //Debugging: Log the formData object
    for (var pair of formData.entries()) {
        console.log(pair[0]+ ', ' + pair[1]); 
    }

    getAjax('password_reset', formData).then(response => {
        if (response.status === 'success') {
            // Reload the page
            document.getElementById('emailSent').classList.remove('hidden');
            document.getElementById('resetPasswordButton').classList.add('hidden');
        } else {
            alert('Error: ' + response.message);
        }
    }).catch(error => {
        alert('An error occurred while sending the password reset email: ' + error.message);
    });
}

function editUserField(fieldname, description, userId, event) {
    let fieldType = "text"; // Default input type
    //See if the element which called this function has a value for data-field-value,
    // and if so, use that as the default value
    let editDefaultValue = '';
    //Get the details of the element that called this function
    
    if(event.target.getAttribute('data-field-value')) {
        console.log('Found data-field-value');
        editDefaultValue = event.target.getAttribute('data-field-value');
    } else {
        console.log('Could not find any data to use as default value');
    }
    console.log('Default value: ' + editDefaultValue);
    // Define input types for specific fields
    switch (fieldname) {
        case "about":
            fieldType = "textarea_";
            break;
        case "languages_spoken":
            fieldType = "language_"; // Use json for multiple languages
            break;
        case "skills":
            fieldType = "textarea_"; // Use textarea for multiple skills
            break;
        case "location":
            fieldType = "_";
            break;
    }

    // Show the custom prompt
    showCustomPrompt(
        `Edit ${fieldname.replace("_", " ")}`,
        description,
        [fieldType+fieldname],
        [editDefaultValue], // Default value
        function (inputValues) {
            if (inputValues) {
                var thisvalue=inputValues[0];
                if(fieldType === 'language_') {
                    //Split the value into an array
                    thisvalue = thisvalue.split(',');
                    //Convert the value to a json string
                    thisvalue = JSON.stringify(thisvalue);
                }
                const data = {
                    'user_id': userId,
                    //how do I set the key to the fieldname?
                    [fieldname]: thisvalue,                    
                };
                getAjax('update_user', data).then(response => {
                    if (response.status === 'success') {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }).catch(error => {
                    alert('An error occurred while updating the user: ' + error.message);
                });
            }
        }
    );
}


