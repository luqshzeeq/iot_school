<?php
// This file is included within teacher_dashboard.php, so session_start() and db_connection.php
// are assumed to be already loaded and $conn is available.

// Ensure essential variables are available from the parent scope (teacher_dashboard.php)
// These variables are typically passed down implicitly via include, or explicitly if using functions.
// Fallbacks and sanitization for expected variables
$selected_date_str_display = isset($selected_date_str) ? htmlspecialchars($selected_date_str) : date('Y-m-d');
$available_languages_list = is_array($available_languages ?? null) ? $available_languages : [];
$csrf_token = $_SESSION['csrf_token'] ?? ''; // CSRF token should be set in teacher_dashboard.php

// Ensure database connection is successful. This check is crucial.
if (!isset($conn) || !$conn) {
    error_log("Database connection failed in set_language_content.php: " . (mysqli_connect_error() ?? "No connection object."));
    echo "<p class='text-red-600'>Database connection not available. Please check db_connection.php and its inclusion.</p>";
    return; // Exit early if no DB connection
}

// Re-fetch current language for the date picker if a specific date is requested via GET.
// This ensures the dropdown correctly reflects the current setting for the date being viewed.
$current_lang_id = ''; // Initialize
$date_for_current_lang_fetch = isset($_GET['selected_date_str']) ? $_GET['selected_date_str'] : date('Y-m-d');
$sql_current_setting_for_dropdown = "SELECT l.id, l.language_name
                                   FROM teacher_daily_languages tdl
                                   JOIN languages l ON tdl.language_id = l.id
                                   WHERE tdl.setting_date = ?";
$stmt_fetch_lang_for_dropdown = $conn->prepare($sql_current_setting_for_dropdown);
if ($stmt_fetch_lang_for_dropdown) {
    $stmt_fetch_lang_for_dropdown->bind_param("s", $date_for_current_lang_fetch);
    if ($stmt_fetch_lang_for_dropdown->execute()) {
        $result_current_setting_for_dropdown = $stmt_fetch_lang_for_dropdown->get_result();
        if ($row_current_for_dropdown = $result_current_setting_for_dropdown->fetch_assoc()) {
            $current_lang_id = $row_current_for_dropdown['id'];
        }
    } else {
        error_log("Error executing current language fetch in set_language_content.php: " . $stmt_fetch_lang_for_dropdown->error);
    }
    $stmt_fetch_lang_for_dropdown->close();
} else {
    error_log("Error preparing current language fetch statement in set_language_content.php: " . $conn->error);
}


// --- Fetch all Teacher Daily Languages for the history table ---
$teacher_daily_languages_records = [];
$sql_daily_langs_table = "SELECT tdl.setting_date, tdl.language_id, tdl.set_by_teacher_id, l.language_name, u.username AS set_by_username
                          FROM teacher_daily_languages tdl
                          JOIN languages l ON tdl.language_id = l.id
                          LEFT JOIN users u ON tdl.set_by_teacher_id = u.id
                          ORDER BY tdl.setting_date DESC";
$result_daily_langs_table = $conn->query($sql_daily_langs_table);

if ($result_daily_langs_table) {
    while ($row = $result_daily_langs_table->fetch_assoc()) {
        $set_by_username_display = htmlspecialchars($row['set_by_username'] ?? 'Unknown');
        if ($row['set_by_username'] === NULL && $row['set_by_teacher_id'] !== NULL) {
             $set_by_username_display = 'Unknown (ID: ' . htmlspecialchars($row['set_by_teacher_id']) . ')';
        }
        $teacher_daily_languages_records[] = [
            'setting_date' => htmlspecialchars($row['setting_date']),
            'language_id' => htmlspecialchars($row['language_id']),
            'language_name' => htmlspecialchars($row['language_name']),
            'set_by_username' => $set_by_username_display
        ];
    }
} else {
    error_log("Error fetching teacher daily languages for table in set_language_content.php: " . $conn->error);
}

