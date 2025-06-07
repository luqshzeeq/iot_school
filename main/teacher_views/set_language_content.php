<?php
// This file is included by teacher_dashboard.php when $page is 'set_language'.
// Expected variables from teacher_dashboard.php:
// $conn, $teacher_id
// $selected_date_str (the date being viewed/edited)
// $available_languages (array of languages from 'languages' table)
// $current_language_for_selected_date_id (ID of language currently set for $selected_date_str)

// Fallbacks (should ideally be set by parent teacher_dashboard.php)
$selected_date_str_display = isset($selected_date_str) ? htmlspecialchars($selected_date_str) : date('Y-m-d');
$available_languages_list = isset($available_languages) ? $available_languages : [];
$current_lang_id = isset($current_language_for_selected_date_id) ? $current_language_for_selected_date_id : '';

?>
<div class="max-w-2xl mx-auto">
    <div class="card">
        <div class="card-header">
            <h3 class="text-lg font-semibold text-gray-800">Set Language of the Day</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="teacher_dashboard.php?page=set_language&setting_date=<?php echo $selected_date_str_display; ?>" id="dailyLanguageForm">
                <input type="hidden" name="set_language_action" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="mb-6">
                    <label for="setting_date_picker" class="block text-sm font-medium text-gray-700 mb-1">Select Date:</label>
                    <input type="date" id="setting_date_picker" name="setting_date" 
                            value="<?php echo $selected_date_str_display; ?>" 
                            class="w-full bg-white border border-gray-300 text-gray-900 px-3 py-2.5 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                            required>
                    </div>

                <div class="mb-6">
                    <label for="languageSelectPage" class="block text-sm font-medium text-gray-700 mb-1">
                        Select Language for <span id="selectedDateDisplay" class="font-semibold"><?php echo $selected_date_str_display; ?></span>:
                    </label>
                    <select id="languageSelectPage" name="language_id" class="w-full bg-white border border-gray-300 text-gray-900 px-3 py-2.5 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm" required>
                        <option value="">-- Choose a Language --</option>
                        <?php if (!empty($available_languages_list)): ?>
                            <?php foreach ($available_languages_list as $lang): ?>
                                <option value="<?php echo htmlspecialchars($lang['id']); ?>" <?php echo ($current_lang_id == $lang['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lang['language_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No languages available. Please add them via Admin panel.</option>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg text-sm transition duration-150 shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                    <i class="fas fa-save mr-2"></i>Select Language
                </button>
            </form>
        </div>
    </div>
</div>