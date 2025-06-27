import speech_recognition as sr
from gtts import gTTS
from playsound import playsound
from datetime import datetime
from lingua import Language, LanguageDetectorBuilder
import mysql.connector
import os
import requests
import sys
import urllib.parse
import numpy as np
import noisereduce as nr
import io

# --- Enforce UTF-8 for all print output ---
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')

# ==============================================================================
# --- CONFIGURATION ---
# ==============================================================================

# -- Database Configuration --
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'language_monitor'
}

# -- ESP32 Device Configuration --
ESP32_IP_ADDRESS = "172.20.10.3" # Your ESP32's IP Address
ESP32_WEB_SERVER_PORT = 80

# -- Speech Recognition Configuration --
speech_lang_code = {
    'ENGLISH': 'en-US', 'MALAY': 'ms-MY',
    'CHINESE': 'zh-CN', 'TAMIL': 'ta-IN'
}

# -- Language Mapping and Detection Configuration --
DB_LANG_TO_INTERNAL_KEY = {
    'Bahasa Melayu': 'MALAY', 'English': 'ENGLISH',
    'Mandarin': 'CHINESE', 'Tamil': 'TAMIL'
}
language_map = {
    'ENGLISH': Language.ENGLISH, 'MALAY': Language.MALAY,
    'CHINESE': Language.CHINESE, 'TAMIL': Language.TAMIL
}
REVERSE_LANGUAGE_MAP = {v: k for k, v in language_map.items()}
detector = LanguageDetectorBuilder.from_languages(*language_map.values()).build()


# ==============================================================================
# --- HELPER FUNCTIONS ---
# ==============================================================================

def play_feedback_sound(text, lang='en'):
    """Generates and plays an audio feedback, then cleans up the audio file."""
    try:
        tts = gTTS(text=text, lang=lang)
        audio_file = "feedback.mp3"
        tts.save(audio_file)
        # playsound(audio_file) # Uncomment for server-side audio feedback
    except Exception as e:
        print(f"Error playing sound: {e}")
    finally:
        if os.path.exists(audio_file):
            os.remove(audio_file)

def get_db_connection():
    """Establishes and returns a database connection."""
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except mysql.connector.Error as err:
        print(f"DB ERROR: Error connecting to database: {err}")
        return None

def get_language_of_day_from_db(conn, date_str):
    """Fetches the global language set for a given date from the database."""
    if not conn: return None, None, None
    with conn.cursor(dictionary=True) as cursor:
        query = """
        SELECT tdl.language_id, l.language_name FROM teacher_daily_languages tdl
        JOIN languages l ON tdl.language_id = l.id WHERE tdl.setting_date = %s LIMIT 1
        """
        cursor.execute(query, (date_str,))
        result = cursor.fetchone()
        if result:
            internal_key = DB_LANG_TO_INTERNAL_KEY.get(result['language_name'])
            return result['language_id'], result['language_name'], internal_key
    return None, None, None

def log_to_db(query, params, conn):
    """Generic function to execute a logging query to the database."""
    if not conn: return
    try:
        with conn.cursor() as cursor:
            cursor.execute(query, params)
        conn.commit()
    except mysql.connector.Error as err:
        print(f"DB ERROR: Failed to log: {err}")
        conn.rollback()

def send_result_to_esp32(result_status, text="N/A", detected="N/A", expected="N/A"):
    """Sends the detailed detection result as an HTTP GET request to the ESP32."""
    try:
        encoded_text = urllib.parse.quote(text)
        url = f"http://{ESP32_IP_ADDRESS}:{ESP32_WEB_SERVER_PORT}/?result={result_status}&text={encoded_text}&detected={detected}&expected={expected}"
        print(f"[HTTP] Sending detailed result to ESP32: {url}")
        response = requests.get(url, timeout=5)
        print(f"Sent result '{result_status}' to ESP32. ESP32 Response: {response.status_code} - {response.text}")
    except requests.exceptions.RequestException as e:
        print(f"ERROR: Could not send result to ESP32 at {ESP32_IP_ADDRESS}: {e}")

# ==============================================================================
# --- CORE SPEECH PROCESSING FUNCTION ---
# ==============================================================================

