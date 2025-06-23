import speech_recognition as sr
from gtts import gTTS
from playsound import playsound
from datetime import datetime
import csv
from lingua import Language, LanguageDetectorBuilder
import mysql.connector
import os
import requests
import sys 

# ===== DATABASE CONFIGURATION =====
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '', 
    'database': 'language_monitor'
}

# ===== ESP32 DEVICE CONFIGURATION (for direct communication) =====
ESP32_IP_ADDRESS = "172.20.10.3" 
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
        #playsound(audio_file) edit this if want play feedback speaker 
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
        print(f"DB ERROR: Error connecting to database: {err}") 
        return None

# MODIFIED: get_language_of_day_from_db to return language_name as well
def get_language_of_day_from_db(date_str):
    """
    Fetches the global language set for a given date from the database.
    Returns (language_id, language_name, internal_language_key) or (None, None, None) if not found.
    """
    conn = get_db_connection()
    if not conn:
        return None, None, None # Connection failed

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
        print(f"DB DEBUG: Querying for daily language: {date_str}")
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
        print(f"DB ERROR: Error fetching language of the day: {err}")
    finally:
        cursor.close()
        conn.close()
    return target_language_id, target_language_name, internal_lang_key # RETURN ALL THREE


def log_language_usage_to_db(language_id, detected_lang_str, status, transcribed_text):
    """Logs the language usage details to the language_usage table."""
    conn = get_db_connection()
    if not conn:
        print("DB ERROR: Connection failed, cannot log language usage.")
        return

    cursor = conn.cursor()
    try:
        current_date = datetime.now().strftime("%Y-%m-%d")
        status_enum = 'correct' if status == 'Correct' or status == 'Correct (Fallback)' else 'incorrect'

        query = """
        INSERT INTO language_usage (language_id, usage_date, detected_language, status, timestamp)
        VALUES (%s, %s, %s, %s, NOW())
        """
        print(f"DB DEBUG: Executing query for language_usage: {query}")
        cursor.execute(query, (language_id, current_date, detected_lang_str, status_enum)) 
        print(f"DB DEBUG: Query executed. Rows affected: {cursor.rowcount}")

        conn.commit()
        print("Language usage logged to database (language_usage table).")
    except mysql.connector.Error as err:
        print(f"DB ERROR: Error logging language usage (language_usage table): {err}")
        conn.rollback()
    finally:
        cursor.close()
        conn.close()


def log_student_interaction_to_db(log_date, log_time, transcribed_text, detected_language, expected_language, result_status):
    """Logs student interaction details to the student_interaction_logs table."""
    conn = get_db_connection()
    if not conn:
        print("DB ERROR: Connection failed, cannot log student interaction.")
        return

    cursor = conn.cursor()
    try:
        query = """
        INSERT INTO student_interaction_logs (log_date, log_time, transcribed_text, detected_language, expected_language, result_status, timestamp)
        VALUES (%s, %s, %s, %s, %s, %s, NOW())
        """
        print(f"DB DEBUG: Executing query for student_interaction_logs: {query}")
        cursor.execute(query, (log_date, log_time, transcribed_text, detected_language, expected_language, result_status))
        print(f"DB DEBUG: Query executed. Rows affected: {cursor.rowcount}")

        conn.commit()
        print("Student interaction logged to database (student_interaction_logs table).")
    except mysql.connector.Error as err:
        print(f"DB ERROR: Error logging student interaction (student_interaction_logs table): {err}")
        conn.rollback()
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
        response = requests.get(url, timeout=5) 
        print(f"Sent result '{result_status_for_esp32}' to ESP32. ESP32 Response: {response.status_code} - {response.text}")
    except requests.exceptions.RequestException as e:
        print(f"ERROR: Could not send result to ESP32 at {ESP32_IP_ADDRESS}: {e}")

# ===== START PROCESS =====
# Check for command-line arguments to determine execution mode
if len(sys.argv) > 1 and sys.argv[1] == '--get-expected-language':
    current_date_str = datetime.now().strftime("%Y-%m-%d")
    # MODIFIED: Call get_language_of_day_from_db to get name
    target_language_id, target_language_name_for_display, internal_lang_key = get_language_of_day_from_db(current_date_str) 
    
    if target_language_name_for_display: # Use the actual name for printing
        print(f"EXPECTED_LANGUAGE_IS: {target_language_name_for_display}") 
    else:
        print("EXPECTED_LANGUAGE_IS: Not Found (Check DB).")
    sys.exit(0) 

# Normal execution path (full speech recognition)
print("Script started. Time:", datetime.now().strftime('%Y-%m-%d %H:%M:%S'))

current_date_str = datetime.now().strftime("%Y-%m-%d")

