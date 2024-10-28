let isShowingDropdown = false;
document.addEventListener("DOMContentLoaded", function() {
    // Create a single dropdown menu and append it to the body
    const dropdownMenu = document.createElement('div');
    dropdownMenu.id = 'dropdownMenu';
    dropdownMenu.classList.add('dropdown-content');
    dropdownMenu.style.display = 'none';  // Initially hidden
    dropdownMenu.innerHTML = `
        <button class="add-father-btn">Add Father</button>
        <button class="add-mother-btn">Add Mother</button>
        <button class="add-spouse-btn">Add Spouse</button>
        <button class="add-son-btn">Add Son</button>
        <button class="add-daughter-btn">Add Daughter</button>
    `;
    document.body.appendChild(dropdownMenu);

    //Close the "Dropdown" modal when the user clicks outside of the modal content    
    window.addEventListener('click', function(event) {
        console.log('Checking to hide dropdown');
        console.log(isShowingDropdown);
        if(isShowingDropdown) { 
            console.log('its set to true');
            isShowingDropdown = false;
            return;
        } 
        if(dropdownMenu && !dropdownMenu.contains(event.target)) {
            console.log('isShowingDropdown is '+isShowingDropdown);
            dropdownMenu.style.display = 'none';
        }
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

        document.getElementById('relationship-form-action').value = 'link_relationship';
        document.getElementById('first_names').removeAttribute('required');
        document.getElementById('last_name').removeAttribute('required');
        document.getElementById('additional-fields').style.display = 'none';      
    });

    //When someone selects id='choice-new-individual', hide the id='existing-individuals' div and show the id='additional-fields' div
    document.getElementById('choice-new-individual').addEventListener('click', function() {
        document.getElementById('existing-individuals').style.display = 'none';
        document.getElementById('relationships').style.display = 'block';
        document.getElementById('additional-fields').style.display = 'block';
        document.getElementById('choice-existing-individual').checked = false;

        document.getElementById('relationship-form-action').value = 'add_relationship';
        document.getElementById('first_names').setAttribute('required', '');
        document.getElementById('last_name').setAttribute('required', '');
        document.getElementById('additional-fields').style.display = 'block';              
    });

 

    document.querySelector('.add-new-btn').addEventListener('click', function() {
        openModal('add_individual', dropdownMenu.getAttribute('data-individual-id'), dropdownMenu.getAttribute('data-individual-gender'));
    });
    // Add event listeners to dropdown options
    dropdownMenu.querySelector('.add-father-btn').addEventListener('click', function() {
        openModal('add_father', dropdownMenu.getAttribute('data-individual-id'), dropdownMenu.getAttribute('data-individual-gender'));
    });
    dropdownMenu.querySelector('.add-mother-btn').addEventListener('click', function() {
        openModal('add_mother', dropdownMenu.getAttribute('data-individual-id'), dropdownMenu.getAttribute('data-individual-gender'));
    });
    dropdownMenu.querySelector('.add-spouse-btn').addEventListener('click', function() {
        openModal('add_spouse', dropdownMenu.getAttribute('data-individual-id'), dropdownMenu.getAttribute('data-individual-gender'));
    });
    dropdownMenu.querySelector('.add-son-btn').addEventListener('click', function() {
        openModal('add_son', dropdownMenu.getAttribute('data-individual-id'), dropdownMenu.getAttribute('data-individual-gender'));
    });
    dropdownMenu.querySelector('.add-daughter-btn').addEventListener('click', function() {
        openModal('add_daughter', dropdownMenu.getAttribute('data-individual-id'), dropdownMenu.getAttribute('data-individual-gender'));
    });

    document.getElementById('findindividual_lookup').addEventListener('input', function() {
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

    document.getElementById('findindividual_connect_to').addEventListener('change', function() {
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

function getIndividualId(button) {
    return button.closest('.node').querySelector('.individualid').value;
}

function getIndividualGender(button) {
    return button.closest('.node').querySelector('.individualgender').value;
}

function loadIndividualFromTreeNode(individualId) {
    window.location.href="?to=family/individual&individual_id="+individualId;
}

function editIndividualFromTreeNode(individualId) {
    openEditModal(individualId);
}

function addRelationshipToIndividualFromTreeNode(button) {
    var individualId = getIndividualId(button);
    var gender = getIndividualGender(button);
    showDropdown(button, individualId, gender);
    isShowingDropdown = true;
}

// Function to dynamically show the dropdown relative to the clicked button
function showDropdown(button, individualId, gender) {

    var rect = button.getBoundingClientRect();  // Get the position of the button

    console.log('Attempting to show dropdownMenu');
    console.log(dropdownMenu);
    // Position the dropdown below the button
    console.log('isShowingDropdown is '+isShowingDropdown);

    dropdownMenu.style.left = rect.left + 'px';
    dropdownMenu.style.top = (rect.bottom + window.scrollY) + 'px';
    dropdownMenu.style.display = 'block';  // Show the dropdown
    console.log(dropdownMenu);
    // Store the individual ID for later use
    dropdownMenu.dataset.individualId = individualId;
    dropdownMenu.dataset.individualGender = gender;
    //dropdownMenu.dataset.individualGender = button.dataset.individualGender;
 
}


// Function to open the modal with dynamic form data
function openModal(action, individualId, individualGender) {
    console.log('Opened modal with action:', action, 'for individual ID:', individualId, ' and gender', individualGender);

    document.getElementById('add-relationship-form').action = '?to=family/tree&zoom=' + individualId;

    // Get the modal and form elements for the "Add" form
    var modal = document.getElementById('popupForm');
    var closeButton = document.querySelector('.close-btn');
    //var formActionInput = document.getElementById('form-action');
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
    //hide the dropdownMenu
    dropdownMenu.style.display = 'none';

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
    //formActionInput.value = action;  // Set the action (add_parent, add_spouse, etc.)
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
            document.getElementById('choose-second-parent').style.display = 'block';
            break;
        case 'add_son':
            formActionRelationship.value='child';
            formActionGender.value='male';
            getSpouses(individualId).then(spouses => {
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

// Function to open the "Edit" modal and populate it with the individual's data
async function openEditModal(individualId) {


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

    // Close modal logic for "Edit" modal
    closeModalBtn.addEventListener('click', function() {
        editModal.style.display = 'none';
    });
    //Close the "Edit" modal when the user clicks outside of the modal content    
    window.addEventListener('click', function(event) {
        if (event.target === editModal) {
            editModal.style.display = 'none';
        }
    });      
  
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

        // Display the "Edit" modal
        editModal.style.display = 'block';
    } catch (error) {
        console.error('Error opening edit modal:', error);
    }


}


function findNodeForIndividualId(id) {
    return new Promise((resolve, reject) => {
        // Get all foreignObject elements
        const foreignObjects = document.querySelectorAll('foreignObject');

        // Iterate through each foreignObject
        for (let fo of foreignObjects) {
            // Look for the hidden input inside this foreignObject
            let input = fo.querySelector('input.individualid');
            
            // If the input exists and its value matches the provided id
            if (input && input.value === String(id)) {
                // Find the first div inside the foreignObject and return its id
                let firstDiv = fo.querySelector('div');
                if (firstDiv) {
                    resolve(firstDiv.getAttribute('id'));
                    return;
                }
            }
        }

        // Reject if no match is found
        reject('Node not found');
    });
}

function viewTreeSearch() {
    //alert('Not yet built!');
    //Show the customPrompt
    showCustomPrompt('Enter the name of the individual to search for:', 'Search for Individual', [name], [name], 'searchIndividual');
}