// Unset session flags for success/error specific to language setting after display
// This prevents the modal/banner from reappearing on simple page refresh
$language_set_success = $_SESSION['language_set_success'] ?? false;
$language_error_message = $_SESSION['language_error'] ?? '';
unset($_SESSION['language_set_success'], $_SESSION['language_error']);
?>

<style>
    /*
    ** IMPORTANT: If any of these DataTables-specific or action button base styles are already
    ** defined in your main teacher_dashboard.php's <style> block, it is best practice
    ** to keep them there and remove them from this file to avoid duplication.
    ** These styles are included here for completeness of this file's functionality.
    */

    /* --- Search Input & Icon Styling --- */
    .dataTables_wrapper .dataTables_filter {
        text-align: right; /* Aligns the search input to the right */
        margin-bottom: 1rem;
        position: relative; /* **CRUCIAL**: This makes it the positioning context for the icon */
        display: flex; /* Use flexbox to better align its contents */
        justify-content: flex-end; /* Pushes the input field to the right */
        align-items: center; /* Vertically centers content within this flex container */
    }

    /* Hide the default "Search:" label text generated by DataTables */
    .dataTables_wrapper .dataTables_filter label {
        display: none !important; /* **THIS IS THE KEY LINE TO HIDE THE "SEARCH:" TEXT** */
    }

    /* Style the actual search input field */
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.6rem 1rem; /* Base padding */
        width: 250px; /* Set a desired width for your search bar */
        transition: all 0.2s ease-in-out;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        /* **IMPORTANT**: Increase left padding to create space for the icon */
        padding-left: 2.8rem !important; /* Adjust this value if the icon overlaps text or there's too much space */
        font-size: 0.95rem;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #3B82F6; /* Blue border on focus */
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); /* Blue focus ring */
        outline: none; /* Remove default browser outline */
    }

    /* Style and position the Font Awesome search icon */
    .dataTables_wrapper .dataTables_filter .fas.fa-search {
        position: absolute; /* **CRUCIAL**: Positions the icon relative to its parent (`.dataTables_filter`) */
        left: 1.25rem; /* Distance from the left edge of the input field (adjust if needed) */
        top: 50%; /* Moves the icon down half the height of its parent */
        transform: translateY(-50%); /* Moves the icon up by half its own height, for perfect vertical centering */
        color: #1e3a8a; /* <--- Set to Dark Blue for clear visibility */
        pointer-events: none; /* Allows mouse clicks to pass through to the input field */
        font-size: 1.1rem; /* Size of the icon (adjust for visual balance) */
        z-index: 1; /* Ensures the icon is rendered above the input field */
    }


    /* --- Refined Pagination Styling --- */
    .dataTables_wrapper .dataTables_paginate {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-top: 1.5rem;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        display: flex; align-items: center; justify-content: center;
        min-width: 2.5rem; height: 2.5rem;
        padding: 0 0.75rem; margin: 0 0.25rem;
        border-radius: 0.5rem; border: 1px solid #cbd5e0;
        background-color: #ffffff; color: #4a5568;
        cursor: pointer; transition: all 0.2s ease-in-out;
        font-weight: 500; box-shadow: none;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background-color: #2563eb; border-color: #2563eb; color: #ffffff;
        font-weight: 700; box-shadow: 0 2px 5px rgba(37, 99, 235, 0.2);
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background-color: #1d4ed8; border-color: #1d4ed8;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
        color: #a0aec0;
        cursor: not-allowed;
        background-color: #f0f4f8; border-color: #e2e8f0;
        box-shadow: none;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:not(.current):not(.disabled):hover {
        background-color: #f7fafc;
        border-color: #a0aec0;
        color: #2d3748;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    /* Specific styling for Previous/Next icon buttons */
    .dataTables_wrapper .dataTables_paginate .paginate_button.next,
    .dataTables_wrapper .dataTables_paginate .paginate_button.previous {
        color: #4a5568; /* Default icon color */
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.next:hover,
    .dataTables_wrapper .dataTables_paginate .paginate_button.previous:hover {
        color: #2d3748; /* Darker icon color on hover */
    }

    /* Ensure info text and pagination align well within their common wrapper */
    .dataTables_info {
        flex-grow: 1; text-align: left; color: #718096; font-size: 0.875rem;
    }

    /* The wrapper div for DataTables controls (added by JS) */
    .dataTables_wrapper .flex.justify-between.items-center.mb-4,
    .dataTables_wrapper .flex.justify-between.items-center.mt-4 {
        align-items: flex-end; /* Ensures search input aligns well even with icon */
    }


    /* --- General Table Design Styles (from previous update) --- */
    #dailyLanguageHistoryTable {
        border-collapse: collapse; width: 100%;
    }
    #dailyLanguageHistoryTable th,
    #dailyLanguageHistoryTable td {
        border: 1px solid #e5e7eb; padding: 1rem 1.5rem; font-size: 0.9375rem; line-height: 1.5; vertical-align: middle;
    }
    #dailyLanguageHistoryTable thead th {
        background-color: #f9fafb; color: #6b7280; font-weight: 600; text-transform: uppercase;
        font-size: 0.8125rem; letter-spacing: 0.05em; border-bottom: 2px solid #e5e7eb; border-top: none;
    }
    #dailyLanguageHistoryTable th:first-child, #dailyLanguageHistoryTable td:first-child { border-left: none; }
    #dailyLanguageHistoryTable th:last-child, #dailyLanguageHistoryTable td:last-child { border-right: none; }
    #dailyLanguageHistoryTable tbody tr { transition: background-color 0.2s ease-in-out; }
    #dailyLanguageHistoryTable tbody tr:hover { background-color: #f5f9ff; }
    #dailyLanguageHistoryTable tbody td { color: #4b5563; }

    /* Action button base styles */
    .action-btn {
        @apply p-2 rounded-md transition-colors duration-200;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .edit-btn { color: #6b7280; }
    .edit-btn:hover { background-color: #e0f2f7; color: #2563eb; }
    .delete-btn { color: #9ca3af; }
    .delete-btn:hover { background-color: #fee2e2; color: #ef4444; }

</style>

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

    <div id="deleteConfirmationModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center p-4 z-[9999] opacity-0 invisible transition-all duration-300 ease-out">
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-sm w-full text-center transform scale-95 transition-all duration-300 ease-out">
            <div class="mb-6 flex justify-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.332 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Confirm Deletion</h3>
            <p class="text-gray-600 mb-6" id="deleteModalMessage">Are you sure you want to delete the language setting for <span class="font-semibold text-red-700" id="deleteDateDisplay"></span>?</p>
            <div class="flex justify-center space-x-4">
                <button id="confirmDeleteBtn" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2.5 px-6 rounded-lg transition duration-150 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50">
                    Delete
                </button>
                <button id="cancelDeleteBtn" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2.5 px-6 rounded-lg transition duration-150 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                    Cancel
                </button>
            </div>
        </div>
    </div>


    <div class="card bg-white shadow-md rounded-lg overflow-hidden mb-6">
        <div class="card-header bg-gray-50 px-6 py-4 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800">Set Language of the Day</h3>
        </div>
        <div class="card-body p-6 md:p-8 w-full">
            <form method="POST" action="teacher_dashboard.php?page=set_language" id="dailyLanguageForm">
                <input type="hidden" name="set_language_action" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

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

    <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-800 p-4 rounded-md shadow-sm mb-6" role="alert">
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
                    <li><strong>For any given day, only ONE language can be set per day.</strong> If you set a language for a date that already has one, it will be updated to your new selection.</li>
                    <li>Use the "Actions" column in the history table below to directly edit or delete an existing entry.</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card bg-white shadow-md rounded-lg overflow-hidden">
        <div class="card-header bg-gray-50 px-6 py-4 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800">Daily Language Settings History</h3>
        </div>
        <div class="card-body p-6">
            <?php if (!empty($teacher_daily_languages_records)): ?>
                <div class="overflow-x-auto">
                    <table id="dailyLanguageHistoryTable" class="min-w-full text-left">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Language</th>
                                <th scope="col">Set By Teacher</th>
                                <th scope="col" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacher_daily_languages_records as $record): ?>
                            <tr>
                                <td data-date="<?php echo $record['setting_date']; ?>"><?php echo $record['setting_date']; ?></td>
                                <td data-lang-id="<?php echo $record['language_id']; ?>"><?php echo $record['language_name']; ?></td>
                                <td><?php echo $record['set_by_username']; ?></td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button"
                                            class="edit-language-btn action-btn edit-btn"
                                            data-date="<?php echo $record['setting_date']; ?>"
                                            data-lang-id="<?php echo $record['language_id']; ?>"
                                            title="Edit Language Setting">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button"
                                            class="delete-language-btn action-btn delete-btn ml-2"
                                            data-date="<?php echo $record['setting_date']; ?>"
                                            title="Delete Language Setting">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600">No global daily language settings found yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const successModal = document.getElementById('successModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalOkClose = document.getElementById('modalOkClose');

        const deleteConfirmationModal = document.getElementById('deleteConfirmationModal');
        const deleteDateDisplay = document.getElementById('deleteDateDisplay');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        let dateToDelete = '';

        const settingDatePicker = document.getElementById('setting_date_picker');
        const selectedDateDisplay = document.getElementById('selectedDateDisplay');
        const languageSelectPage = document.getElementById('languageSelectPage');

        // Function to show the modal with custom content
        function showModal(title, message, isSuccess = true) {
            modalTitle.textContent = title;
            modalMessage.textContent = message;

            // Adjust icon and colors based on success/error
            const iconDiv = successModal.querySelector('.w-16.h-16');
            const iconSvg = successModal.querySelector('svg');

            if (isSuccess) {
                iconDiv.classList.remove('bg-red-100');
                iconDiv.classList.add('bg-blue-100');
                iconSvg.classList.remove('text-red-600');
                iconSvg.classList.add('text-blue-600');
                iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>'; // Checkmark
            } else {
                iconDiv.classList.remove('bg-blue-100');
                iconDiv.classList.add('bg-red-100');
                iconSvg.classList.remove('text-blue-600');
                iconSvg.classList.add('text-red-600');
                iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2A9 9 0 111 12a9 9 0 0118 0z"></path>'; // X-mark or alert
            }

            successModal.classList.remove('invisible', 'opacity-0');
            successModal.classList.add('opacity-100');
            successModal.querySelector('div').classList.remove('scale-95');
            successModal.querySelector('div').classList.add('scale-100');
        }

        // Function to hide the modal
        function hideModal(modalElement) {
            modalElement.classList.remove('opacity-100');
            modalElement.classList.add('opacity-0');
            modalElement.querySelector('div').classList.remove('scale-100');
            modalElement.querySelector('div').classList.add('scale-95');

            setTimeout(() => {
                modalElement.classList.add('invisible');
            }, 300);
        }

        // Event listeners for modals
        modalOkClose.addEventListener('click', () => hideModal(successModal));
        successModal.addEventListener('click', function(event) {
            if (event.target === successModal) {
                hideModal(successModal);
            }
        });

        // Trigger success modal if language was set successfully (from POST in teacher_dashboard.php)
        <?php if ($language_set_success): ?>
            showModal(
                "Completed!",
                "You have successfully set the language for <?php echo $selected_date_str_display; ?>.",
                true
            );
        <?php endif; ?>

        // Trigger error modal if language setting failed (from POST in teacher_dashboard.php)
        <?php if (!empty($language_error_message)): ?>
            showModal(
                "Error!",
                "<?php echo htmlspecialchars($language_error_message); ?>",
                false
            );
        <?php endif; ?>


        // Update the displayed date dynamically when the date picker changes
        settingDatePicker.addEventListener('change', function() {
            // Reload the page to correctly populate the language dropdown for the new date
            window.location.href = `teacher_dashboard.php?page=set_language&selected_date_str=${this.value}`;
        });

        // Ensure the initial date display matches the picker if coming from query param
        selectedDateDisplay.textContent = settingDatePicker.value;


        // --- Edit Button Logic ---
        document.querySelectorAll('.edit-language-btn').forEach(button => {
            button.addEventListener('click', function() {
                const date = this.dataset.date;
                const langId = this.dataset.langId;

                settingDatePicker.value = date;
                selectedDateDisplay.textContent = date;
                languageSelectPage.value = langId;

                // Scroll to the top of the form for better UX
                document.getElementById('dailyLanguageForm').scrollIntoView({ behavior: 'smooth', block: 'start' });

                // Optionally, highlight the form to indicate it's in "edit" mode
                const formCard = document.querySelector('#dailyLanguageForm').closest('.card');
                formCard.classList.add('ring-4', 'ring-blue-200');
                setTimeout(() => {
                    formCard.classList.remove('ring-4', 'ring-blue-200');
                }, 1500);
            });
        });

        // --- Delete Button Logic ---
        document.querySelectorAll('.delete-language-btn').forEach(button => {
            button.addEventListener('click', function() {
                dateToDelete = this.dataset.date;
                deleteDateDisplay.textContent = dateToDelete;
                deleteConfirmationModal.classList.remove('invisible', 'opacity-0');
                deleteConfirmationModal.classList.add('opacity-100');
                deleteConfirmationModal.querySelector('div').classList.remove('scale-95');
                deleteConfirmationModal.querySelector('div').classList.add('scale-100');
            });
        });

        cancelDeleteBtn.addEventListener('click', () => hideModal(deleteConfirmationModal));

        confirmDeleteBtn.addEventListener('click', function() {
            hideModal(deleteConfirmationModal);

            fetch('process_language_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'delete',
                    'setting_date': dateToDelete,
                    'csrf_token': '<?php echo htmlspecialchars($csrf_token); ?>'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => { throw new Error('Server response was not JSON: ' + text); });
                }
            })
            .then(data => {
                if (data.success) {
                    showModal("Deleted!", data.message, true);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showModal("Error!", data.message, false);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showModal("Error!", "An unexpected error occurred. Please try again. (Details in console)", false);
            });
        });


        // --- Initialize DataTable for Teacher Daily Languages Table ---
        if (document.getElementById('dailyLanguageHistoryTable')) {
            $('#dailyLanguageHistoryTable').DataTable({
                "paging": true,
                "searching": true,
                "lengthChange": true,
                "lengthMenu": [10, 25, 50, 100],
                "ordering": true,
                "info": true,
                "language": {
                    // This "Search:" text will be visually hidden by CSS now
                    "search": "Search:",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoFiltered": "(filtered from _MAX_ total entries)",
                    "infoEmpty": "Showing 0 to 0 of 0 entries",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    }
                },
                "order": [[0, "desc"]]
            });

            // --- Custom styling & icon application for DataTables elements (apply after DataTable init) ---
            // Search input field styling
            $('.dataTables_filter input').addClass('border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500').attr('placeholder', 'Search...');

            // Remove default DataTables "Search:" label text and prepend the icon
            // The icon's styling and positioning are handled by CSS.
            $('.dataTables_filter label').remove(); // Directly remove the label element
            $('.dataTables_filter').prepend('<i class="fas fa-search"></i>'); // Prepend the Font Awesome icon

            // Pagination buttons - The main styling is now done via CSS
            // We only need to ensure the correct icons are appended for Previous/Next.
            $('.dataTables_paginate .paginate_button.next').html('<i class="fas fa-chevron-right"></i>');
            $('.dataTables_paginate .paginate_button.previous').html('<i class="fas fa-chevron-left"></i>');

            // Adjust layout of DataTables controls (important for left/right alignment)
            // This ensures "Show X entries" and "Search" are on one line, and "Info" and "Pagination" are on another.
            $('.dataTables_length, .dataTables_filter').wrapAll('<div class="flex justify-between items-end mb-4"></div>');
            $('.dataTables_info, .dataTables_paginate').wrapAll('<div class="flex justify-between items-center mt-4"></div>');
        }
    });
</script>