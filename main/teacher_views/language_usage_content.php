<?php
// language_usage.php
// This file is included by teacher_dashboard.php when $page is 'language_usage'.
// It fetches data for the history table and new chart data.

// Ensure $conn and $teacher_id are available from teacher_dashboard.php scope
if (!isset($conn)) {
    echo "<p class='text-red-600'>Database connection not available.</p>";
    return; // Exit if no DB connection
}
// Note: $teacher_id is still passed, but for the global usage charts, we won't filter by teacher_id.
// The history table (below) *does* filter by set_by_teacher_id.

// --- Define a master consistent color mapping for all charts ---
// Use colors that are visually distinct for common labels.
$master_chart_colors = [
    'ENGLISH' => ['backgroundColor' => '#3B82F6', 'borderColor' => '#2563EB'], // Tailwind blue-500/600
    'MALAY'   => ['backgroundColor' => '#10B981', 'borderColor' => '#059669'], // Tailwind emerald-500/600
    'UNKNOWN' => ['backgroundColor' => '#F59E0B', 'borderColor' => '#D97706'], // Tailwind amber-500/600
    'Bahasa Melayu' => ['backgroundColor' => '#10B981', 'borderColor' => '#059669'], // Ensure consistency if 'Bahasa Melayu' appears as a label
    'CHINESE' => ['backgroundColor' => '#EC4899', 'borderColor' => '#BE185D'], // Tailwind pink-500/600
    'TAMIL'   => ['backgroundColor' => '#8B5CF6', 'borderColor' => '#7C3AED'], // Tailwind violet-500/600
    // Add more languages/labels here if you have others, or use a fallback.
    'ERROR'   => ['backgroundColor' => '#EF4444', 'borderColor' => '#DC2626'], // Tailwind red-500/600
    'Correct' => ['backgroundColor' => '#48BB78', 'borderColor' => '#38A169'], // Tailwind green-500/600
    'Incorrect' => ['backgroundColor' => '#FC8181', 'borderColor' => '#E53E3E'], // Tailwind red-400/500
];


// --- Fetch all language settings by this teacher (the ones they have SET) ---
// This is for the table that shows what languages *this specific teacher* has set for days.
$teacher_language_history = [];
if(isset($teacher_id)){ // Only attempt if teacher_id is available
    $sql_history = "SELECT tdl.setting_date, l.language_name
                    FROM teacher_daily_languages tdl
                    JOIN languages l ON tdl.language_id = l.id
                    WHERE tdl.set_by_teacher_id = ?  
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
    } else {
        error_log("Error preparing language history statement: " . $conn->error);
    }
}


// --- START: PHP Logic for Language Usage Charts (Global Data) ---

// 1. Data for Detected Language Distribution (Pie Chart)
$language_distribution = []; // Stores counts of detected_language
$sql_detected_lang_dist = "SELECT detected_language, COUNT(*) as count FROM language_usage GROUP BY detected_language";
$result_detected_lang_dist = $conn->query($sql_detected_lang_dist);

if ($result_detected_lang_dist) {
    while ($row = $result_detected_lang_dist->fetch_assoc()) {
        $language_distribution[$row['detected_language']] = $row['count'];
    }
} else {
    error_log("Error fetching detected language distribution data: " . $conn->error);
}

// Prepare data for Chart.js Pie Chart (Detected Language Distribution)
$pie_labels = array_keys($language_distribution);
$pie_data = array_values($language_distribution);
$pie_colors_bg = [];
$pie_colors_border = [];
foreach ($pie_labels as $label) {
    $colors = $master_chart_colors[$label] ?? ['backgroundColor' => '#CCCCCC', 'borderColor' => '#999999']; // Fallback
    $pie_colors_bg[] = $colors['backgroundColor'];
    $pie_colors_border[] = $colors['borderColor'];
}


// 2. Data for Correct vs. Incorrect Detections (Pie Chart)
$status_distribution = ['correct' => 0, 'incorrect' => 0]; // Stores counts of correct/incorrect
$sql_status_dist = "SELECT status, COUNT(*) as count FROM language_usage GROUP BY status";
$result_status_dist = $conn->query($sql_status_dist);

if ($result_status_dist) {
    while ($row = $result_status_dist->fetch_assoc()) {
        if (isset($status_distribution[$row['status']])) {
            $status_distribution[$row['status']] = $row['count'];
        }
    }
} else {
    error_log("Error fetching status distribution data: " . $conn->error);
}

