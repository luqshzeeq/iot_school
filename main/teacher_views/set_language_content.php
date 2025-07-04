<?php
// This file is included within teacher_dashboard.php, so session_start() and db_connection.php
// are assumed to be already loaded and $conn is available.

// Ensure essential variables are available from the parent scope (teacher_dashboard.php)
// These variables are typically passed down implicitly via include, or explicitly if using functions.
// Fallbacks and sanitization for expected variables
$selected_date_str_display = isset($selected_date_str) ? htmlspecialchars($selected_date_str) : date('Y-m-d');
$available_languages_list = is_array($available_languages ?? null) ? $available_languages : [];
$csrf_token = $_SESSION['csrf_token'] ?? ''; // CSRF token should be set in teacher_dashboard.php
$teacher_id = $_SESSION['user_id'] ?? 0; // Ensure teacher_id is available for requests

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

// Get messages from language request process (these are handled by AJAX, not page reload)
$request_success_message = $_SESSION['language_request_success'] ?? '';
$request_error_message = $_SESSION['language_request_error'] ?? '';
unset($_SESSION['language_request_success'], $_SESSION['language_request_error']);

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

    /* New modal styles for request language */
    /* NOTE: These styles are specific to the request language modal and are kept here.
       The delete and success modals will now be handled by SweetAlert2, so their custom styles
       is no longer needed in this file (if they were here previously). */
    .request-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    .request-modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    .request-modal-box {
        background-color: white;
        padding: 2rem;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        text-align: center;
        max-width: 450px; /* Slightly wider for forms */
        width: 90%;
        transform: translateY(-20px);
        opacity: 0;
        transition: transform 0.3s ease, opacity 0.3s ease;
    }
    .request-modal-overlay.active .request-modal-box {
        transform: translateY(0);
        opacity: 1;
    }
    .request-modal-box .icon-wrapper {
        background-color: #e0f2f7;
        color: #2980b9;
        border-radius: 9999px;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
    }
    .request-modal-box .icon-wrapper i {
        font-size: 2rem;
    }
</style>

