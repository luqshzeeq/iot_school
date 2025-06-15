<?php

// Fallbacks and sanitization for expected variables
$selected_date_str_display = isset($selected_date_str) ? htmlspecialchars($selected_date_str) : date('Y-m-d');
$available_languages_list = is_array($available_languages ?? null) ? $available_languages : [];
$current_lang_id = $current_language_for_selected_date_id ?? '';

?>

<div>
    <div id="successModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center p-4 z-[9999] opacity-0 invisible transition-all duration-300 ease-out">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full text-center transform scale-95 transition-all duration-300 ease-out">
            <div class="mb-6 flex justify-center">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>

            <h3 class="text-2xl font-bold text-gray-800 mb-2" id="modalTitle">Completed!</h3>

            <p class="text-gray-600 mb-6" id="modalMessage">You have successfully set the language.</p>

            <div class="flex justify-center space-x-4">
                <button id="modalOkClose" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-lg transition duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                    Close
                </button>
            </div>
        </div>
    </div>

    <div class="card bg-white shadow-md rounded-lg overflow-hidden mb-6"> <div class="card-header bg-gray-50 px-6 py-4 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800">Set Language of the Day</h3>
        </div>
        <div class="card-body p-6 md:p-8 w-full">
            <form method="POST" action="teacher_dashboard.php?page=set_language" id="dailyLanguageForm">
                <input type="hidden" name="set_language_action" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

                <div class="mb-6">
                    <label for="setting_date_picker" class="block text-sm font-medium text-gray-700 mb-2">Select Date:</label>
                    <input type="date" id="setting_date_picker" name="setting_date"
                            value="<?php echo $selected_date_str_display; ?>"
                            class="w-full bg-white border border-gray-300 text-gray-900 px-4 py-2.5 rounded-lg shadow-sm
                                   focus:ring-blue-500 focus:border-blue-500 text-base"
                            required>
                </div>

                <div class="mb-6">
                    <label for="languageSelectPage" class="block text-sm font-medium text-gray-700 mb-2">
                        Select Language for <span id="selectedDateDisplay" class="font-semibold text-blue-700"><?php echo $selected_date_str_display; ?></span>:
                    </label>
                    <select id="languageSelectPage" name="language_id"
                            class="w-full bg-white border border-gray-300 text-gray-900 px-4 py-2.5 rounded-lg shadow-sm
                                   focus:ring-blue-500 focus:border-blue-500 text-base"
                            required
                            <?php echo empty($available_languages_list) ? 'disabled' : ''; ?>
                    >
                        <option value="">-- Choose a Language --</option>
                        <?php if (!empty($available_languages_list)): ?>
                            <?php foreach ($available_languages_list as $lang): ?>
                                <option value="<?php echo htmlspecialchars($lang['id']); ?>"
                                        <?php echo ((string)$current_lang_id === (string)$lang['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lang['language_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No languages available. Please add them via Admin panel.</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($available_languages_list)): ?>
                        <p class="mt-2 text-sm text-red-600">
                            No languages are configured. Please add languages in your admin settings.
                        </p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg text-base
                                             transition duration-150 shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50
                                             flex items-center justify-center">
                    <i class="fas fa-save mr-2"></i>Select
                </button>
            </form>
        </div>
    </div>

    <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-800 p-4 rounded-md shadow-sm" role="alert">
        <div class="flex">
            <div class="py-1">
                <svg class="h-6 w-6 text-blue-500 mr-4" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                </svg>
            </div>
            <div>
                <p class="font-bold text-lg mb-1">Important Notice:</p>
                <ul class="list-disc list-inside text-sm space-y-1">
                    <li>Use this form to set the language for a specific day.</li>
                    <li>You can select any past, current, or future date.</li>
                    <li><strong>For any given day, each teacher can set only ONE language.</strong> If you set a language for a date that already has one, it will be updated to your new selection.</li>
                    <li>The currently selected language for the chosen date is displayed below the "Select Language for..." label.</li>
                </ul>
            </div>
        </div>
    </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const successModal = document.getElementById('successModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalOkClose = document.getElementById('modalOkClose');
        const settingDatePicker = document.getElementById('setting_date_picker');
        const selectedDateDisplay = document.getElementById('selectedDateDisplay');

        // Function to show the modal with custom content
        function showModal(title, message) {
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            successModal.classList.remove('invisible', 'opacity-0');
            successModal.classList.add('opacity-100'); // Fade in overlay

            // Animate the modal content itself
            successModal.querySelector('div').classList.remove('scale-95');
            successModal.querySelector('div').classList.add('scale-100');
        }

        // Function to hide the modal
        function hideModal() {
            successModal.classList.remove('opacity-100');
            successModal.classList.add('opacity-0'); // Fade out overlay

            // Animate the modal content itself
            successModal.querySelector('div').classList.remove('scale-100');
            successModal.querySelector('div').classList.add('scale-95');

            // After transition, make it truly invisible
            setTimeout(() => {
                successModal.classList.add('invisible');
            }, 300); // Matches transition duration
        }

        // Event listener for the "Ok, Close" button
        modalOkClose.addEventListener('click', hideModal);

        // Optional: Hide modal if clicking outside the content (on the overlay)
        successModal.addEventListener('click', function(event) {
            if (event.target === successModal) {
                hideModal();
            }
        });

        // Trigger modal if language was set successfully
        <?php if (isset($_SESSION['language_set_success']) && $_SESSION['language_set_success']): ?>
            showModal(
                "Completed!",
                "You have successfully set the language for <?php echo $selected_date_str_display; ?>."
            );
            <?php unset($_SESSION['language_set_success']); // Reset session flag ?>
        <?php endif; ?>

        // Update the displayed date dynamically when the date picker changes
        settingDatePicker.addEventListener('change', function() {
            selectedDateDisplay.textContent = this.value;
            // Update the form action to reflect the newly selected date for proper context
            const form = document.getElementById('dailyLanguageForm');
            const url = new URL(form.action);
            url.searchParams.set('setting_date', this.value);
            form.action = url.toString();
        });

        // Ensure the initial date display matches the picker if coming from query param
        selectedDateDisplay.textContent = settingDatePicker.value;
    });
</script>