// Prepare data for Chart.js Pie Chart (Correct/Incorrect Distribution)
$status_pie_labels = ['Correct', 'Incorrect'];
$status_pie_data = [
    $status_distribution['correct'] ?? 0,
    $status_distribution['incorrect'] ?? 0
];
$status_pie_colors_bg = [];
$status_pie_colors_border = [];
foreach ($status_pie_labels as $label) {
    $colors = $master_chart_colors[$label] ?? ['backgroundColor' => '#CCCCCC', 'borderColor' => '#999999']; // Fallback
    $status_pie_colors_bg[] = $colors['backgroundColor'];
    $status_pie_colors_border[] = $colors['borderColor'];
}


// --- 3. Data for Weekly Trend (Bar Chart) - Usage counts per day of current week ---
$weekly_usage_data_by_lang = []; // Stores date => language_name => count mappings

// Calculate start and end dates for the current week (Monday to Friday)
$current_date_obj = new DateTime(date('Y-m-d')); // Start with today's date
// Adjust to Monday of the current week (ISO-8601, Monday=1)
if ($current_date_obj->format('N') != 1) {
    $current_date_obj->modify('last monday');
}
$week_start_date_str = $current_date_obj->format('Y-m-d');
$current_date_obj->modify('+4 days'); // Move to Friday
$week_end_date_str = $current_date_obj->format('Y-m-d');


// Fetch data for the current week (Monday to Friday)
$sql_weekly_bar_data = "SELECT usage_date, detected_language, COUNT(*) as count
                         FROM language_usage
                         WHERE usage_date BETWEEN ? AND ?
                         GROUP BY usage_date, detected_language
                         ORDER BY usage_date ASC";

$stmt_weekly_bar_data = $conn->prepare($sql_weekly_bar_data);
if ($stmt_weekly_bar_data) {
    $stmt_weekly_bar_data->bind_param("ss", $week_start_date_str, $week_end_date_str);
    if ($stmt_weekly_bar_data->execute()) {
        $result_weekly_bar_data = $stmt_weekly_bar_data->get_result();
        while ($row = $result_weekly_bar_data->fetch_assoc()) {
            $date_key = $row['usage_date'];
            $lang = $row['detected_language'];
            $count = $row['count'];
            
            if (!isset($weekly_usage_data_by_lang[$date_key])) {
                $weekly_usage_data_by_lang[$date_key] = [];
            }
            $weekly_usage_data_by_lang[$date_key][$lang] = $count;
        }
    } else {
        error_log("Error fetching weekly bar chart data: " . $stmt_weekly_bar_data->error);
    }
    $stmt_weekly_bar_data->close();
}


// Generate labels for Monday to Friday of the current week
$bar_labels = [];
$date_cursor_bar = new DateTime($week_start_date_str);
for ($i = 0; $i < 5; $i++) { // Loop 5 times for Mon-Fri
    $bar_labels[] = $date_cursor_bar->format('D, j M'); // e.g., "Mon, 17 Jun"
    $date_cursor_bar->modify('+1 day');
}

// Prepare datasets for the weekly bar chart
$bar_datasets = [];
// Get all unique detected languages that appeared in usage history for consistent datasets
$all_detected_languages_for_bar = array_keys($language_distribution); 

foreach ($all_detected_languages_for_bar as $lang_name) {
    $data_for_lang = [];
    $current_day_iter_bar = new DateTime($week_start_date_str); // Reset iterator for each language
    for ($i = 0; $i < 5; $i++) { // Loop through Mon-Fri dates
        $current_date_str_iter = $current_day_iter_bar->format('Y-m-d');
        $data_for_lang[] = $weekly_usage_data_by_lang[$current_date_str_iter][$lang_name] ?? 0;
        $current_day_iter_bar->modify('+1 day');
    }

    $colors = $master_chart_colors[$lang_name] ?? ['backgroundColor' => '#A1A1AA', 'borderColor' => '#71717A']; // Fallback
    
    $bar_datasets[] = [
        'label' => $lang_name,
        'data' => $data_for_lang,
        'backgroundColor' => $colors['backgroundColor'],
        'borderColor' => $colors['borderColor'],
        'borderWidth' => 1
    ];
}


// Encode PHP arrays to JSON for direct use in JavaScript
$pie_chart_data_json = json_encode([
    'labels' => $pie_labels,
    'datasets' => [[
        'data' => $pie_data,
        'backgroundColor' => $pie_colors_bg,
        'borderColor' => $pie_colors_border,
        'hoverOffset' => 4
    ]]
]);

$status_pie_chart_data_json = json_encode([
    'labels' => $status_pie_labels,
    'datasets' => [[
        'data' => $status_pie_data,
        'backgroundColor' => $status_pie_colors_bg,
        'borderColor' => $status_pie_colors_border,
        'hoverOffset' => 4
    ]]
]);

