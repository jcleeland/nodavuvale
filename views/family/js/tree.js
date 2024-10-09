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

    var toggleAkaButton = document.getElementById('toggle-aka');
    var akaDiv = document.getElementById('aka');

    toggleAkaButton.addEventListener('click', function() {
        if (akaDiv.style.display === 'none' || akaDiv.style.display === '') {
            akaDiv.style.display = 'block';
        } else {
            akaDiv.style.display = 'none';
        }
    });    

    // Function to dynamically show the dropdown relative to the clicked button
    function showDropdown(button) {
        var rect = button.getBoundingClientRect();  // Get the position of the button

        // Position the dropdown below the button
        dropdownMenu.style.left = rect.left + 'px';
        dropdownMenu.style.top = (rect.bottom + window.scrollY) + 'px';
        dropdownMenu.style.display = 'block';  // Show the dropdown

        // Store the individual ID for later use
        dropdownMenu.dataset.individualId = button.dataset.individualId;
        dropdownMenu.dataset.individualGender = button.dataset.individualGender;
    }

    // Function to hide the dropdown
    function hideDropdowns() {
        dropdownMenu.style.display = 'none';
    }

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

    // Add event listeners to the "+" buttons in each node
    document.querySelectorAll('.dropdown-btn').forEach(function(button) {
        button.addEventListener('click', function(event) {
            event.stopPropagation();  // Prevent click from closing the dropdown immediately
            hideDropdowns();  // Hide other open dropdowns
            showDropdown(this);  // Show the clicked dropdown
        });
    });

    // Hide all dropdowns when clicking outside
    window.addEventListener('click', function(event) {
        hideDropdowns();
    });

    // Get the modal and form elements for the "Add" form
    var modal = document.getElementById('popupForm');
    var closeButton = document.querySelector('.close-btn');
    var formActionInput = document.getElementById('form-action');
    var formActionGender = document.getElementById('gender');
    var formActionRelationship = document.getElementById('relationship');
    var relatedIndividualInput = document.getElementById('related-individual');

    // Function to open the modal with dynamic form data
    function openModal(action) {
        var individualId = dropdownMenu.dataset.individualId;
        var individualGender= dropdownMenu.dataset.individualGender;
        console.log(individualGender);
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
        document.getElementById('choose-second-parent').style.display = 'block';
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
                break;
            case 'add_son':
                formActionRelationship.value='child';
                formActionGender.value='male';
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

    document.querySelector('.add-new-btn').addEventListener('click', function() {
        openModal('add_individual');
    });
    // Add event listeners to dropdown options
    dropdownMenu.querySelector('.add-father-btn').addEventListener('click', function() {
        openModal('add_father');
    });
    dropdownMenu.querySelector('.add-mother-btn').addEventListener('click', function() {
        openModal('add_mother');
    });
    dropdownMenu.querySelector('.add-spouse-btn').addEventListener('click', function() {
        openModal('add_spouse');
    });
    dropdownMenu.querySelector('.add-son-btn').addEventListener('click', function() {
        openModal('add_son');
    });
    dropdownMenu.querySelector('.add-daughter-btn').addEventListener('click', function() {
        openModal('add_daughter');
    });

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

    // ------------------- Handling the "Edit" button and modal -------------------

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

    window.onclick = function(event) {
        if (event.target === editModal) {
            editModal.style.display = 'none';
        }
    };





});

// Initialize the draggable modal
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.querySelector('.modal-content');
    const header = document.querySelector('.modal-header');
    makeModalDraggable(modal, header);
});    