<div>
    <!-- The old successModal and deleteConfirmationModal HTML are removed here.
         SweetAlert2 will dynamically create these modals. -->

    <div id="requestLanguageModal" class="request-modal-overlay">
        <div class="request-modal-box">
            <div class="icon-wrapper">
                <i class="fas fa-plus-circle"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-4">Request New Language</h3>
            <p class="text-gray-600 mb-6"></p> <form id="requestLanguageForm" class="space-y-4">
                <input type="hidden" name="action" value="request_language">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="requested_by" value="<?php echo htmlspecialchars($teacher_id); ?>">
                <div>
                    <label for="new_language_name_input" class="block text-sm font-medium text-gray-700 text-left mb-1">Language Name:</label>
                    <input type="text" id="new_language_name_input" name="language_name"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="e.g., Japanese, French" required>
                </div>
                <div class="flex justify-center space-x-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-lg transition duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                        Submit
                    </button>
                    <button type="button" id="cancelRequestLanguageBtn" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2.5 px-6 rounded-lg transition duration-150 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                        Cancel
                    </button>
                </div>
            </form>
            <button type="button" id="closeRequestLanguageModalBtn" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2.5 px-6 rounded-lg transition duration-150 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50 mt-4 hidden">
                Close
            </button>
        </div>
    </div>

    <div class="card bg-white shadow-md rounded-lg overflow-hidden mb-6">
        <div class="card-header bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-xl font-semibold text-gray-800">Set Language of the Day</h3>
            <button id="openRequestLanguageModalBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm flex items-center">
                <i class="fas fa-plus-circle mr-2"></i>Request New Language
            </button>
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
                            <option value="" disabled>No languages available. Please request one!</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($available_languages_list)): ?>
                        <p class="mt-2 text-sm text-red-600">
                            No languages are configured. Please request a new language using the button above.
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
        // ... (Existing variable declarations and event listeners for date picker, edit button, etc. remain the same) ...

        const settingDatePicker = document.getElementById('setting_date_picker');
        const selectedDateDisplay = document.getElementById('selectedDateDisplay');
        const languageSelectPage = document.getElementById('languageSelectPage');

        // Request Language Modal elements (still using custom modal)
        const openRequestLanguageModalBtn = document.getElementById('openRequestLanguageModalBtn');
        const requestLanguageModal = document.getElementById('requestLanguageModal');
        const cancelRequestLanguageBtn = document.getElementById('cancelRequestLanguageBtn');
        const requestLanguageForm = document.getElementById('requestLanguageForm');
        const newLanguageNameInput = document.getElementById('new_language_name_input');
        const closeRequestLanguageModalBtn = document.getElementById('closeRequestLanguageModalBtn'); // New close button for request modal


        // Function to show the request language modal with custom content (kept for this modal only)
        function showRequestLanguageModal(title, message, isSuccess = true) {
            const modalBox = requestLanguageModal.querySelector('.request-modal-box');
            const modalBoxTitle = modalBox.querySelector('h3');
            const modalBoxMessage = modalBox.querySelector('p:not([id])'); // Target the message <p>

            if (modalBoxTitle) modalBoxTitle.textContent = title;
            if (modalBoxMessage) modalBoxMessage.textContent = message;

            const iconI = modalBox.querySelector('.icon-wrapper i.fas');
            const iconDiv = iconI.closest('.icon-wrapper');
            iconDiv.classList.remove('bg-red-100', 'bg-blue-100', 'bg-green-100');
            iconI.classList.remove('fa-plus-circle', 'fa-check-circle', 'fa-times-circle');
            iconI.classList.remove('text-blue-600', 'text-green-600', 'text-red-600');

            if (isSuccess) {
                iconDiv.classList.add('bg-green-100');
                iconI.classList.add('fa-check-circle', 'text-green-600');
            } else {
                iconDiv.classList.add('bg-red-100');
                iconI.classList.add('fa-times-circle', 'text-red-600');
            }
            
            requestLanguageModal.classList.remove('invisible', 'opacity-0');
            requestLanguageModal.classList.add('active');
        }

        // Function to hide the request language modal
        function hideRequestLanguageModal() {
            requestLanguageModal.classList.remove('active');
            setTimeout(() => {
                requestLanguageModal.classList.add('invisible', 'opacity-0');

                // Reset modal content to its original state
                const modalBox = requestLanguageModal.querySelector('.request-modal-box');
                const iconI = modalBox.querySelector('.icon-wrapper i.fas');
                const titleH3 = modalBox.querySelector('h3');
                const messageP = modalBox.querySelector('p:not([id])');

                if (iconI) {
                    iconI.classList.remove('fa-check-circle', 'fa-times-circle', 'text-green-600', 'text-red-600');
                    iconI.classList.add('fa-plus-circle', 'text-blue-600'); // Default icon and color
                    iconI.closest('.icon-wrapper').classList.remove('bg-green-100', 'bg-red-100');
                    iconI.closest('.icon-wrapper').classList.add('bg-blue-100');
                }
                if (titleH3) titleH3.textContent = "Request New Language";
                if (messageP) messageP.textContent = "";
                if (requestLanguageForm) requestLanguageForm.style.display = 'block';
                if (requestLanguageForm) requestLanguageForm.querySelector('button[type="submit"]').style.display = 'block';
                if (requestLanguageForm) requestLanguageForm.querySelector('#cancelRequestLanguageBtn').style.display = 'block';
                if (closeRequestLanguageModalBtn) closeRequestLanguageModalBtn.classList.add('hidden');
            }, 300);
        }

        // Trigger SweetAlert for success message if language was set successfully
        <?php if ($language_set_success): ?>
            Swal.fire({
                target: '#main-content-area', // <-- ADD THIS
                heightAuto: false,            // <-- AND ADD THIS
                title: "Completed!",
                text: "You have successfully set the language for <?php echo $selected_date_str_display; ?>.",
                icon: "success",
                confirmButtonColor: '#2563eb', // Blue button for success
                onBeforeOpen: () => handleSwalLayoutShift()
            });
            <?php unset($_SESSION['language_set_success']); // UNSET AFTER USE ?>
        <?php endif; ?>

        // Trigger SweetAlert for error message if language setting failed
        <?php if (!empty($language_error)): ?>
            Swal.fire({
                target: '#main-content-area', // <-- ADD THIS
                heightAuto: false,            // <-- AND ADD THIS
                title: "Error!",
                text: "<?php echo htmlspecialchars($language_error); ?>",
                icon: "error",
                confirmButtonColor: '#ef4444', // Red button for error
                onBeforeOpen: () => handleSwalLayoutShift()
            });
            <?php unset($_SESSION['language_error']); // UNSET AFTER USE ?>
        <?php endif; ?>

        // Update the displayed date dynamically when the date picker changes
        settingDatePicker.addEventListener('change', function() {
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

                document.getElementById('dailyLanguageForm').scrollIntoView({ behavior: 'smooth', block: 'start' });

                const formCard = document.querySelector('#dailyLanguageForm').closest('.card');
                formCard.classList.add('ring-4', 'ring-blue-200');
                setTimeout(() => {
                    formCard.classList.remove('ring-4', 'ring-blue-200');
                }, 1500);
            });
        });

        // --- Delete Button Logic with SweetAlert2 ---
        document.querySelectorAll('.delete-language-btn').forEach(button => {
            button.addEventListener('click', function() {
                const dateToDelete = this.dataset.date; // Get the date from the button's data-date attribute

                Swal.fire({
                    target: '#main-content-area', // <-- ADD THIS
                    heightAuto: false,            // <-- AND ADD THIS
                    title: 'Are you sure?',
                    html: `You are about to delete the language setting for <span class="font-semibold text-red-700">${dateToDelete}</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626', // Tailwind red-600
                    cancelButtonColor: '#6b7280', // Tailwind gray-500
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true, // Puts "Cancel" on the left, "Delete" on the right
                    onBeforeOpen: () => handleSwalLayoutShift()
                }).then((result) => {
                    if (result.isConfirmed) {
                        // User confirmed, proceed with deletion via Fetch API
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
                                // If the response is not OK, try to read it as text to get a more specific error
                                return response.text().then(text => {
                                    throw new Error(`HTTP error! status: ${response.status}. Server response: ${text}`);
                                });
                            }
                            // Check if the content type is JSON before parsing
                            const contentType = response.headers.get("content-type");
                            if (contentType && contentType.indexOf("application/json") !== -1) {
                                return response.json();
                            } else {
                                // If not JSON, assume a non-json success and handle it
                                return {}; // Return empty object or handle as needed
                            }
                        })
                        .then(data => {
                            if (data.success) {
                                Swal.fire(
                                    'Deleted!',
                                    data.message,
                                    'success'
                                ).then(() => {
                                    window.location.reload(); // Reload page after successful deletion
                                });
                            } else {
                                Swal.fire(
                                    'Error!',
                                    data.message || 'An error occurred during deletion.',
                                    'error'
                                );
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire(
                                'Error!',
                                'An unexpected error occurred. Please try again.',
                                'error'
                            );
                        });
                    }
                });
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
            $('.dataTables_filter input').addClass('border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500').attr('placeholder', 'Search...');
            $('.dataTables_filter label').remove();
            $('.dataTables_filter').prepend('<i class="fas fa-search"></i>');

            $('.dataTables_paginate .paginate_button.next').html('<i class="fas fa-chevron-right"></i>');
            $('.dataTables_paginate .paginate_button.previous').html('<i class="fas fa-chevron-left"></i>');

            $('.dataTables_length, .dataTables_filter').wrapAll('<div class="flex justify-between items-end mb-4"></div>');
            $('.dataTables_info, .dataTables_paginate').wrapAll('<div class="flex justify-between items-center mt-4"></div>');
        }

        // NEW: Request Language Modal event listeners
        openRequestLanguageModalBtn.addEventListener('click', () => {
            showRequestLanguageModal("Request New Language", "Enter the name of the new language you wish to request.", true); // Set default for this modal
            requestLanguageForm.style.display = 'block'; // Ensure form is visible
            requestLanguageForm.querySelector('button[type="submit"]').style.display = 'block'; // Show submit
            requestLanguageForm.querySelector('#cancelRequestLanguageBtn').style.display = 'block'; // Show cancel
            closeRequestLanguageModalBtn.classList.add('hidden'); // Hide the standalone close button initially
        });

        cancelRequestLanguageBtn.addEventListener('click', () => {
            hideRequestLanguageModal(); // Hide modal
            requestLanguageForm.reset(); // Clear form
        });

        // Add event listener for the new standalone close button
        closeRequestLanguageModalBtn.addEventListener('click', () => {
            hideRequestLanguageModal();
            requestLanguageForm.reset();
        });


        requestLanguageModal.addEventListener('click', (e) => {
            // Only hide if clicking directly on the overlay, not on the modal box itself
            if (e.target === requestLanguageModal) {
                hideRequestLanguageModal();
                requestLanguageForm.reset();
            }
        });

        requestLanguageForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const formData = new URLSearchParams(new FormData(this));

            fetch('process_language_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json(); // Expect JSON response
            })
            .then(data => {
                // If the request was successful
                if (data.success) {
                    showRequestLanguageModal("Request Sent!", data.message, true);
                    newLanguageNameInput.value = ''; // Clear input after successful submission
                    requestLanguageForm.style.display = 'none'; // Hide form after successful submission
                    // Show the new standalone close button
                    closeRequestLanguageModalBtn.classList.remove('hidden');
                } else {
                    // If the request failed (e.g., duplicate pending)
                    showRequestLanguageModal("Request Failed!", data.message, false);
                    // Keep the form visible for correction
                    requestLanguageForm.style.display = 'block';
                    closeRequestLanguageModalBtn.classList.add('hidden'); // Hide standalone close button if form is visible
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showRequestLanguageModal("Request Failed!", "An unexpected error occurred. Please try again. (Details in console)", false);
                requestLanguageForm.style.display = 'block'; // Keep form visible on unexpected error
                closeRequestLanguageModalBtn.classList.add('hidden'); // Hide standalone close button
            });
        });

    });
</script>
