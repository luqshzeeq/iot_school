<?php
// language_usage.php
// This file is included by teacher_dashboard.php when $page is 'language_usage'.
// You can add PHP logic here to fetch data for charts or reports.

// Fetch all language settings by this teacher:
$teacher_language_history = [];
if(isset($conn) && isset($teacher_id)){
    // Removed LIMIT 10 here so DataTables can paginate and search all available data
    $sql_history = "SELECT tdl.setting_date, l.language_name
                    FROM teacher_daily_languages tdl
                    JOIN languages l ON tdl.language_id = l.id
                    WHERE tdl.teacher_id = ?
                    ORDER BY tdl.setting_date DESC";
    $stmt_history = $conn->prepare($sql_history);
    if($stmt_history){
        $stmt_history->bind_param("i", $teacher_id);
        $stmt_history->execute();
        $result_history = $stmt_history->get_result();
        while($row = $result_history->fetch_assoc()){
            $teacher_language_history[] = $row;
        }
        $stmt_history->close();
    }
}
?>

<div class="space-y-8">
    <div class="card">
        <div class="card-header">
            <h3 class="text-lg font-semibold text-gray-700">Language Usage Overview</h3>
        </div>
        <div class="card-body">
            <p class="text-gray-600 mb-4">This section will provide insights into language usage patterns. Below are placeholders for potential charts and data tables.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-medium text-gray-700 mb-2">Language Distribution (Pie Chart)</h4>
                    <div class="w-full h-64 bg-gray-100 rounded-lg flex items-center justify-center">
                        <p class="text-gray-400">Pie Chart Placeholder</p>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Shows the distribution of languages set over a period.</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-medium text-gray-700 mb-2">Monthly Trend (Bar Chart)</h4>
                    <div class="w-full h-64 bg-gray-100 rounded-lg flex items-center justify-center">
                        <p class="text-gray-400">Bar Chart Placeholder</p>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Illustrates how often languages are set each month.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="text-lg font-semibold text-gray-700">Language Settings History</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($teacher_language_history)): ?>
                <div class="overflow-x-auto">
                    <table id="languageHistoryTable" class="min-w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                            <tr>
                                <th scope="col" class="px-6 py-3">Date Set</th>
                                <th scope="col" class="px-6 py-3">Language</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacher_language_history as $entry): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                    <?php echo htmlspecialchars(date("D, j M Y", strtotime($entry['setting_date']))); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo htmlspecialchars($entry['language_name']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600">No language setting history found for your account yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Ensure the DOM is fully loaded before initializing DataTables
    document.addEventListener('DOMContentLoaded', function() {
        // Check if the table element exists before trying to initialize DataTables
        // This prevents errors if no history is found and the table isn't rendered
        if (document.getElementById('languageHistoryTable')) {
            $('#languageHistoryTable').DataTable({
                "paging": true,         // Enable pagination
                "searching": true,      // Enable search box
                "lengthChange": true,   // Enable "Show X entries" dropdown
                "lengthMenu": [10, 25, 50, 100], // Options for "Show X entries"
                "ordering": true,       // Enable column ordering
                "info": true,           // Show table information (e.g., "Showing 1 to 10 of X entries")
                "language": {
                    // Customize text if needed, e.g., for localization
                    "lengthMenu": "Show _MENU_ entries",
                    "search": "Search:",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "Showing 0 to 0 of 0 entries",
                    "infoFiltered": "(filtered from _MAX_ total entries)",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    }
                }
            });
        }
    });
</script>