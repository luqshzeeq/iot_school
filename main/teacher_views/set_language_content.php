<?php
// Assuming session_start() and db_connection.php are included higher up
// Fallbacks and sanitization for expected variables
$selected_date_str_display = isset($selected_date_str) ? htmlspecialchars($selected_date_str) : date('Y-m-d');
$available_languages_list = is_array($available_languages ?? null) ? $available_languages : [];
$current_lang_id = ''; // Initialize, will fetch the global language for the selected date

// Assuming $conn is available from an include, and $_SESSION['user_id'] is the logged-in teacher

// Fetch the globally set language for the selected date
if (isset($conn)) {
    // THIS IS LINE 12, check for hidden characters if you encounter syntax error again.
    // If you see "Database connection not available." error, delete this line and manually re-type it.
    // echo "<p class='text-red-600'>Database connection not available.</p>";
    // return; // Exit if no DB connection - REMOVE THIS RETURN AFTER FIXING CONNECTION
}
// Note: $teacher_id is still passed, but for the global usage charts, we won't filter by teacher_id.
// The history table (below) *does* filter by set_by_teacher_id.

if (!isset($conn)) { // Check $conn at the beginning of the file's logic block
    echo "<p class='text-red-600'>Database connection not available. Please check db_connection.php and its inclusion.</p>";
    return;
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['set_language_action'])) {
    if (!isset($_SESSION['user_id'])) {
        // Handle not logged in error - redirect or show message
        $_SESSION['error_message'] = "You must be logged in to set the language.";
        // Optionally redirect
        header("Location: teacher_dashboard.php");
        exit();
    }

    $teacher_id = $_SESSION['user_id']; // The ID of the teacher who is setting it
    $setting_date = trim($_POST['setting_date'] ?? '');
    $language_id = filter_var($_POST['language_id'] ?? '', FILTER_VALIDATE_INT);

    if (empty($setting_date) || $language_id === false || $language_id <= 0) {
        $_SESSION['error_message'] = "Invalid date or language selected.";
        header("Location: teacher_dashboard.php?page=set_language&selected_date_str=" . urlencode($setting_date));
        exit();
    }

    // Check if an entry for this date already exists
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM teacher_daily_languages WHERE setting_date = ?");
    if ($stmt_check) {
        $stmt_check->bind_param("s", $setting_date);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            // Update existing entry for this date
            $stmt = $conn->prepare("UPDATE teacher_daily_languages SET language_id = ?, set_by_teacher_id = ? WHERE setting_date = ?");
            if ($stmt) {
                $stmt->bind_param("iis", $language_id, $teacher_id, $setting_date);
            }
        } else {
            // Insert new entry
            $stmt = $conn->prepare("INSERT INTO teacher_daily_languages (setting_date, language_id, set_by_teacher_id) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sii", $setting_date, $language_id, $teacher_id);
            }
        }

        if ($stmt) {
            if ($stmt->execute()) {
                $_SESSION['language_set_success'] = true;
                // Reload current page with the selected date to show update
                header("Location: teacher_dashboard.php?page=set_language&selected_date_str=" . urlencode($setting_date));
                exit();
            } else {
                $_SESSION['error_message'] = "Error setting language: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Database statement preparation failed: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = "Database check statement preparation failed: " . $conn->error;
    }

    // If an error occurred, redirect with the error message
    header("Location: teacher_dashboard.php?page=set_language&selected_date_str=" . urlencode($setting_date));
    exit();
}


// --- NEW: Fetch all Teacher Daily Languages for the table ---
$teacher_daily_languages_records = [];
if (isset($conn)) {
    // Show all global daily language settings, sorted by date.
    // Use LEFT JOIN with users table to show 'set_by' username, even if user is not found.
    $sql_daily_langs_table = "SELECT tdl.setting_date, l.language_name, u.username AS set_by_username
                              FROM teacher_daily_languages tdl
                              JOIN languages l ON tdl.language_id = l.id
                              LEFT JOIN users u ON tdl.set_by_teacher_id = u.id 
                              ORDER BY tdl.setting_date DESC";
    $result_daily_langs_table = $conn->query($sql_daily_langs_table);

    if ($result_daily_langs_table) {
        while ($row = $result_daily_langs_table->fetch_assoc()) {
            // Handle case where set_by_username might be NULL due to LEFT JOIN
            if ($row['set_by_username'] === NULL) {
                $row['set_by_username'] = 'Unknown User (ID: ' . $row['set_by_teacher_id'] . ')'; 
            }
            $teacher_daily_languages_records[] = $row;
        }
    } else {
        error_log("Error fetching teacher daily languages for table: " . $conn->error);
    }
}


?>

