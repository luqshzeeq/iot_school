<?php
// esp_communication_handler.php
// Handles both device status updates and button triggers from ESP32.

header('Content-Type: application/json'); // Default to JSON response
header('Access-Control-Allow-Origin: *'); // <<< ADD THIS LINE FOR CORS
// --- DATABASE CONFIGURATION ---
$db_host = '127.0.0.1';
$db_user = 'root';
$db_password = ''; // Typically empty for XAMPP root user
$db_name = 'language_monitor';

// --- Configuration: Must match values set in esp32.ino ---
$expectedApiKey = "ESP32_SECRET_KEY"; // Must match apiKey in esp32.ino
$expectedDeviceId = "ESP32_LangMon_002"; // Must match deviceID in esp32.ino

// --- Get common parameters ---
$action = $_REQUEST['action'] ?? ''; // Use $_REQUEST to get from GET or POST
$receivedApiKey = $_REQUEST['api_key'] ?? '';
$receivedDeviceId = $_REQUEST['device_id'] ?? '';

// --- Basic Security Check ---
// We only enforce this for actions that trigger Python execution or database writes
if ($action === 'trigger_speech' || $action === 'update_status') { 
    if ($receivedApiKey !== $expectedApiKey || $receivedDeviceId !== $expectedDeviceId) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access or invalid device credentials.'
        ]);
        http_response_code(403); // Forbidden
        exit();
    }
}


// --- Function to get Database Connection ---
function getDbConnection($host, $user, $password, $name) {
    $conn = new mysqli($host, $user, $password, $name);
    if ($conn->connect_error) {
        error_log("Database Connection Failed: " . $conn->connect_error);
        return null;
    }
    return $conn;
}

// --- Helper function to run Python script and capture output ---
function runPythonScript($scriptPath, $pythonExecutable, $args = []) {
    $command = escapeshellcmd("{$pythonExecutable} {$scriptPath} " . implode(' ', array_map('escapeshellarg', $args)));
    
    $descriptorspec = array(
       0 => array("pipe", "r"),   // stdin
       1 => array("pipe", "w"),   // stdout
       2 => array("pipe", "w")    // stderr
    );
    $process = proc_open($command, $descriptorspec, $pipes);

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $return_code = proc_close($process);

    return ['stdout' => $stdout, 'stderr' => $stderr, 'return_code' => $return_code];
}

// Define the path to your Python executable
$python_executable = 'C:\\Users\\luqma\\AppData\\Local\\Programs\\Python\\Python310\\python.exe';
$python_script_path = __DIR__ . '\\language_monitor.py'; 

// --- Handle actions based on 'action' parameter ---
switch ($action) {
    case 'update_status':
        // --- DEVICE STATUS UPDATE LOGIC ---
        $status = $_REQUEST['status'] ?? 'unknown'; 
        
        $conn = getDbConnection($db_host, $db_user, $db_password, $db_name);
        if (!$conn) {
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed for status update.'
            ]);
            http_response_code(500);
            exit();
        }

        $allowed_statuses = ['online', 'offline', 'error']; 
        if (!in_array($status, $allowed_statuses)) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
            http_response_code(400); 
            exit();
        }

        $sql = "INSERT INTO device_status (device_id, status)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), last_checked = NOW()";

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            error_log("SQL Prepare Failed: " . $conn->error);
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Database prepare failed for status update.']);
            http_response_code(500);
            exit();
        }

        $stmt->bind_param("ss", $receivedDeviceId, $status);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => "Device status '$status' updated in DB."
            ]);
        } else {
            error_log("SQL Execute Failed: " . $stmt->error);
            echo json_encode([
                'success' => false,
                'message' => 'Database update failed for status.'
            ]);
            http_response_code(500);
        }

        $stmt->close();
        $conn->close();
        break;

    case 'trigger_speech':
        // --- BUTTON TRIGGER LOGIC ---
        $python_output_data = runPythonScript($python_script_path, $python_executable);

        $response_message = 'Python script triggered successfully.';
        if ($python_output_data['return_code'] !== 0) {
            $response_message = "Python script failed to execute. Return code: {$python_output_data['return_code']}. Error: {$python_output_data['stderr']}";
            error_log("Python script execution error in esp_communication_handler.php (trigger_speech): " . $python_output_data['stderr']);
            http_response_code(500); 
        }
        
        echo json_encode([
            'success' => ($python_output_data['return_code'] === 0),
            'message' => $response_message,
            'python_output' => $python_output_data['stdout'] ?: 'No direct output.'
        ]);
        break;

    case 'get_daily_language':
        // --- NEW: Lightweight action to only get daily language directly from DB ---
        $current_date = date('Y-m-d'); // Get today's date

        $conn = getDbConnection($db_host, $db_user, $db_password, $db_name);
        if (!$conn) {
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed for language fetch.'
            ]);
            http_response_code(500);
            exit();
        }

        $expectedLang = 'Not Found (Check DB).';
        $sql = "SELECT l.language_name
                FROM teacher_daily_languages tdl
                JOIN languages l ON tdl.language_id = l.id
                WHERE tdl.setting_date = ?
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("SQL Prepare Failed (get_daily_language): " . $conn->error);
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Database prepare failed for language fetch.']);
            http_response_code(500);
            exit();
        }

        $stmt->bind_param("s", $current_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $expectedLang = $row['language_name'];
        }
        
        $stmt->close();
        $conn->close();

        echo json_encode([
            'success' => true,
            'message' => 'Expected language fetched directly from DB.',
            'expected_language' => $expectedLang
        ]);
        break;

    default:
        // Invalid or missing action
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or missing action parameter.'
        ]);
        http_response_code(400); 
        break;
}
exit();
?>