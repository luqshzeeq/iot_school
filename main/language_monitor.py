<?php
session_start();

// No longer need to check for teacher role if this script is for students speaking
// and we're just getting the global language of the day.
// You still need to manage user sessions (student or teacher) as appropriate for your app flow.

// Define the path to your Python executable and script
$python_executable = '/usr/bin/python3'; // Common path for Python 3 on Linux
// $python_executable = 'C:\Python39\python.exe'; // Example path on Windows
$python_script_path = __DIR__ . '/language_monitor.py'; // Assumes script is in the same directory as this PHP file

// Construct the command to execute the Python script.
// No teacher_id argument is passed anymore.
$command = escapeshellcmd("{$python_executable} {$python_script_path}");

echo "Attempting to run command: " . htmlspecialchars($command) . "<br>";

// Execute the Python script
$output = shell_exec($command);

if ($output === null) {
    echo "Error: Failed to execute Python script. Check paths and permissions.<br>";
} else {
    echo "Python script output:<br><pre>" . htmlspecialchars($output) . "</pre>";
}

// Optionally, you might want to redirect the user after execution or display feedback
// header("Location: student_speaking_page.php?status=speech_processed");
// exit();

?>