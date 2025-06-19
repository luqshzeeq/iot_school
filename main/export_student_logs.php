<?php
// export_student_logs.php
// This script exports data from the student_interaction_logs table to a CSV file.

session_start();
include 'db_connection.php'; // Your database connection file

// --- Basic Authentication Check ---
// Ensure only logged-in teachers (or admins) can access this data.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin')) {
    header("Location: index.php"); // Redirect to login page
    exit();
}

if (!$conn) {
    die("Database connection failed for export.");
}

// --- Fetch Student Interaction Logs Data ---
$student_logs_data = [];

$sql_student_logs = "SELECT log_date, log_time, transcribed_text, detected_language, expected_language, result_status, timestamp 
                     FROM student_interaction_logs 
                     ORDER BY log_date DESC, log_time DESC";

$result_student_logs = $conn->query($sql_student_logs);

if ($result_student_logs) {
    while ($row = $result_student_logs->fetch_assoc()) {
        $student_logs_data[] = $row;
    }
} else {
    error_log("Error fetching student interaction logs for export: " . $conn->error);
    die("Error fetching data for export.");
}

$conn->close();

// --- Generate CSV Output ---

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="student_interaction_logs_' . date('Y-m-d_H-i') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Output CSV headers
fputcsv($output, [
    'Date',
    'Time',
    'Transcribed Text',
    'Detected Language',
    'Expected Language',
    'Result Status',
    'Log Timestamp'
]);

// Output data rows
foreach ($student_logs_data as $row) {
    fputcsv($output, [
        $row['log_date'],
        $row['log_time'],
        $row['transcribed_text'],
        $row['detected_language'],
        $row['expected_language'],
        $row['result_status'],
        $row['timestamp']
    ]);
}

fclose($output);
exit();

?>