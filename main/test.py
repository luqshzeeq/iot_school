import speech_recognition as sr
from gtts import gTTS
from playsound import playsound
from datetime import datetime
import csv
from lingua import Language, LanguageDetectorBuilder
import mysql.connector # Added for MySQL database interaction
import os # Added for playing sound and removing the file

# ===== DATABASE CONFIGURATION =====
DB_CONFIG = {
    'host': '127.0.0.1', # Your database host
    'user': 'root',      # Your database username
    'password': '',      # Your database password
    'database': 'language_monitor' # Your database name
}

# For demonstration, assuming a teacher_id. In a real app, this would come from authentication.
DEMO_TEACHER_ID = 1

# ===== CONFIGURATION =====

# Expected phrases per language
# NOTE: These are still used for fallback matching, but the target language
# itself will now be dynamically fetched from the database.
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

# Google Speech API language codes
speech_lang_code = {
    'ENGLISH': 'en-US',
    'MALAY': 'ms-MY',
    'CHINESE': 'zh-CN', # Simplified Chinese
    'TAMIL': 'ta-IN'
}

# Map database language names to internal keys
DB_LANG_TO_INTERNAL_KEY = {
    'Bahasa Melayu': 'MALAY',
    'English': 'ENGLISH',
    'Mandarin': 'CHINESE',
    'Tamil': 'TAMIL' # Assuming 'Tamil' will be added to your languages table
}

# Setup language detection (lingua)
language_map = {
    'ENGLISH': Language.ENGLISH,
    'MALAY': Language.MALAY,
    'CHINESE': Language.CHINESE,
    'TAMIL': Language.TAMIL
}
detector = LanguageDetectorBuilder.from_languages(*language_map.values()).build()

# Helper function to play sound and clean up
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

