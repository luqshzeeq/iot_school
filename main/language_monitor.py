import speech_recognition as sr
from gtts import gTTS
from playsound import playsound
from datetime import datetime
import csv # Keep csv import if you still want to write to csv in addition to DB, otherwise remove
from lingua import Language, LanguageDetectorBuilder
import mysql.connector
import os
import requests

# ===== DATABASE CONFIGURATION =====
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'language_monitor'
}

# ===== ESP32 DEVICE CONFIGURATION (for direct communication) =====
ESP32_IP_ADDRESS = "172.20.10.3" # Example: "192.168.1.100"
ESP32_WEB_SERVER_PORT = 80 

# ===== CONFIGURATION =====
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

speech_lang_code = {
    'ENGLISH': 'en-US',
    'MALAY': 'ms-MY',
    'CHINESE': 'zh-CN',
    'TAMIL': 'ta-IN'
}

DB_LANG_TO_INTERNAL_KEY = {
    'Bahasa Melayu': 'MALAY',
    'English': 'ENGLISH',
    'Mandarin': 'CHINESE',
    'Tamil': 'TAMIL'
}

language_map = {
    'ENGLISH': Language.ENGLISH,
    'MALAY': Language.MALAY,
    'CHINESE': Language.CHINESE,
    'TAMIL': Language.TAMIL
}
detector = LanguageDetectorBuilder.from_languages(*language_map.values()).build()

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
            print(f"Found daily language: {target_language_name} (ID: {target_language_id})")
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
        print("Language usage logged to database (language_usage table).") # Updated print message
    except mysql.connector.Error as err:
        print(f"Error logging language usage (language_usage table): {err}")
    finally:
        cursor.close()
        conn.close()

# NEW: Function to log student interaction to the new table
def log_student_interaction_to_db(log_date, log_time, transcribed_text, detected_language, expected_language, result_status):
    """Logs student interaction details to the student_interaction_logs table."""
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor()
    try:
        query = """
        INSERT INTO student_interaction_logs (log_date, log_time, transcribed_text, detected_language, expected_language, result_status, timestamp)
        VALUES (%s, %s, %s, %s, %s, %s, NOW())
        """
        cursor.execute(query, (log_date, log_time, transcribed_text, detected_language, expected_language, result_status))
        conn.commit()
        print("Student interaction logged to database (student_interaction_logs table).")
    except mysql.connector.Error as err:
        print(f"Error logging student interaction (student_interaction_logs table): {err}")
    finally:
        cursor.close()
        conn.close()


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
    send_result_to_esp32("Unknown") # Inform ESP32 of unknown state
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
    # Log to both tables
    log_language_usage_to_db(target_language_id, "NO_SPEECH", "Wrong", "No speech detected within timeout")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), "NO_SPEECH_DETECTED", "N/A", target_language_internal_key, "NoSpeech")
    exit()
except Exception as e:
    print(f"Microphone/Audio capture error: {e}")
    play_feedback_sound("Microphone error. Please check your microphone and permissions.")
    send_result_to_esp32("MicError") # Inform ESP32
    # Log to both tables
    log_language_usage_to_db(target_language_id, "MIC_ERROR", "Wrong", f"Microphone error: {e}")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), f"MIC_ERROR: {e}", "N/A", target_language_internal_key, "MicError")
    exit()


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

    # --- Logging ---
    # Log to language_usage table
    log_language_usage_to_db(target_language_id, detected_lang_name, result, transcribed_text)

    # Log to new student_interaction_logs table
    log_student_interaction_to_db(
        datetime.now().strftime("%Y-%m-%d"),
        datetime.now().strftime("%H:%M:%S"),
        transcribed_text,
        detected_lang_name,
        target_language_internal_key, # Expected language
        result
    )

    # Removed CSV logging, as data is now going to student_interaction_logs table
    # kept for reference if needed:
    # now = datetime.now()
    # with open("canteen_log.csv", mode='a', newline='', encoding='utf-8') as file:
    #     writer = csv.writer(file)
    #     writer.writerow([
    #         now.strftime("%Y-%m-%d"),
    #         now.strftime("%H:%M:%S"),
    #         transcribed_text,
    #         detected_lang_name,
    #         target_language_internal_key,
    #         result
    #     ])
    # print("Logged to canteen_log.csv")

except sr.UnknownValueError:
    print("Could not understand audio.")
    feedback_msg = f"Sorry, I couldn’t understand. Please try again in {target_language_internal_key.capitalize()}."
    play_feedback_sound(feedback_msg, lang='en')
    send_result_to_esp32("Unknown") # Inform ESP32 that transcription was unknown
    # Log to both tables
    log_language_usage_to_db(target_language_id, "UNKNOWN", "Wrong", "Could not understand audio")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), "UNTRANSCRIBABLE", "UNKNOWN", target_language_internal_key, "Unknown")

except sr.RequestError as e:
    print(f"Could not request results from Google Speech Recognition service; {e}")
    feedback_msg = "Speech recognition service error. Please check your internet connection."
    play_feedback_sound(feedback_msg, lang='en')
    send_result_to_esp32("API_Error") # Inform ESP32 of API error
    # Log to both tables
    log_language_usage_to_db(target_language_id, "API_ERROR", "Wrong", f"Speech Recognition API Error: {e}")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), f"API_ERROR: {e}", "N/A", target_language_internal_key, "API_Error")

except Exception as e:
    print(f"An unexpected error occurred: {e}")
    feedback_msg = "An unexpected error occurred. Please try again."
    play_feedback_sound(feedback_msg, lang='en')
    send_result_to_esp32("SystemError") # Inform ESP32 of a general system error
    # Log to both tables
    log_language_usage_to_db(target_language_id, "SYSTEM_ERROR", "Wrong", f"Unexpected Error: {e}")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), f"SYSTEM_ERROR: {e}", "N/A", target_language_internal_key, "SystemError")