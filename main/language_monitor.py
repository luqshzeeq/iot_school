import speech_recognition as sr
from gtts import gTTS
from playsound import playsound
from datetime import datetime
import csv
from lingua import Language, LanguageDetectorBuilder
import mysql.connector
import os
import requests # NEW: Import the requests library for sending HTTP requests

# ===== DATABASE CONFIGURATION =====
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'language_monitor'
}

# ===== ESP32 DEVICE CONFIGURATION (for direct communication) =====
# You MUST replace this with your ESP32's actual IP address.
# Check your ESP32's Serial Monitor or OLED display after it connects to WiFi.
ESP32_IP_ADDRESS = "172.20.10.3" # Example: "192.168.1.100"
ESP32_WEB_SERVER_PORT = 80 # Default port for ESP32's WebServer (from ESP32 code)

# ===== CONFIGURATION =====

# Expected phrases per language (remains unchanged)
expected_phrases = {
    'MALAY': [
        "makcik nak order nasi lemak dan teh ais",
        "saya mahu nasi goreng dan air sirap",
        "saya nak beli roti canai dan milo ais"
    ],
    'ENGLISH': [
        "i would like to order nasi lemak and iced tea",
        "i want fried rice and syrup drink",
        "i want to buy roti canai and milo ice"
    ],
    'CHINESE': [
        "wo yao ji fan",
        "wo yao mai roti dan he milo bing",
        "ni hao wo yao cha dan ji fan"
    ],
    'TAMIL': [
        "vanakkam enakku soru vendum",
        "naan sappiduven",
        "soru kudunga milo kudunga"
    ]
}

# Google Speech API language codes (remains unchanged)
speech_lang_code = {
    'ENGLISH': 'en-US',
    'MALAY': 'ms-MY',
    'CHINESE': 'zh-CN',
    'TAMIL': 'ta-IN'
}

# Map database language names to internal keys (remains unchanged)
DB_LANG_TO_INTERNAL_KEY = {
    'Bahasa Melayu': 'MALAY',
    'English': 'ENGLISH',
    'Mandarin': 'CHINESE',
    'Tamil': 'TAMIL'
}

# Setup language detection (lingua) (remains unchanged)
language_map = {
    'ENGLISH': Language.ENGLISH,
    'MALAY': Language.MALAY,
    'CHINESE': Language.CHINESE,
    'TAMIL': Language.TAMIL
}
detector = LanguageDetectorBuilder.from_languages(*language_map.values()).build()

# Helper function to play sound and clean up (remains unchanged)
def play_feedback_sound(text, lang='en'):
    """Generates and plays an audio feedback, then cleans up the audio file."""
    try:
        tts = gTTS(text=text, lang=lang)
        audio_file = "feedback.mp3"
        tts.save(audio_file)
        playsound(audio_file)
    except Exception as e:
        print(f"Error playing sound: {e}")
    finally:
        if os.path.exists(audio_file):
            os.remove(audio_file)

def get_db_connection():
    """Establishes and returns a database connection."""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except mysql.connector.Error as err:
        print(f"Error connecting to database: {err}")
        return None

def get_language_of_day_from_db(date_str):
    """
    Fetches the global language set for a given date from the database.
    Returns (language_id, internal_language_key) or (None, None) if not found.
    """
    conn = get_db_connection()
    if not conn:
        return None, None

    cursor = conn.cursor(dictionary=True)
    target_language_id = None
    target_language_name = None
    internal_lang_key = None

    try:
        query = """
        SELECT tdl.language_id, l.language_name
        FROM teacher_daily_languages tdl
        JOIN languages l ON tdl.language_id = l.id
        WHERE tdl.setting_date = %s
        LIMIT 1
        """
        cursor.execute(query, (date_str,))
        result = cursor.fetchone()

        if result:
            target_language_id = result['language_id']
            target_language_name = result['language_name']
            internal_lang_key = DB_LANG_TO_INTERNAL_KEY.get(target_language_name)
            print(f"Found daily language: {target_language_name} (ID: {target_language_id})") # Cleaner print for final output
        else:
            print(f"No global language setting found for {date_str}.")

    except mysql.connector.Error as err:
        print(f"Error fetching language of the day: {err}")
    finally:
        cursor.close()
        conn.close()
    return target_language_id, internal_lang_key

def log_language_usage_to_db(language_id, detected_lang_str, status, transcribed_text):
    """Logs the language usage details to the language_usage table."""
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor()
    try:
        current_date = datetime.now().strftime("%Y-%m-%d")
        status_enum = 'correct' if status == 'Correct' or status == 'Correct (Fallback)' else 'incorrect'

        query = """
        INSERT INTO language_usage (language_id, usage_date, detected_language, status, timestamp)
        VALUES (%s, %s, %s, %s, NOW())
        """
        cursor.execute(query, (language_id, current_date, detected_lang_str, status_enum))
        conn.commit()
        print("Language usage logged to database.") # Cleaner print for final output
    except mysql.connector.Error as err:
        print(f"Error logging language usage: {err}")
    finally:
        cursor.close()
        conn.close()

# NEW: Function to send result to ESP32 via HTTP GET
def send_result_to_esp32(result_status_for_esp32):
    """Sends the detection result as an HTTP GET request to the ESP32."""
    if ESP32_IP_ADDRESS == "YOUR_ESP32_IP_ADDRESS":
        print("ERROR: ESP32_IP_ADDRESS is not set. Cannot send result to ESP32.")
        return

    try:
        url = f"http://{ESP32_IP_ADDRESS}:{ESP32_WEB_SERVER_PORT}/?result={result_status_for_esp32}"
        response = requests.get(url, timeout=5) # 5-second timeout for the request
        print(f"Sent result '{result_status_for_esp32}' to ESP32. ESP32 Response: {response.status_code} - {response.text}")
    except requests.exceptions.RequestException as e:
        print(f"ERROR: Could not send result to ESP32 at {ESP32_IP_ADDRESS}: {e}")