<style>
    /* Custom styles for DataTables search input and pagination to match modern design */
    .dataTables_wrapper .dataTables_filter {
        text-align: right; /* Align search to the right */
        margin-bottom: 1rem;
    }

    .dataTables_wrapper .dataTables_filter label {
        display: inline-flex; /* Align label and input */
        align-items: center;
        font-weight: normal; /* Override default bold label */
        margin-bottom: 0;
    }

    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #e2e8f0; /* Subtle border */
        border-radius: 0.5rem; /* Rounded corners */
        padding: 0.5rem 1rem; /* Padding inside input */
        margin-left: 0.5rem; /* Space from label */
        width: 150px; /* Default width */
        transition: all 0.2s ease-in-out;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); /* Subtle inner shadow */
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #3B82F6; /* Blue on focus */
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); /* Focus ring */
        outline: none; /* Remove default outline */
    }

    /* Pagination Styling */
    .dataTables_wrapper .dataTables_paginate {
        text-align: right; /* Align pagination to the right */
        margin-top: 1rem;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        display: inline-block;
        padding: 0.5rem 0.8rem;
        margin-left: 0.25rem;
        border-radius: 0.375rem; /* Slightly rounded buttons */
        border: 1px solid #e2e8f0; /* Subtle border */
        background-color: #ffffff;
        color: #4a5568; /* Default text color */
        cursor: pointer;
        transition: all 0.2s ease-in-out;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background-color: #EEF2FF; /* Light blue background for active page */
        border-color: #C7D2FE; /* Matching border color */
        color: #3B82F6; /* Blue text color for active page */
        font-weight: 600;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background-color: #E0E7FF; /* Slightly darker blue on hover for active */
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
        color: #a0aec0; /* Lighter color for disabled buttons */
        cursor: not-allowed;
        background-color: #f7fafc;
        border-color: #e2e8f0;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:not(.current):not(.disabled):hover {
        background-color: #f7fafc; /* Light gray on hover for inactive buttons */
        border-color: #cbd5e0;
        color: #2d3748;
    }

    /* Length (Show X entries) dropdown styling */
    .dataTables_wrapper .dataTables_length {
        margin-bottom: 1rem;
        text-align: left;
    }
    .dataTables_wrapper .dataTables_length label {
        display: inline-flex;
        align-items: center;
        font-weight: normal;
        color: #4a5568;
    }
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.3rem 0.5rem;
        margin: 0 0.5rem;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        background-color: #ffffff;
    }

    /* Info text styling */
    .dataTables_wrapper .dataTables_info {
        margin-top: 1rem;
        color: #718096; /* Gray text color */
        font-size: 0.875rem; /* text-sm */
    }

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

    <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-800 p-4 rounded-md shadow-sm mb-6" role="alert"> <div class="flex">
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
                    <li><strong>For any given day, only ONE language can be set globally.</strong> If you set a language for a date that already has one, it will be updated to your new selection.</li>
                    <li>The currently selected language for the chosen date is displayed below the "Select Language for..." label.</li>
                </ul>
            </div>
        </div>
    </div>

<div class="card mb-6">
    <div class="card-header">
        <h3 class="text-xl font-semibold text-gray-800">Daily Language Settings History</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($teacher_daily_languages_records)): ?>
            <div class="overflow-x-auto">
                <table id="dailyLanguageHistoryTable" class="min-w-full text-left"> <thead class="bg-white"> <tr>
                            <th scope="col" class="px-6 py-3 font-medium text-gray-500 uppercase tracking-wider">Date</th> <th scope="col" class="px-6 py-3 font-medium text-gray-500 uppercase tracking-wider">Language</th>
                            <th scope="col" class="px-6 py-3 font-medium text-gray-500 uppercase tracking-wider">Set By Teacher</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200"> <?php foreach ($teacher_daily_languages_records as $record): ?>
                        <tr class="hover:bg-gray-50"> <td class="px-6 py-4 whitespace-nowrap text-gray-700"><?php echo htmlspecialchars($record['setting_date']); ?></td> <td class="px-6 py-4 whitespace-nowrap text-gray-700"><?php echo htmlspecialchars($record['language_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-700"><?php echo htmlspecialchars($record['set_by_username']); ?></td>
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
            url.searchParams.set('selected_date_str', this.value); // Use 'selected_date_str' for GET param
            form.action = url.toString();

            // Perform an AJAX request or redirect to load the language for the new date
            // For simplicity, a page reload is used here, matching the initial load logic
            window.location.href = `teacher_dashboard.php?page=set_language&selected_date_str=${this.value}`;
        });

        // Ensure the initial date display matches the picker if coming from query param
        selectedDateDisplay.textContent = settingDatePicker.value;

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
                    "lengthMenu": "Show _MENU_ entries",
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
                // NEW: Order by first column (Date) by default in descending order
                "order": [[0, "desc"]] 
            });

            // --- Custom styling for DataTables elements ---
            // These will style the search input and pagination generated by DataTables
            $('.dataTables_filter input').addClass('border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500');
            $('.dataTables_filter label').contents().filter(function() {
                return this.nodeType === 3; // Filter out text nodes (the "Search:" text)
            }).remove(); // Remove default DataTables "Search:" text as we'll add a placeholder
            $('.dataTables_filter input').attr('placeholder', 'Search...');

            // Add search icon
            $('.dataTables_filter').prepend('<i class="fas fa-search text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>').css('position', 'relative');
            $('.dataTables_filter input').css('padding-left', '2.5rem'); // Make space for icon

            // Pagination buttons
            $('.dataTables_paginate .paginate_button').addClass('px-4 py-2 border border-gray-200 bg-white text-gray-700 rounded-md transition-colors hover:bg-gray-100');
            $('.dataTables_paginate .paginate_button.current').addClass('bg-blue-600 text-white border-blue-600 hover:bg-blue-700');
            $('.dataTables_paginate .paginate_button.disabled').addClass('opacity-50 cursor-not-allowed');
            $('.dataTables_paginate .paginate_button.next, .dataTables_paginate .paginate_button.previous').html(''); // Remove default text for next/prev
            $('.dataTables_paginate .next').append('<i class="fas fa-chevron-right"></i>'); // Add icon for next
            $('.dataTables_paginate .previous').prepend('<i class="fas fa-chevron-left"></i>'); // Add icon for previous

            // Adjust layout of DataTables controls (optional, depending on default DataTables CSS)
            $('.dataTables_length, .dataTables_filter').wrapAll('<div class="flex justify-between items-center mb-4"></div>');
            $('.dataTables_info, .dataTables_paginate').wrapAll('<div class="flex justify-between items-center mt-4"></div>');
        }
    });
</script>