<?php
// trigger_speech_from_esp32.php
// This script is called by the ESP32 push button press via HTTP.
// It executes language_monitor.py and returns a simple JSON response to the ESP32.

header('Content-Type: application/json'); // Indicate JSON response

// --- Configuration: Must match values set in esp32.ino ---
$expectedApiKey = "ESP32_SECRET_KEY"; // Must match apiKey in esp32.ino
$expectedDeviceId = "ESP32_LangMon_002"; // Must match deviceID in esp32.ino

// --- Get parameters from ESP32's GET request ---
$receivedApiKey = $_GET['api_key'] ?? '';
$receivedDeviceId = $_GET['device_id'] ?? '';

// --- Basic Security Check for ESP32 Request ---
if ($receivedApiKey !== $expectedApiKey || $receivedDeviceId !== $expectedDeviceId) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access or invalid device credentials.'
    ]);
    http_response_code(403); // Forbidden
    exit();
}

// --- Define the path to your Python executable and script ---
$python_executable = '/usr/bin/python3'; // Common path for Python 3 on Linux
// On Windows: $python_executable = 'C:\xampp\php\python.exe'; // IMPORTANT: Adjust this path to your Python executable!
                                                              // E.g., C:\Python39\python.exe or C:\Users\YourUser\AppData\Local\Programs\Python\Python39\python.exe
$python_script_path = __DIR__ . '/language_monitor.py'; // Assumes script is in the same directory

// --- Optional: Set Google Cloud Credentials Environment Variable ---
// If you are using Google Cloud Speech-to-text API with a service account key
// ensure the path below is correct for your service account JSON file.
// putenv("GOOGLE_APPLICATION_CREDENTIALS=/full/path/to/your/service-account-key.json");

// --- Construct the command to execute the Python script ---
$command = escapeshellcmd("{$python_executable} {$python_script_path}");

// --- Execute the Python script and capture its output ---
// Use proc_open to capture both standard output (stdout) and standard error (stderr)
$descriptorspec = array(
   0 => array("pipe", "r"),  // stdin
   1 => array("pipe", "w"),  // stdout
   2 => array("pipe", "w")   // stderr
);
$process = proc_open($command, $descriptorspec, $pipes);

$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);

fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);

$return_code = proc_close($process);

// --- Prepare response to ESP32 ---
$response_to_esp32 = [
    'status' => 'success',
    'message' => 'Python script triggered successfully.'
];

if ($return_code !== 0) { // Python script returned a non-zero exit code (indicating an error)
    $response_to_esp32['status'] = 'error';
    $response_to_esp32['message'] = "Python script failed to execute. Return code: {$return_code}. Error: {$stderr}";
    error_log("Python script execution error in trigger_speech_from_esp32.php: " . $stderr);
    http_response_code(500); // Internal Server Error
} else {
    // Python script ran successfully. It means it should have attempted to send the result to ESP32 directly.
    // We can optionally parse its stdout for more detail in this PHP's response if needed for debugging
    // For now, a simple success message is fine.
    $response_to_esp32['message'] = "Python script executed. Python Output: " . ($stdout ?: "No direct output.");
    // If you want to log full stdout/stderr for all calls:
    // error_log("Python STDOUT: " . $stdout . "\nPython STDERR: " . $stderr);
}

echo json_encode($response_to_esp32);
exit();