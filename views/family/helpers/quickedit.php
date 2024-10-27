    <!-- Edit Modal -->
    <div id="editModal" class="modal">

        <div class="modal-content">
            <div class="modal-header">
                <span class="edit-close-btn">&times;</span>
                <h2>Quick Edit <span id='individual_name_display'>Individual</span></h2>
            </div>
            <div class="modal-body">
                <form id="editForm" action="<?= $_SERVER['REQUEST_URI'] ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_individual">
                    <input type="hidden" id="edit-individual-id" name="individual_id">
                    <input type="hidden" id="root_id" name="root_id" value="<?= $rootId; ?>">
                    
                    <div class="mb-4">
                        <label for="first_name" class="block">First Name</label>
                        <div class="flex items-center mt-1">
                                <input type="text" id="edit-first-names" name="first_names" class="flex-grow px-4 py-2 border rounded-lg" required>
                                <button type="button" title="Add other names for this person" id="edit-toggle-aka" class="ml-2 px-2 py-1 bg-gray-300 rounded text-xs">AKA</button>
                            </div>                    
                    </div>
                    <div id="edit-aka" class="mb-4" style="display: none">
                            <label for="aka_names" class="block text-gray-700">Other name(s) used</label>
                            <input type="text" id="edit-aka-names" name="aka_names" class="w-full px-4 py-2 border rounded-lg">
                    </div>                
                    <div class="mb-4">
                        <label for="last_name" class="block">Last Name</label>
                        <input type="text" id="edit-last-name" name="last_name" class="w-full px-4 py-2 border rounded-lg" required>
                    </div>




                    <!-- Birth -->
                    <div class="mb-4 grid grid-cols-4 gap-4">
                        <div>
                            <label for="birth_prefix" class="block text-gray-700">Birth</label>
                            <select id="edit-birth-prefix" name="birth_prefix" class="w-full px-4 py-2 border rounded-lg">
                                <option value=""></option>
                                <option value="exactly">Exactly</option>
                                <option value="about">About</option>
                                <option value="after">After</option>
                                <option value="before">Before</option>
                            </select>
                        </div>
                        <div>
                            <label for="birth_year" class="block text-gray-700">Year</label>
                            <input type="text" id="edit-birth-year" name="birth_year" class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label for="birth_month" class="block text-gray-700">Month</label>
                            <select id="edit-birth-month" name="birth_month" class="w-full px-4 py-2 border rounded-lg">
                                <option value=""></option>
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        <div>
                            <label for="birth_date" class="block text-gray-700">Date</label>
                            <input type="text" id="edit-birth-date" name="birth_date" class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>
                    <div class="mb-4 grid grid-cols-4 gap-4">
                        <div>
                            <label for="death_prefix" class="block text-gray-700">Death</label>
                            <select id="edit-death-prefix" name="death_prefix" class="w-full px-4 py-2 border rounded-lg">
                                <option value=""></option>
                                <option value="exactly">Exactly</option>
                                <option value="about">About</option>
                                <option value="after">After</option>
                                <option value="before">Before</option>
                            </select>
                        </div>
                        <div>
                            <label for="death_year" class="block text-gray-700">Year</label>
                            <input type="text" id="edit-death-year" name="death_year" class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label for="death_month" class="block text-gray-700">Month</label>
                            <select id="edit-death-month" name="death_month" class="w-full px-4 py-2 border rounded-lg">
                                <option value=""></option>
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        <div>
                            <label for="death_date" class="block text-gray-700">Date</label>
                            <input type="text" id="edit-death-date" name="death_date" class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>

                    <!-- Deceased -->
                    <div class="mb-4 flex items-center">
                    <input type="checkbox" class="mr-2" id="edit-is_deceased" name="is_deceased" value="1">
                    <label for="edit-is_deceased" class="text-gray-700">Deceased</label>
                    </div>

                    <!-- Gender -->
                    <div class="mb-4">
                        <label for="gender" class="block text-gray-700">Gender</label>
                        <select id="edit-gender" name="gender" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">Select gender...</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="bg-green-500 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>