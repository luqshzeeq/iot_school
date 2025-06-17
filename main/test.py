import speech_recognition as sr
from gtts import gTTS
from playsound import playsound
from datetime import datetime
import csv
from lingua import Language, LanguageDetectorBuilder
import mysql.connector
import os
# sys module is no longer needed if no command-line arguments are passed
# import sys

# ===== DATABASE CONFIGURATION =====
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'language_monitor'
}

# No longer need to get TEACHER_ID from command line or hardcode
# TEACHER_ID = 1 # This line is removed

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

# MODIFIED: Removed teacher_id parameter
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
        # Query the modified teacher_daily_languages table based only on date
        query = """
        SELECT tdl.language_id, l.language_name
        FROM teacher_daily_languages tdl
        JOIN languages l ON tdl.language_id = l.id
        WHERE tdl.setting_date = %s
        LIMIT 1
        """
        cursor.execute(query, (date_str,)) # Only date_str is passed
        result = cursor.fetchone()

        if result:
            target_language_id = result['language_id']
            target_language_name = result['language_name']
            internal_lang_key = DB_LANG_TO_INTERNAL_KEY.get(target_language_name)
            print(f"✅ Found global daily language setting for {date_str}: {target_language_name} (ID: {target_language_id})")
        else:
            print(f"❌ No global language setting found for {date_str}.")

    except mysql.connector.Error as err:
        print(f"Error fetching language of the day: {err}")
    finally:
        cursor.close()
        conn.close()
    return target_language_id, internal_lang_key

# MODIFIED: Removed teacher_id parameter
def log_language_usage_to_db(language_id, detected_lang_str, status, transcribed_text):
    """Logs the language usage details to the language_usage table."""
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor()
    try:
        current_date = datetime.now().strftime("%Y-%m-%d")
        # Ensure 'status' is either 'correct' or 'incorrect' as per ENUM definition
        status_enum = 'correct' if status == 'Correct' or status == 'Correct (Fallback)' else 'incorrect'

        # MODIFIED: Removed teacher_id from INSERT query
        query = """
        INSERT INTO language_usage (language_id, usage_date, detected_language, status, timestamp)
        VALUES (%s, %s, %s, %s, NOW())
        """
        cursor.execute(query, (language_id, current_date, detected_lang_str, status_enum))
        conn.commit()
        print("✅ Language usage logged to database.")
    except mysql.connector.Error as err:
        print(f"Error logging language usage: {err}")
    finally:
        cursor.close()
        conn.close()

# ===== START PROCESS =====
current_date_str = datetime.now().strftime("%Y-%m-%d")

# MODIFIED: No longer passing teacher_id
target_language_id, target_language_internal_key = get_language_of_day_from_db(current_date_str)

if not target_language_internal_key:
    print("❌ Could not determine target language from database. Exiting.")
    play_feedback_sound("Sorry, I cannot determine the language for today. Please contact support.")
    exit()

print(f"📅 Today is {datetime.now().strftime('%A')}, {current_date_str}. Expected language: {target_language_internal_key.capitalize()}")
print("🎤 Please speak your order...")

# Start mic (remains unchanged)
recognizer = sr.Recognizer()
with sr.Microphone() as source:
    recognizer.adjust_for_ambient_noise(source, duration=1)
    print("🎧 Listening...")
    audio = recognizer.listen(source)

user_text = ""

try:
    # Transcribe audio using the speech code for the detected target language (remains unchanged)
    transcribed_text = recognizer.recognize_google(audio, language=speech_lang_code[target_language_internal_key])
    print(f"📝 Transcribed: {transcribed_text}")
    user_text = transcribed_text.lower().strip()

    # Step 1: AI detect language using Lingua (remains unchanged)
    detected_lingua_lang = detector.detect_language_of(user_text)
    detected_lang_name = detected_lingua_lang.name.upper() if detected_lingua_lang else "UNKNOWN"
    print(f"🌍 Detected language (Lingua): {detected_lang_name}")

    feedback = ""
    result = "Wrong"

    # Step 2: AI match - Lingua detected language matches the target (remains unchanged)
    if detected_lang_name == target_language_internal_key:
        feedback = f"✅ Correct! You spoke in {target_language_internal_key.capitalize()}."
        result = "Correct"
    # Step 3: Fallback - expected phrase match in the target language (remains unchanged)
    elif any(phrase.lower() in user_text for phrase in expected_phrases.get(target_language_internal_key, [])):
        feedback = f"✅ Acceptable phrase in {target_language_internal_key.capitalize()}."
        result = "Correct (Fallback)"
    # Step 4: Fail (remains unchanged)
    else:
        feedback = f"❌ Wrong language. Please speak in {target_language_internal_key.capitalize()}."
        result = "Wrong"

    # Audio response (remains unchanged)
    play_feedback_sound(feedback, lang='en')

    # Logging to CSV (existing functionality) (remains unchanged)
    now = datetime.now()
    with open("canteen_log.csv", mode='a', newline='', encoding='utf-8') as file:
        writer = csv.writer(file)
        writer.writerow([
            now.strftime("%Y-%m-%d"),
            now.strftime("%H:%M:%S"),
            transcribed_text,
            detected_lang_name,
            target_language_internal_key,
            result
        ])
    print("✅ Logged to canteen_log.csv")

    # Logging to MySQL (new functionality) - MODIFIED: No longer passing teacher_id
    log_language_usage_to_db(target_language_id, detected_lang_name, result, transcribed_text)

except sr.UnknownValueError:
    print("❌ Could not understand audio.")
    feedback = f"Sorry, I couldn’t understand. Please try again in {target_language_internal_key.capitalize()}."
    play_feedback_sound(feedback, lang='en')
    # Log 'Unknown' detection to DB - MODIFIED: No longer passing teacher_id
    log_language_usage_to_db(target_language_id, "UNKNOWN", "Wrong", "Could not understand audio")

except sr.RequestError as e:
    print(f"❌ Could not request results from Google Speech Recognition service; {e}")
    feedback = "Speech recognition service error. Please check your internet connection."
    play_feedback_sound(feedback, lang='en')
    # Log error - MODIFIED: No longer passing teacher_id
    log_language_usage_to_db(target_language_id, "ERROR", "Wrong", f"Speech Recognition Error: {e}")

except Exception as e:
    print(f"❌ Error: {e}")
    feedback = "An unexpected error occurred. Please try again."
    play_feedback_sound(feedback, lang='en')
    # Log error - MODIFIED: No longer passing teacher_id
    log_language_usage_to_db(target_language_id, "ERROR", "Wrong", f"Unexpected Error: {e}")