<!-- "Add new relationship" Modal Popup Form -->
<div id="popupForm" class="modal" style="display: none;">
        <div class="modal-content">
            <div id="modal-header" class="modal-header">
                <span class="close-btn">&times;</span>
                <h2 id="modal-title">Add New Relationship <span id='adding_relationship_to'></span></h2>
            </div>
            <div class="modal-body">
                <form id="dynamic-form" action="?to=family/tree" method="POST">
                    <input type="hidden" name="action" value="" id="form-action">
                    <input type="hidden" name="related_individual" value="" id="related-individual">
                    <input type="hidden" id="root_id" name="root_id" value="<?= $rootId; ?>">

                    <div id="add-relationship-choice" class="mb-4 text-sm text-center mt-2">
                    Connect to 
                        <input type="radio" id="choice-existing-individual" name="choice-existing-individual" value="existing">
                        <label for="choice-existing-individual" class="mr-3">Existing Individual</label>
                        <input type="radio" id="choice-new-individual" name="choice-new-individual" value="new">
                        <label for="choice-new-individual" class="mr-3">New Individual</label>
                    </div>

                    <!-- Lookup field to select an existing individual -->
                    <div id="existing-individuals" class="mb-4" style='display: none'>
                        <label for="lookup" class="block text-gray-700">Connect to Existing Individual</label>
                        <input type="text" id="lookup" name="lookup" class="w-full px-4 py-2 border rounded-lg" placeholder="Type to search...">
                        <select id="connect_to" name="connect_to" class="w-full px-4 py-2 border rounded-lg mt-2" size="5" style="display: none;">
                            <option value="">Select someone...</option>
                            <?php foreach ($individuals as $indi): ?>
                                <option value="<?php echo $indi['id']; ?>">
                                    <?php echo $indi['first_names'] . ' ' . $indi['last_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- New Individual Form -->
                    <div id="additional-fields" style='display: none'>
                        <div class="mb-4">
                            <label for="first_names" class="block text-gray-700 mr-2">First Name(s)</label>
                            <div class="flex items-center">
                                <input type="text" id="first_names" name="first_names" class="flex-grow px-4 py-2 border rounded-lg" required>
                                <button type="button" title="Add other names for this person" id="toggle-aka" class="ml-2 px-2 py-1 bg-gray-300 rounded text-xs">AKA</button>
                            </div>
                        </div>
                        <div id="aka" class="mb-4" style="display: none">
                            <label for="aka_names" class="block text-gray-700">Other name(s) used</label>
                            <input type="text" id="aka_names" name="aka_names" class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div class="mb-4">
                            <label for="last_name" class="block text-gray-700">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="w-full px-4 py-2 border rounded-lg" required>
                        </div>

                        <!-- Birth -->
                        <div class="mb-4 grid grid-cols-4 gap-4">
                            <div>
                                <label for="birth_prefix" class="block text-gray-700">Birth</label>
                                <select id="birth_prefix" name="birth_prefix" class="w-full px-4 py-2 border rounded-lg">
                                    <option value=""></option>
                                    <option value="exactly">Exactly</option>
                                    <option value="about">About</option>
                                    <option value="after">After</option>
                                    <option value="before">Before</option>
                                </select>
                            </div>
                            <div>
                                <label for="birth_year" class="block text-gray-700">Year</label>
                                <input type="text" id="birth_year" name="birth_year" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                            <div>
                                <label for="birth_month" class="block text-gray-700">Month</label>
                                <select id="birth_month" name="birth_month" class="w-full px-4 py-2 border rounded-lg">
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
                                <input type="text" id="birth_date" name="birth_date" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                        </div>
                        <div class="mb-4 grid grid-cols-4 gap-4">
                            <div>
                                <label for="death_prefix" class="block text-gray-700">Death</label>
                                <select id="death_prefix" name="death_prefix" class="w-full px-4 py-2 border rounded-lg">
                                    <option value=""></option>
                                    <option value="exactly">Exactly</option>
                                    <option value="about">About</option>
                                    <option value="after">After</option>
                                    <option value="before">Before</option>
                                </select>
                            </div>
                            <div>
                                <label for="death_year" class="block text-gray-700">Year</label>
                                <input type="text" id="death_year" name="death_year" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                            <div>
                                <label for="death_month" class="block text-gray-700">Month</label>
                                <select id="death_month" name="death_month" class="w-full px-4 py-2 border rounded-lg">
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
                                <input type="text" id="death_date" name="death_date" class="w-full px-4 py-2 border rounded-lg">
                            </div>
                        </div>

                        <!-- Gender -->
                        <div class="mb-4">
                            <label for="gender" class="block text-gray-700">Gender</label>
                            <select id="gender" name="gender" class="w-full px-4 py-2 border rounded-lg">
                                <option value="">Select gender...</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                    </div>

                    <!-- Relationship -->
                    <div id="relationships" class="mb-4">
                        <div id="primary-relationship">
                            <label for="relationship" class="block text-gray-700">Relationship to Selected Individual</label>
                            <select id="relationship" name="relationship" class="w-full px-4 py-2 border rounded-lg">
                                <option value="">Select Relationship...</option>
                                <option value='parent'>Parent</option>
                                <option value='child'>Child</option>
                                <option value='spouse'>Spouse</option>
                            </select>
                        </div>
                        <div id="choose-second-parent" style="display: none">
                            <label for="second-parent" class="block text-gray-700">Other parent</label>
                            <select id="second-parent" name="second-parent" class="w-full px-4 py-2 border rounded-lg">
                                <option value="">Not known..</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