$bar_chart_data_json = json_encode([
    'labels' => $bar_labels,
    'datasets' => $bar_datasets
]);

// --- END: PHP Logic for Language Usage Charts ---

?>

<div class="space-y-8">
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="text-lg font-semibold text-gray-700">Global Language Usage Overview</h3>
        </div>
        <div class="card-body">
            <p class="text-gray-600 mb-4">This section provides insights into overall system language usage patterns (all student attempts).</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="p-4 bg-gray-50 rounded-lg flex flex-col items-center">
                    <h4 class="font-medium text-gray-700 mb-2">Detected Language Distribution</h4>
                    <div class="w-full h-64 flex items-center justify-center">
                        <canvas id="languageDistributionChart"></canvas>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 text-center">Shows the distribution of languages detected across all usage entries.</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg flex flex-col items-center">
                    <h4 class="font-medium text-gray-700 mb-2">Correct vs. Incorrect Detections</h4>
                    <div class="w-full h-64 flex items-center justify-center">
                        <canvas id="statusDistributionChart"></canvas>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 text-center">Breakdown of correctly identified vs. incorrectly identified language attempts.</p>
                </div>
            </div>

            <div class="p-4 bg-gray-50 rounded-lg">
                <h4 class="font-medium text-gray-700 mb-2">Weekly Language Usage Trend (Monday - Friday)</h4>
                <div class="w-full h-80 flex items-center justify-center">
                    <canvas id="weeklyUsageTrendChart"></canvas>
                </div>
                <p class="text-xs text-gray-500 mt-2 text-center">Illustrates the volume of usage for each detected language across the current school week.</p>
                <div class="mt-4 text-right">
                    <button id="exportWeeklyUsageBtn" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md text-sm transition duration-150 shadow-md">
                        <i class="fas fa-file-excel mr-2"></i>Export Weekly Usage
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="text-lg font-semibold text-gray-700">My Language Settings History</h3>
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
    // Ensure the DOM is fully loaded before initializing DataTables and Charts
    document.addEventListener('DOMContentLoaded', function() {
        // --- DataTables Initialization ---
        if (document.getElementById('languageHistoryTable')) {
            $('#languageHistoryTable').DataTable({
                "paging": true,        // Enable pagination
                "searching": true,     // Enable search box
                "lengthChange": true,  // Enable "Show X entries" dropdown
                "lengthMenu": [10, 25, 50, 100], // Options for "Show X entries"
                "ordering": true,      // Enable column ordering
                "info": true,          // Show table information (e.g., "Showing 1 to 10 of X entries")
                "language": {
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

        // --- Chart.js Initialization ---

        // Parse PHP data
        const pieChartData = <?php echo $pie_chart_data_json; ?>;
        const statusPieChartData = <?php echo $status_pie_chart_data_json; ?>;
        const barChartData = <?php echo $bar_chart_data_json; ?>;

        // 1. Detected Language Distribution Pie Chart
        const ctxLangDist = document.getElementById('languageDistributionChart');
        if (ctxLangDist) {
            new Chart(ctxLangDist, {
                type: 'pie',
                data: pieChartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        title: {
                            display: false, // Title is in h4
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += context.parsed + ' entries';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        // 2. Correct vs. Incorrect Detections Pie Chart
        const ctxStatusDist = document.getElementById('statusDistributionChart');
        if (ctxStatusDist) {
            new Chart(ctxStatusDist, {
                type: 'pie',
                data: statusPieChartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        title: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += context.parsed + ' entries';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        // 3. Weekly Language Usage Trend Bar Chart
        const ctxWeeklyTrend = document.getElementById('weeklyUsageTrendChart');
        if (ctxWeeklyTrend) {
            new Chart(ctxWeeklyTrend, {
                type: 'bar',
                data: barChartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 20, // Make legend color boxes smaller
                            }
                        },
                        title: {
                            display: false,
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            stacked: true, // Stack bars for each day
                            title: {
                                display: true,
                                text: 'Day of Week'
                            }
                        },
                        y: {
                            stacked: true, // Stack bars for each day
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Detections'
                            },
                            ticks: {
                                precision: 0 // Ensure whole numbers
                            }
                        }
                    }
                }
            });
        }

        // --- Export to Excel Button Logic ---
        const exportWeeklyUsageBtn = document.getElementById('exportWeeklyUsageBtn');
        if (exportWeeklyUsageBtn) {
            exportWeeklyUsageBtn.addEventListener('click', function() {
                // Redirect to a new PHP script that generates the CSV/Excel file.
                // You will need to create this file: 'export_weekly_usage.php'
                window.location.href = 'export_weekly_usage.php';
            });
        }
    });
</script>