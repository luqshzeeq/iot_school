<?php
session_start();
include 'db_connection.php'; // Your database connection file

// --- Optional: Basic Authentication Check ---
// You should add more robust authentication as needed.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php"); // Redirect to login page
    exit();
}

if (!$conn) {
    die("Database connection failed for export.");
}

// --- Fetch Weekly Language Usage Data ---
$weekly_usage_data_raw = [];

// Calculate start and end dates for the current week (Monday to Friday)
$current_date_obj = new DateTime(date('Y-m-d'));
if ($current_date_obj->format('N') != 1) { // If not Monday (1 is Monday for ISO-8601)
    $current_date_obj->modify('last monday');
}
$week_start_date_str = $current_date_obj->format('Y-m-d');
$current_date_obj->modify('+4 days'); // Move to Friday
$week_end_date_str = $current_date_obj->format('Y-m-d');

$sql_export_data = "SELECT usage_date, detected_language, status, COUNT(*) as count
                    FROM language_usage
                    WHERE usage_date BETWEEN ? AND ?
                    GROUP BY usage_date, detected_language, status
                    ORDER BY usage_date ASC, detected_language ASC, status ASC";

$stmt_export_data = $conn->prepare($sql_export_data);
if ($stmt_export_data) {
    $stmt_export_data->bind_param("ss", $week_start_date_str, $week_end_date_str);
    if ($stmt_export_data->execute()) {
        $result_export_data = $stmt_export_data->get_result();
        while ($row = $result_export_data->fetch_assoc()) {
            $weekly_usage_data_raw[] = $row;
        }
    } else {
        error_log("Error fetching export data: " . $stmt_export_data->error);
        die("Error fetching data for export.");
    }
    $stmt_export_data->close();
} else {
    error_log("Error preparing export statement: " . $conn->error);
    die("Error preparing export data.");
}

$conn->close();

// --- Generate CSV Output ---

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="weekly_language_usage_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Output CSV headers
fputcsv($output, ['Date', 'Detected Language', 'Status', 'Count']);

// Output data rows
foreach ($weekly_usage_data_raw as $row) {
    fputcsv($output, [
        $row['usage_date'],
        $row['detected_language'],
        $row['status'],
        $row['count']
    ]);
}

fclose($output);
exit();

?>