def handle_speech_recognition(recognizer, target_language_internal_key, target_language_name_for_display):
    """
    Listens for audio, processes it, and returns the results.
    This function contains the main speech recognition and language detection logic.
    """
    results = {
        "transcribed_text": "N/A", "detected_lang_key": "N/A",
        "result": "Wrong", "result_for_esp32": "Unknown", "feedback_msg": ""
    }

    # 1. Capture and clean audio
    try:
        # ### THE FIX IS HERE ###
        # Using Microphone() without a device_index uses the system's default microphone,
        # which was the original working behavior.
        with sr.Microphone(sample_rate=16000) as source:
            print("Listening...")
            audio_data = recognizer.listen(source, timeout=5, phrase_time_limit=15)
        print("Audio captured. Now cleaning noise...")
        audio_data_np = np.frombuffer(audio_data.get_raw_data(), dtype=np.int16)
        reduced_noise_audio = nr.reduce_noise(y=audio_data_np, sr=source.SAMPLE_RATE, prop_decrease=0.8)
        cleaned_audio = sr.AudioData(reduced_noise_audio.tobytes(), source.SAMPLE_RATE, source.SAMPLE_WIDTH)
        print("Noise cleaning complete. Processing...")
    
    except sr.WaitTimeoutError:
        results["feedback_msg"] = "Sorry, I didn't hear anything. Please try again."
        results["result_for_esp32"] = "NoSpeech"
        results["transcribed_text"] = "NO_SPEECH_DETECTED"
        return results
    except Exception as e:
        results["feedback_msg"] = "Microphone error. Please check your microphone and permissions."
        results["result_for_esp32"] = "MicError"
        results["transcribed_text"] = f"MIC_ERROR: {e}"
        return results

    # 2. Transcribe and Detect Language
    try:
        transcribed_text_found = None
        language_priority_codes = ['en-US', 'ms-MY', 'ta-IN', 'zh-CN']
        print("Attempting to transcribe audio...")
        for lang_code in language_priority_codes:
            try:
                transcribed_text_found = recognizer.recognize_google(cleaned_audio, language=lang_code)
                if transcribed_text_found:
                    print(f"Successfully transcribed using model '{lang_code}'.")
                    results["transcribed_text"] = transcribed_text_found
                    break
            except sr.UnknownValueError:
                print(f"Audio not clearly understood as '{lang_code}', trying next model.")
        
        if results["transcribed_text"] != "N/A":
            detected_lingua_lang = detector.detect_language_of(results["transcribed_text"])
            if detected_lingua_lang:
                results["detected_lang_key"] = REVERSE_LANGUAGE_MAP.get(detected_lingua_lang, "UNKNOWN")
                print(f"Lingua detected language of text as: {results['detected_lang_key']}")

                if results["detected_lang_key"] == target_language_internal_key:
                    results["result"], results["result_for_esp32"] = "Correct", "Correct"
                    results["feedback_msg"] = f"Correct! You spoke in {target_language_name_for_display}."
                    print(f"Transcription (Correct): {results['transcribed_text']}")
                else:
                    results["result"], results["result_for_esp32"] = "Wrong", "Wrong"
                    results["feedback_msg"] = f"Wrong language. Detected {results['detected_lang_key']}. Please speak in {target_language_name_for_display}."
                    print(f"Transcription (Incorrect): Spoke {results['detected_lang_key']}, heard: '{results['transcribed_text']}'")
            else:
                results["result_for_esp32"], results["detected_lang_key"] = "Unknown", "UNKNOWN"
                results["feedback_msg"] = f"Sorry, I couldn't determine the language you spoke. Please try speaking in {target_language_name_for_display}."
                print(f"Could not determine language of transcribed text: '{results['transcribed_text']}'")
        else:
            results["result_for_esp32"], results["detected_lang_key"] = "Unknown", "UNKNOWN"
            results["transcribed_text"] = "UNTRANSCRIBABLE"
            results["feedback_msg"] = f"Sorry, I couldn't understand what you said. Please try speaking in {target_language_name_for_display}."
            print("Could not understand audio in any of the supported languages.")

    except sr.RequestError as e:
        results.update(transcribed_text=f"API_ERROR: {e}", detected_lang_key="API_ERROR", feedback_msg="Speech service error. Please check your internet connection.", result_for_esp32="API_Error")
    except Exception as e:
        results.update(transcribed_text=f"SYSTEM_ERROR: {e}", detected_lang_key="SYSTEM_ERROR", feedback_msg="An unexpected system error occurred.", result_for_esp32="SystemError")

    return results

# ==============================================================================
# --- MAIN EXECUTION ---
# ==============================================================================
def main():
    """Main function to orchestrate the language monitoring process."""
    print("Script started. Time:", datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
    db_conn = get_db_connection()
    if not db_conn:
        print("Exiting due to database connection failure.")
        return

    current_date_str = datetime.now().strftime("%Y-%m-%d")
    target_language_id, target_language_name, target_lang_key = get_language_of_day_from_db(db_conn, current_date_str)

    if not target_lang_key:
        print("Could not determine target language. Exiting.")
        send_result_to_esp32("Unknown", text="Could not find daily lang in DB.")
        db_conn.close()
        return

    print(f"Today is {datetime.now().strftime('%A, %Y-%m-%d')}. Expected language: {target_language_name}")
    print("\nPlease speak your order...")
    
    recognizer = sr.Recognizer()
    results = handle_speech_recognition(recognizer, target_lang_key, target_language_name)

    # Log results to the database
    now = datetime.now()
    log_to_db("INSERT INTO student_interaction_logs (log_date, log_time, transcribed_text, detected_language, expected_language, result_status) VALUES (%s, %s, %s, %s, %s, %s)",
              (now.strftime("%Y-%m-%d"), now.strftime("%H:%M:%S"), results["transcribed_text"], results["detected_lang_key"], target_lang_key, results["result_for_esp32"]), db_conn)
    
    status_enum = 'correct' if results["result"] == 'Correct' else 'incorrect'
    log_to_db("INSERT INTO language_usage (usage_date, detected_language, status, language_id) VALUES (%s, %s, %s, %s)",
              (now.strftime("%Y-%m-%d"), results["detected_lang_key"], status_enum, target_language_id), db_conn)
    
    # Provide final feedback (sound and ESP32)
    if results["feedback_msg"]:
        print(f"\nFINAL FEEDBACK: {results['feedback_msg']}")
        play_feedback_sound(results['feedback_msg'], lang='en')
        send_result_to_esp32(
            results['result_for_esp32'],
            results['transcribed_text'],
            results['detected_lang_key'],
            target_lang_key
        )

    db_conn.close()
    print("\nScript finished.")


if __name__ == "__main__":
    main()