# ===== START PROCESS =====
print("Script started. Time:", datetime.now().strftime('%Y-%m-%d %H:%M:%S'))

current_date_str = datetime.now().strftime("%Y-%m-%d")

target_language_id, target_language_internal_key = get_language_of_day_from_db(current_date_str)

if not target_language_internal_key:
    print("Could not determine target language. Exiting.")
    play_feedback_sound("Sorry, I cannot determine the language for today. Please contact support.")
    send_result_to_esp32("UnknownCmd") # Inform ESP32 of unknown state
    exit()

print(f"Today is {datetime.now().strftime('%A, %Y-%m-%d')}. Expected language: {target_language_internal_key.capitalize()}")
print("Please speak your order...")

# Start mic
recognizer = sr.Recognizer()
try: # Wrap microphone part in its own try-except for mic issues
    with sr.Microphone() as source:
        recognizer.adjust_for_ambient_noise(source, duration=1)
        print("Listening...")
        audio = recognizer.listen(source, timeout=5, phrase_time_limit=5) # Added timeout and phrase_time_limit
        print("Audio captured. Processing...")

except sr.WaitTimeoutError:
    print("No speech detected within timeout.")
    play_feedback_sound("Sorry, I didn't hear anything. Please try again.")
    send_result_to_esp32("NoSpeech") # Inform ESP32
    log_language_usage_to_db(target_language_id, "NO_SPEECH", "Wrong", "No speech detected within timeout")
    exit() # Exit after timeout
except Exception as e:
    print(f"Microphone/Audio capture error: {e}")
    play_feedback_sound("Microphone error. Please check your microphone and permissions.")
    send_result_to_esp32("MicError") # Inform ESP32
    log_language_usage_to_db(target_language_id, "MIC_ERROR", "Wrong", f"Microphone error: {e}")
    exit() # Exit on mic error


user_text = ""
transcribed_text = ""
detected_lang_name = "N/A"
result = "Wrong" # Default result for system-level logic

try:
    # Transcribe audio using the speech code for the detected target language
    transcribed_text = recognizer.recognize_google(audio, language=speech_lang_code[target_language_internal_key])
    print(f"Transcribed: {transcribed_text}")
    user_text = transcribed_text.lower().strip()

    # Detect language with Lingua
    detected_lingua_lang = detector.detect_language_of(user_text)
    detected_lang_name = detected_lingua_lang.name.upper() if detected_lingua_lang else "UNKNOWN"
    print(f"Detected language (Lingua): {detected_lang_name}")

    # Determine correctness
    # The 'result' variable here is the one used for logging and for sending to ESP32
    if detected_lang_name == target_language_internal_key:
        feedback_msg = f"Correct! You spoke in {target_language_internal_key.capitalize()}."
        result = "Correct" # Result for ESP32 and logging
    elif any(phrase.lower() in user_text for phrase in expected_phrases.get(target_language_internal_key, [])):
        feedback_msg = f"Acceptable phrase in {target_language_internal_key.capitalize()}."
        result = "Correct" # Treat "Correct (Fallback)" as just "Correct" for ESP32/simplicity
    else:
        feedback_msg = f"Wrong language. Please speak in {target_language_internal_key.capitalize()}."
        result = "Wrong" # Result for ESP32 and logging

    print(feedback_msg)
    play_feedback_sound(feedback_msg, lang='en')

    # Send final result (Correct/Wrong) to ESP32
    send_result_to_esp32(result) # Send "Correct" or "Wrong"

    # Logging
    now = datetime.now()
    with open("canteen_log.csv", mode='a', newline='', encoding='utf-8') as file:
        writer = csv.writer(file)
        writer.writerow([
            now.strftime("%Y-%m-%d"),
            now.strftime("%H:%M:%S"),
            transcribed_text,
            detected_lang_name,
            target_language_internal_key, # This is the expected language, not the detected.
            result
        ])
    print("Logged to canteen_log.csv")
    log_language_usage_to_db(target_language_id, detected_lang_name, result, transcribed_text)

except sr.UnknownValueError:
    print("Could not understand audio.")
    feedback_msg = f"Sorry, I couldn’t understand. Please try again in {target_language_internal_key.capitalize()}."
    play_feedback_sound(feedback_msg, lang='en')
    send_result_to_esp32("Unknown") # Inform ESP32 that transcription was unknown
    log_language_usage_to_db(target_language_id, "UNKNOWN", "Wrong", "Could not understand audio")

except sr.RequestError as e:
    print(f"Could not request results from Google Speech Recognition service; {e}")
    feedback_msg = "Speech recognition service error. Please check your internet connection."
    play_feedback_sound(feedback_msg, lang='en')
    send_result_to_esp32("API_Error") # Inform ESP32 of API error
    log_language_usage_to_db(target_language_id, "API_ERROR", "Wrong", f"Speech Recognition API Error: {e}")

except Exception as e:
    print(f"An unexpected error occurred: {e}")
    feedback_msg = "An unexpected error occurred. Please try again."
    play_feedback_sound(feedback_msg, lang='en')
    send_result_to_esp32("SystemError") # Inform ESP32 of a general system error
    log_language_usage_to_db(target_language_id, "SYSTEM_ERROR", "Wrong", f"Unexpected Error: {e}")