# MODIFIED: Call get_language_of_day_from_db to get name
target_language_id, target_language_name_for_display, target_language_internal_key = get_language_of_day_from_db(current_date_str)

if not target_language_internal_key: # Still use internal key for language logic
    print("Could not determine target language. Exiting.")
    play_feedback_sound("Sorry, I cannot determine the language for today. Please contact support.")
    send_result_to_esp32("Unknown")
    exit()

print(f"Today is {datetime.now().strftime('%A, %Y-%m-%d')}. Expected language: {target_language_name_for_display.capitalize()}") # Use name for display
print("\nPlease speak your order...") 

user_text = ""
transcribed_text = ""
detected_lang_name = "N/A"
result = "Wrong"

recognizer = sr.Recognizer()
try:
    with sr.Microphone() as source:
        recognizer.adjust_for_ambient_noise(source, duration=1)
        print("Listening...")
        audio = recognizer.listen(source, timeout=5, phrase_time_limit=5)
        print("Audio captured. Processing...")

except sr.WaitTimeoutError:
    print("No speech detected within timeout.")
    play_feedback_sound("Sorry, I didn't hear anything. Please try again.")
    send_result_to_esp32("NoSpeech")
    log_language_usage_to_db(target_language_id, "NO_SPEECH", "Wrong", "No speech detected within timeout")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), "NO_SPEECH_DETECTED", "N/A", target_language_internal_key, "NoSpeech")
except Exception as e:
    print(f"Microphone/Audio capture error: {e}")
    play_feedback_sound("Microphone error. Please check your microphone and permissions.")
    send_result_to_esp32("MicError")
    log_language_usage_to_db(target_language_id, "MIC_ERROR", "Wrong", f"Microphone error: {e}")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), f"MIC_ERROR: {e}", "N/A", target_language_internal_key, "MicError")
    exit()

try:
    transcribed_text = recognizer.recognize_google(audio, language=speech_lang_code[target_language_internal_key])
    print(f"Transcribed: {transcribed_text}")
    user_text = transcribed_text.lower().strip()

    detected_lingua_lang = detector.detect_language_of(user_text)
    detected_lang_name = detected_lingua_lang.name.upper() if detected_lingua_lang else "UNKNOWN"
    print(f"Detected language (Lingua): {detected_lang_name}")

    if detected_lang_name == target_language_internal_key:
        feedback_msg = f"Correct! You spoke in {target_language_name_for_display}." # Use display name
        result = "Correct"
    elif any(phrase.lower() in user_text for phrase in expected_phrases.get(target_language_internal_key, [])):
        feedback_msg = f"Acceptable phrase in {target_language_name_for_display}." # Use display name
        result = "Correct"
    else:
        feedback_msg = f"Wrong language. Please speak in {target_language_name_for_display}." # Use display name
        result = "Wrong"

    print(feedback_msg)
    play_feedback_sound(feedback_msg, lang='en')

    send_result_to_esp32(result)

    log_language_usage_to_db(target_language_id, detected_lang_name, result, transcribed_text)
    log_student_interaction_to_db(
        datetime.now().strftime("%Y-%m-%d"),
        datetime.now().strftime("%H:%M:%S"),
        transcribed_text,
        detected_lang_name,
        target_language_internal_key, # Log internal key
        result
    )

except sr.UnknownValueError:
    print("Could not understand audio.")
    feedback_msg = f"Sorry, I couldn’t understand the audio. Please try again in {target_language_name_for_display}." # Use display name
    play_feedback_sound(feedback_msg, lang='en')
    send_result_to_esp32("Unknown")
    log_language_usage_to_db(target_language_id, "UNKNOWN", "Wrong", "Could not understand audio")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), "UNTRANSCRIBABLE", "UNKNOWN", target_language_internal_key, "Unknown")

except sr.RequestError as e:
    print(f"Could not request results from Google Speech Recognition service; {e}")
    feedback_msg = "Speech recognition service error. Please check your internet connection."
    play_feedback_sound(feedback_msg, lang='en')
    send_result_to_esp32("API_Error")
    log_language_usage_to_db(target_language_id, "API_ERROR", "Wrong", f"Speech Recognition API Error: {e}")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), f"API_ERROR: {e}", "N/A", target_language_internal_key, "API_Error")

except Exception as e:
    print(f"An unexpected error occurred: {e}")
    feedback_msg = "An unexpected error occurred. Please try again."
    play_feedback_sound(feedback_msg, lang='en')
    send_result_to_esp32("SystemError")
    log_language_usage_to_db(target_language_id, "SYSTEM_ERROR", "Wrong", f"Unexpected Error: {e}")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), f"SYSTEM_ERROR: {e}", "N/A", target_language_internal_key, "SystemError")