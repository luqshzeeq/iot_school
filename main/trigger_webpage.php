<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Language Monitor - Speech Trigger</title>
    <link rel="icon" href="assets/images/brand/unimapicon.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-image: url('img/canteen.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .mic-icon {
            font-size: 4rem; /* Larger icon */
            margin-bottom: 1rem;
            color: #60a5fa; /* Blue color */
        }
        .status-message {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
        }
        #triggerButton.active-key-press {
            transform: scale(0.98);
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.5);
        }
        .waveform-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 4rem;
            margin-bottom: 1rem;
        }
        .waveform-container span {
            display: block;
            width: 6px;
            height: 80%;
            margin: 0 4px;
            background-color: #22c55e;
            border-radius: 4px;
            animation: sound-wave 1.2s infinite ease-in-out;
        }
        .waveform-container span:nth-child(2) { animation-delay: -1.0s; }
        .waveform-container span:nth-child(3) { animation-delay: -0.8s; }
        .waveform-container span:nth-child(4) { animation-delay: -0.6s; }
        .waveform-container span:nth-child(5) { animation-delay: -0.4s; }
        @keyframes sound-wave {
            0%, 40%, 100% {
                transform: scaleY(0.1);
            }
            20% {
                transform: scaleY(1.0);
            }
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-xl text-center max-w-md w-full">
        <h1 class="text-3xl font-bold text-gray-800 mb-6"> Language Monitor</h1>
        <p class="text-gray-600 mb-8">Click 'Start' or " Press Shift + Enter" and speak your order in the expected language.</p>

        <p id="expectedLanguageDisplay" class="text-xl font-semibold text-purple-700 mb-4">
            Today's Language: Loading...
        </p>

        <div id="statusArea" class="mb-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <i id="micIcon" class="fas fa-microphone mic-icon"></i>
            <div id="waveform" class="waveform-container hidden">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>
            <p id="statusMessage" class="status-message">Ready to start order.</p>
        </div>

        <button id="triggerButton"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-md transition duration-300 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-blue-300">
            Start
        </button>

        <div id="studentOutputArea" class="mt-4 p-4 bg-white rounded-md border border-gray-300 text-left text-gray-800">
            <p class="font-semibold text-gray-800 mb-2">Student Output Display:</p>
            <pre class="whitespace-pre-wrap text-base font-mono" id="filteredOutputText">Waiting for interaction...</pre>
        </div>

        <div class="mt-8">
            <a href="index.php" class="group inline-flex items-center text-sm font-semibold text-gray-600 hover:text-blue-700 transition-colors duration-300">
                <i class="fas fa-arrow-left mr-2 transition-transform duration-300 ease-in-out group-hover:-translate-x-1"></i>
                <span>Back to Login</span>
            </a>
        </div>
        </div>

    <script>
        $(document).ready(function() {
            // Define your API Key and Device ID (must match ESP32 & PHP config)
            const API_KEY = "ESP32_SECRET_KEY";
            const DEVICE_ID = "ESP32_LangMon_002";

            // Define the URL to your esp_communication_handler.php
            const PHP_HANDLER_URL = "http://172.20.10.3/esp_communication_handler.php"; // <<< REPLACE WITH YOUR PC's IP

            function updateStatusDisplay(iconClass, message, colorClass = 'text-blue-600', isListening = false) {
                if (isListening) {
                    $('#micIcon').addClass('hidden');
                    $('#waveform').removeClass('hidden');
                } else {
                    $('#waveform').addClass('hidden');
                    $('#micIcon').removeClass('hidden');
                    $('#micIcon').removeClass().addClass(`fas ${iconClass} mic-icon`);
                }
                $('#statusMessage').removeClass().addClass(`status-message ${colorClass}`).text(message);
                if (colorClass === 'text-red-600') {
                    $('#micIcon').css('color', '#dc2626');
                } else if (colorClass === 'text-green-600') {
                    $('#micIcon').css('color', '#16a34a');
                } else if (colorClass === 'text-yellow-600') {
                    $('#micIcon').css('color', '#ca8a04');
                }
                else {
                    $('#micIcon').css('color', '#60a5fa');
                }
            }
            
            function filterPythonOutputForStudent(fullOutput) {
                // If there is no output from the script, show the default message.
                if (!fullOutput) {
                    return 'Waiting for interaction...';
                }

                const lines = fullOutput.split('\n');
                const linesToKeep = [];

                // Loop through each line of the Python script's output.
                for (const line of lines) {
                    const trimmedLine = line.trim();
                    
                    // Only keep the line if it starts with "Transcription" or "FINAL FEEDBACK:".
                    if (trimmedLine.startsWith('Transcription') || trimmedLine.startsWith('FINAL FEEDBACK:')) {
                        linesToKeep.push(trimmedLine);
                    }
                }

                // Join the kept lines together with a newline.
                if (linesToKeep.length > 0) {
                    return linesToKeep.join('\n');
                } else {
                    return 'See main status above for result.';
                }
            }

            function getExpectedLanguage() {
                $.ajax({
                    url: PHP_HANDLER_URL,
                    method: 'GET',
                    data: { action: 'get_daily_language', api_key: API_KEY, device_id: DEVICE_ID },
                    success: function(response) {
                        if (response.success && response.expected_language) {
                            $('#expectedLanguageDisplay').text('Today\'s Expected Language: ' + response.expected_language.trim());
                        } else {
                            $('#expectedLanguageDisplay').text('Today\'s Expected Language: Not Found (Check DB).');
                            console.error('Failed to get expected language:', response.message || 'No specific error message from PHP.');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        $('#expectedLanguageDisplay').text('Today\'s Expected Language: Error loading.');
                        console.error('AJAX Error getting expected language:', textStatus, errorThrown, jqXHR.responseText);
                    }
                });
            }

            getExpectedLanguage();

            function initiateSpeechProcess() {
                if ($('#triggerButton').prop('disabled')) {
                    return;
                }
                $('#filteredOutputText').text('Waiting for result...');
                $('#triggerButton').prop('disabled', true).removeClass('bg-blue-600 hover:bg-blue-700').addClass('bg-gray-400 cursor-not-allowed');

                // Step 1: Show an "Initializing" state immediately
                updateStatusDisplay('fa-spinner fa-spin', 'Initializing... Please wait.', 'text-gray-600', false);

                // Step 2: Use a timer to switch to "Listening" after a delay
                const startupDelay = 5000; // 2 seconds in milliseconds. Adjust as needed.
                setTimeout(function() {
                    updateStatusDisplay('', 'Listening... Please speak your order.', 'text-green-600', true);
                }, startupDelay);

                // The AJAX call starts immediately as before.
                $.ajax({
                    url: PHP_HANDLER_URL,
                    method: 'GET',
                    data: { action: 'trigger_speech', api_key: API_KEY, device_id: DEVICE_ID },
                    success: function(response) {
                        let pythonOutput = response.python_output || '';
                        let displayMessage = '';
                        let icon = 'fa-check-circle';
                        let color = 'text-green-600';

                        if (pythonOutput.includes('No speech detected within timeout')) {
                            displayMessage = 'No speech detected. Please try again.';
                            icon = 'fa-times-circle';
                            color = 'text-red-600';
                        } else if (pythonOutput.includes('Microphone error on server')) {
                            displayMessage = 'Microphone error on server. Check server console.';
                            icon = 'fa-times-circle';
                            color = 'text-red-600';
                        } else if (pythonOutput.includes('Transcription')) {
                            if (pythonOutput.includes('Correct!')) {
                                displayMessage = 'Correct! You spoke in the expected language.';
                                icon = 'fa-check-circle';
                                color = 'text-green-600';
                            } else if (pythonOutput.includes('Wrong language')) {
                                displayMessage = 'Wrong language detected. Please try again.';
                                icon = 'fa-times-circle';
                                color = 'text-red-600';
                            } else {
                                displayMessage = 'Audio processed, but feedback is unclear.';
                                icon = 'fa-info-circle';
                                color = 'text-blue-600';
                            }
                        } else if (pythonOutput.includes('Could not understand audio')) {
                            displayMessage = 'Could not understand. Please speak clearer.';
                            icon = 'fa-question-circle';
                            color = 'text-yellow-600';
                        } else {
                            displayMessage = 'An issue occurred. Check full output.';
                            icon = 'fa-info-circle';
                            color = 'text-blue-600';
                        }
                        
                        updateStatusDisplay(icon, displayMessage, color, false); // Switch back from listening animation
                        $('#filteredOutputText').text(filterPythonOutputForStudent(pythonOutput));
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        let errorMessage = 'Request FAILED: ' + textStatus + ' - ' + errorThrown;
                        updateStatusDisplay('fa-exclamation-triangle', errorMessage, 'text-red-600');
                        $('#filteredOutputText').text('An error occurred. Please try again or contact staff.');
                    },
                    complete: function() {
                        $('#triggerButton').prop('disabled', false).addClass('bg-blue-600 hover:bg-blue-700').removeClass('bg-gray-400 cursor-not-allowed');
                    }
                });
            }

            $('#triggerButton').on('click', initiateSpeechProcess);
            $(document).on('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    $('#triggerButton').addClass('active-key-press');
                    initiateSpeechProcess();
                } else if (event.ctrlKey && event.key === 'Enter') {
                    event.preventDefault();
                    $('#triggerButton').addClass('active-key-press');
                    initiateSpeechProcess();
                } else if (event.shiftKey && event.key === 'Enter') {
                    event.preventDefault();
                    $('#triggerButton').addClass('active-key-press');
                    initiateSpeechProcess();
                } else if (event.shiftKey && event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    $('#triggerButton').addClass('active-key-press');
                    initiateSpeechProcess();
                }
            });
            $(document).on('keyup', function(event) {
                if (event.key === 'Enter' || event.key === ' ' || event.ctrlKey || event.shiftKey || event.altKey) {
                    $('#triggerButton').removeClass('active-key-press');
                }
            });

        });
    </script>
</body>
</html>