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
ESP32_IP_ADDRESS = "172.20.10.4" # Your ESP32's IP Address
ESP32_WEB_SERVER_PORT = 80

# -- Whisper Model Configuration --
# Options: "tiny", "base", "small", "medium", "large".
# "base" is a good balance of speed and accuracy for multiple languages.
# Using a larger model (e.g., "small") will be more accurate but slower.
WHISPER_MODEL = "base"

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
# --- HELPER FUNCTIONS (No changes needed here) ---
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
        query = "SELECT tdl.language_id, l.language_name FROM teacher_daily_languages tdl JOIN languages l ON tdl.language_id = l.id WHERE tdl.setting_date = %s LIMIT 1"
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
# --- CORE SPEECH PROCESSING FUNCTION (Updated for Whisper) ---
# ==============================================================================

def handle_speech_recognition(recognizer):
    """
    Listens for audio, processes it with Whisper, and returns the results.
    """
    results = { "transcribed_text": "N/A", "detected_lang_key": "N/A", "error": None }

    # 1. Capture and clean audio
    try:
        with sr.Microphone(sample_rate=16000) as source:
            print("Calibrating for ambient noise...")
            recognizer.adjust_for_ambient_noise(source, duration=0.5)
            print("Listening...")
            audio_data = recognizer.listen(source, timeout=5, phrase_time_limit=15)
        print("Audio captured. Now cleaning noise...")
        audio_data_np = np.frombuffer(audio_data.get_raw_data(), dtype=np.int16)
        reduced_noise_audio = nr.reduce_noise(y=audio_data_np, sr=source.SAMPLE_RATE, prop_decrease=0.8)
        cleaned_audio = sr.AudioData(reduced_noise_audio.tobytes(), source.SAMPLE_RATE, source.SAMPLE_WIDTH)
        print("Noise cleaning complete. Processing...")
    
    except sr.WaitTimeoutError:
        results["error"] = "NoSpeech"
        return results
    except Exception as e:
        results["error"] = "MicError"
        results["transcribed_text"] = f"MIC_ERROR: {e}"
        return results

    # 2. Transcribe using Whisper and then detect language using Lingua
    try:
        print(f"Processing audio with Whisper (Model: {WHISPER_MODEL})...")
        # Let Whisper auto-detect the language by setting language=None
        transcribed_text = recognizer.recognize_whisper(cleaned_audio, model=WHISPER_MODEL, language=None)
        results["transcribed_text"] = transcribed_text.strip()
        
        if not results["transcribed_text"]:
            # Whisper returned an empty string, treat as not understood
            print("Whisper could not understand the audio.")
            results["error"] = "Unknown"
        else:
            print(f"Whisper transcribed: '{results['transcribed_text']}'")
            # Use Lingua for a final, reliable language check on the text
            detected_lingua_lang = detector.detect_language_of(results["transcribed_text"])
            if detected_lingua_lang:
                results["detected_lang_key"] = REVERSE_LANGUAGE_MAP.get(detected_lingua_lang, "UNKNOWN")
            else:
                results["detected_lang_key"] = "UNKNOWN"
            print(f"Lingua detected language of text as: {results['detected_lang_key']}")

    except sr.UnknownValueError:
        print("Whisper could not understand the audio.")
        results["error"] = "Unknown"
    except Exception as e:
        print(f"An unexpected error occurred with Whisper: {e}")
        results["error"] = "SystemError"
        results["transcribed_text"] = f"WHISPER_ERROR: {e}"

    return results

# ==============================================================================
# --- MAIN EXECUTION ---
# ==============================================================================
def main():
    """Main function to orchestrate the language monitoring process."""
    print("Script started. Time:", datetime.now().strftime('%Y-%m-%d %H:%M:%S'))

    recognizer = sr.Recognizer()
    audio_results = handle_speech_recognition(recognizer)

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

    final_result = "Wrong"
    final_result_for_esp32 = "Unknown"
    feedback_msg = ""

    if audio_results["error"]:
        final_result_for_esp32 = audio_results["error"]
        if final_result_for_esp32 == "NoSpeech":
            feedback_msg = "Sorry, I didn't hear anything. Please try again."
        elif final_result_for_esp32 == "Unknown":
            feedback_msg = f"Sorry, I couldn't understand what you said. Please try speaking in {target_language_name}."
        else: # MicError, SystemError
            feedback_msg = "An error occurred. Please check the console."
    elif audio_results["detected_lang_key"] == target_lang_key:
        final_result, final_result_for_esp32 = "Correct", "Correct"
        feedback_msg = f"Correct! You spoke in {target_language_name}."
        print(f"Transcription (Correct): {audio_results['transcribed_text']}")
    else: # Language was detected, but it was the wrong one
        final_result, final_result_for_esp32 = "Wrong", "Wrong"
        feedback_msg = f"Wrong language. Detected {audio_results['detected_lang_key']}. Please speak in {target_language_name}."
        print(f"Transcription (Incorrect): Spoke {audio_results['detected_lang_key']}, heard: '{audio_results['transcribed_text']}'")
    
    # Log results to the database
    now = datetime.now()
    log_to_db("INSERT INTO student_interaction_logs (log_date, log_time, transcribed_text, detected_language, expected_language, result_status) VALUES (%s, %s, %s, %s, %s, %s)",
              (now.strftime("%Y-%m-%d"), now.strftime("%H:%M:%S"), audio_results["transcribed_text"], audio_results["detected_lang_key"], target_lang_key, final_result_for_esp32), db_conn)
    
    status_enum = 'correct' if final_result == 'Correct' else 'incorrect'
    log_to_db("INSERT INTO language_usage (usage_date, detected_language, status, language_id) VALUES (%s, %s, %s, %s)",
              (now.strftime("%Y-%m-%d"), audio_results["detected_lang_key"], status_enum, target_language_id), db_conn)
    
    # Provide final feedback (sound and ESP32)
    if feedback_msg:
        print(f"\nFINAL FEEDBACK: {feedback_msg}")
        play_feedback_sound(feedback_msg, lang='en')
        send_result_to_esp32(
            final_result_for_esp32,
            audio_results['transcribed_text'],
            audio_results['detected_lang_key'],
            target_lang_key
        )

    db_conn.close()
    print("\nScript finished.")


if __name__ == "__main__":
    main()