def get_language_of_day_from_db(teacher_id, date_str):
    """
    Fetches the language set for a specific teacher on a given date from the database.
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
        # First, try to get the specific language set for the day
        query = """
        SELECT tdl.language_id, l.language_name
        FROM teacher_daily_languages tdl
        JOIN languages l ON tdl.language_id = l.id
        WHERE tdl.teacher_id = %s AND tdl.setting_date = %s
        LIMIT 1
        """
        cursor.execute(query, (teacher_id, date_str))
        result = cursor.fetchone()

        if result:
            target_language_id = result['language_id']
            target_language_name = result['language_name']
            internal_lang_key = DB_LANG_TO_INTERNAL_KEY.get(target_language_name)
            print(f"✅ Found daily language setting for Teacher {teacher_id} on {date_str}: {target_language_name} (ID: {target_language_id})")
        else:
            # Fallback: get the default language from teacher_settings if no daily setting
            print(f"No specific daily language setting for Teacher {teacher_id} on {date_str}. Checking default settings...")
            query = """
            SELECT ts.language_id, l.language_name
            FROM teacher_settings ts
            JOIN languages l ON ts.language_id = l.id
            WHERE ts.teacher_id = %s
            LIMIT 1
            """
            cursor.execute(query, (teacher_id,))
            result = cursor.fetchone()
            if result:
                target_language_id = result['language_id']
                target_language_name = result['language_name']
                internal_lang_key = DB_LANG_TO_INTERNAL_KEY.get(target_language_name)
                print(f"✅ Found default language setting for Teacher {teacher_id}: {target_language_name} (ID: {target_language_id})")
            else:
                print(f"❌ No language setting found for Teacher {teacher_id}.")

    except mysql.connector.Error as err:
        print(f"Error fetching language of the day: {err}")
    finally:
        cursor.close()
        conn.close()
    return target_language_id, internal_lang_key

def log_language_usage_to_db(teacher_id, language_id, detected_lang_str, status, transcribed_text):
    """Logs the language usage details to the language_usage table."""
    conn = get_db_connection()
    if not conn:
        return

    cursor = conn.cursor()
    try:
        current_date = datetime.now().strftime("%Y-%m-%d")
        # Ensure 'status' is either 'correct' or 'incorrect' as per ENUM definition
        status_enum = 'correct' if status == 'Correct' or status == 'Correct (Fallback)' else 'incorrect'

        query = """
        INSERT INTO language_usage (teacher_id, language_id, usage_date, detected_language, status, timestamp)
        VALUES (%s, %s, %s, %s, %s, NOW())
        """
        cursor.execute(query, (teacher_id, language_id, current_date, detected_lang_str, status_enum))
        conn.commit()
        print("✅ Language usage logged to database.")
    except mysql.connector.Error as err:
        print(f"Error logging language usage: {err}")
    finally:
        cursor.close()
        conn.close()

# ===== START PROCESS =====
current_date_str = datetime.now().strftime("%Y-%m-%d")

# Fetch target language from database
target_language_id, target_language_internal_key = get_language_of_day_from_db(DEMO_TEACHER_ID, current_date_str)

if not target_language_internal_key:
    print("❌ Could not determine target language from database. Exiting.")
    play_feedback_sound("Sorry, I cannot determine the language for today. Please contact support.")
    exit()

print(f"📅 Today is {datetime.now().strftime('%A')}, {current_date_str}. Expected language: {target_language_internal_key.capitalize()}")
print("🎤 Please speak your order...")

# Start mic
recognizer = sr.Recognizer()
with sr.Microphone() as source:
    recognizer.adjust_for_ambient_noise(source, duration=1)
    print("🎧 Listening...")
    audio = recognizer.listen(source)

user_text = "" # Initialize user_text outside try block

try:
    # Transcribe audio using the speech code for the detected target language
    transcribed_text = recognizer.recognize_google(audio, language=speech_lang_code[target_language_internal_key])
    print(f"📝 Transcribed: {transcribed_text}")
    user_text = transcribed_text.lower().strip()

    # Step 1: AI detect language using Lingua
    detected_lingua_lang = detector.detect_language_of(user_text)
    detected_lang_name = detected_lingua_lang.name.upper() if detected_lingua_lang else "UNKNOWN"
    print(f"🌍 Detected language (Lingua): {detected_lang_name}")

    feedback = ""
    result = "Wrong" # Default result

    # Step 2: AI match - Lingua detected language matches the target
    if detected_lang_name == target_language_internal_key:
        feedback = f"✅ Correct! You spoke in {target_language_internal_key.capitalize()}."
        result = "Correct"
    # Step 3: Fallback - expected phrase match in the target language
    elif any(phrase.lower() in user_text for phrase in expected_phrases.get(target_language_internal_key, [])):
        feedback = f"✅ Acceptable phrase in {target_language_internal_key.capitalize()}."
        result = "Correct (Fallback)"
    # Step 4: Fail
    else:
        feedback = f"❌ Wrong language. Please speak in {target_language_internal_key.capitalize()}."
        result = "Wrong"

    # Audio response
    play_feedback_sound(feedback, lang='en')

    # Logging to CSV (existing functionality)
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

    # Logging to MySQL (new functionality)
    log_language_usage_to_db(DEMO_TEACHER_ID, target_language_id, detected_lang_name, result, transcribed_text)

except sr.UnknownValueError:
    print("❌ Could not understand audio.")
    feedback = f"Sorry, I couldn’t understand. Please try again in {target_language_internal_key.capitalize()}."
    play_feedback_sound(feedback, lang='en')
    # Log 'Unknown' detection to DB
    log_language_usage_to_db(DEMO_TEACHER_ID, target_language_id, "UNKNOWN", "Wrong", "Could not understand audio")

except sr.RequestError as e:
    print(f"❌ Could not request results from Google Speech Recognition service; {e}")
    feedback = "Speech recognition service error. Please check your internet connection."
    play_feedback_sound(feedback, lang='en')
    log_language_usage_to_db(DEMO_TEACHER_ID, target_language_id, "ERROR", "Wrong", f"Speech Recognition Error: {e}")

except Exception as e:
    print(f"❌ Error: {e}")
    feedback = "An unexpected error occurred. Please try again."
    play_feedback_sound(feedback, lang='en')
    log_language_usage_to_db(DEMO_TEACHER_ID, target_language_id, "ERROR", "Wrong", f"Unexpected Error: {e}")

