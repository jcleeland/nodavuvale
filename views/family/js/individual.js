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

function uploadImage() {
    var fileInput = document.getElementById('fileUpload');
    var file = fileInput.files[0];
    if (file) {
        // Handle the file upload logic here
        console.log('File selected:', file.name);
        // You can use AJAX to upload the file to the server
    }
}